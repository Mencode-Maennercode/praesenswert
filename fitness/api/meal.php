<?php
/**
 * Essen erkennen - aus einem Foto oder aus einem Satz.
 *
 *   start   Foto oder Text hinein, Schätzung heraus
 *   answer  Antwort auf eine Rückfrage, verfeinerte Schätzung heraus
 *
 * Gespeichert wird hier nichts. Wenn die Schätzung stimmt, trägt der
 * Client sie über day.php ein - derselbe Weg wie bei der Eingabe von Hand.
 * Damit gibt es genau eine Stelle, an der Einträge entstehen.
 *
 * Die Rückfragen laufen über eine serverseitige Sitzung. Zwei Gründe:
 *   - Das Bild wird einmal hochgeladen, nicht bei jeder Antwort erneut.
 *     Sonst dreifache Kosten, dreifache Wartezeit, spürbar auf Mobilfunk.
 *   - Eine Obergrenze, die der Client zählt, ist keine Obergrenze.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

cors();
requireMethod('POST');

// Ein Foto kommt als base64 - das sprengt die übliche Grenze deutlich.
$body = jsonBody(3 * 1024 * 1024);
[$uid, $user] = requireUser($body);

if (!is_array($user['derived'] ?? null)) {
    fail('Erst das Onboarding abschließen.', 409, 'kein-profil');
}

const MAX_RUECKFRAGEN = 3;
const SITZUNG_TTL = 900; // 15 Minuten

match (clean($body['action'] ?? '', 20)) {
    'start' => starten($uid, $user, $body),
    'answer' => antworten($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

/* ------------------------------------------------------------------ Schema */

function schema(): array
{
    return [
        'type' => 'OBJECT',
        'properties' => [
            'gericht' => ['type' => 'STRING'],
            'menge' => ['type' => 'STRING'],
            'kcal' => ['type' => 'INTEGER'],
            'eiweiss_g' => ['type' => 'NUMBER'],
            'kohlenhydrate_g' => ['type' => 'NUMBER'],
            'fett_g' => ['type' => 'NUMBER'],
            'konfidenz' => ['type' => 'NUMBER'],
            'rueckfrage' => ['type' => 'STRING'],
            'antwortoptionen' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            'alternative' => ['type' => 'STRING'],
        ],
        'required' => [
            'gericht', 'menge', 'kcal', 'eiweiss_g', 'kohlenhydrate_g', 'fett_g',
            'konfidenz', 'rueckfrage', 'antwortoptionen', 'alternative',
        ],
        'propertyOrdering' => [
            'gericht', 'menge', 'kcal', 'eiweiss_g', 'kohlenhydrate_g', 'fett_g',
            'konfidenz', 'rueckfrage', 'antwortoptionen', 'alternative',
        ],
    ];
}

function anweisung(array $user, int $offeneFragen): string
{
    $profil = profilText($user);
    $ton = tonRegeln();

    $fragen = $offeneFragen > 0
        ? "Wenn eine einzige kurze Rückfrage die Schätzung deutlich verbessern würde, "
            . "stelle sie in 'rueckfrage' und biete in 'antwortoptionen' drei bis vier "
            . "antippbare Antworten an (z. B. Portionsgrössen). Sonst lass beide Felder leer. "
            . "Frage nur nach dem, was den grössten Unterschied macht - meist die Menge."
        : "Stelle KEINE Rückfrage mehr. 'rueckfrage' und 'antwortoptionen' bleiben leer. "
            . "Entscheide dich für die wahrscheinlichste Schätzung.";

    return <<<TEXT
        Du schätzt Nährwerte von Mahlzeiten für ein privates Ernährungstagebuch.
        Antworte auf Deutsch.

        {$profil}

        Regeln:
        - 'gericht' ist eine kurze Bezeichnung, wie sie in einer Liste steht
          (z. B. "Nudeln mit Pesto"), ohne Beiwerk.
        - 'menge' beschreibt die geschätzte Portion (z. B. "ca. 350 g" oder "1 Teller").
        - Nährwerte gelten für die GESAMTE sichtbare Portion, nicht pro 100 g.
        - 'konfidenz' ist deine ehrliche Sicherheit von 0 bis 1. Bei einem Foto
          ohne Grössenbezug liegt sie niedrig. Rate die Konfidenz nicht hoch.
        - Bei deutlich kalorienreichem Essen (über 700 kcal) nenne in 'alternative'
          EINEN konkreten Austausch mit ungefährer Ersparnis
          (z. B. "Ofenkartoffeln statt Pommes: ca. 340 kcal weniger").
          Sonst bleibt 'alternative' leer.

        {$fragen}

        {$ton}
        TEXT;
}

/* ------------------------------------------------------------- Sitzungen */

function sitzungPfad(string $id): string
{
    return AI_DIR . '/' . $id . '.json';
}

function sitzungLaden(string $id, string $uid): array
{
    if (preg_match('/^s_[a-f0-9]{24}$/', $id) !== 1) {
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    $s = readJson(sitzungPfad($id), []);
    if ($s === [] || ($s['uid'] ?? '') !== $uid) {
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    if (strtotime((string) ($s['createdAt'] ?? '')) < time() - SITZUNG_TTL) {
        @unlink(sitzungPfad($id));
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    return $s;
}

/**
 * Alte Sitzungen wegräumen.
 *
 * Gelegentlich statt bei jedem Aufruf: Ein Verzeichnis mit ein paar
 * hundert Kilobyte durchzugehen lohnt nicht bei jeder Anfrage, und die
 * Dateien schaden bis dahin niemandem.
 */
function sitzungenAufraeumen(): void
{
    if (random_int(1, 25) !== 1) {
        return;
    }
    foreach (glob(AI_DIR . '/*.json') ?: [] as $datei) {
        if (filemtime($datei) < time() - SITZUNG_TTL) {
            @unlink($datei);
        }
    }
}

/* ---------------------------------------------------------------- Starten */

function starten(string $uid, array $user, array $body): never
{
    rateLimit('ai', 20, 600, $uid);
    sitzungenAufraeumen();

    $bild = pruefeBild($body['bild'] ?? null);
    $text = clean($body['text'] ?? '', 400);

    if ($bild === null && $text === '') {
        fail('Kein Foto und kein Text.', 400, 'leer');
    }

    [$erlaubt, $user] = aiUserBudget($user);
    $key = aiKey();

    if (!$erlaubt || $key === '' || !aiBudgetAvailable()) {
        saveUser($uid, $user);
        // Kein Fehler, sondern ein Hinweis: Der Client zeigt dann die
        // Eingabe von Hand. Die KI darf nie der Grund sein, dass es nicht geht.
        send(withFreshToken([
            'ok' => true,
            'ki' => false,
            'grund' => $key === '' ? 'kein-schluessel' : 'budget',
        ], $uid, $user, $body));
    }
    saveUser($uid, $user);

    $teile = [];
    if ($bild !== null) {
        $teile[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $bild]];
    }
    $teile[] = ['text' => $text !== '' ? $text : 'Was ist auf dem Bild, und wie viele Kalorien hat es?'];

    $roh = geminiJson($key, AI_MODEL_FOTO, $teile, schema(), anweisung($user, MAX_RUECKFRAGEN));
    if ($roh === null) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'fehler'], $uid, $user, $body));
    }

    $id = 's_' . bin2hex(random_bytes(12));
    writeJson(sitzungPfad($id), [
        'uid' => $uid,
        'createdAt' => date('c'),
        'turns' => 0,
        'bild' => $bild,
        'text' => $text,
        'verlauf' => [],
    ]);

    send(withFreshToken(antwortPaket($id, $roh, MAX_RUECKFRAGEN), $uid, $user, $body));
}

/* -------------------------------------------------------------- Antworten */

function antworten(string $uid, array $user, array $body): never
{
    rateLimit('ai', 20, 600, $uid);

    $id = clean($body['sessionId'] ?? '', 40);
    $s = sitzungLaden($id, $uid);

    $antwort = clean($body['antwort'] ?? '', 200);
    if ($antwort === '') {
        fail('Keine Antwort.', 400, 'leer');
    }

    $turns = (int) $s['turns'] + 1;
    $offen = max(0, MAX_RUECKFRAGEN - $turns);

    [$erlaubt, $user] = aiUserBudget($user);
    $key = aiKey();
    if (!$erlaubt || $key === '' || !aiBudgetAvailable()) {
        saveUser($uid, $user);
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'budget'], $uid, $user, $body));
    }
    saveUser($uid, $user);

    $s['verlauf'][] = $antwort;

    $teile = [];
    if (is_string($s['bild'] ?? null)) {
        $teile[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $s['bild']]];
    }
    $ausgangstext = (string) ($s['text'] ?? '');
    if ($ausgangstext !== '') {
        $teile[] = ['text' => 'Ursprüngliche Angabe: ' . $ausgangstext];
    }
    $teile[] = ['text' => "Zusätzliche Angaben der Person:\n- " . implode("\n- ", $s['verlauf'])];

    $roh = geminiJson($key, AI_MODEL_FOTO, $teile, schema(), anweisung($user, $offen));
    if ($roh === null) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'fehler'], $uid, $user, $body));
    }

    $s['turns'] = $turns;
    writeJson(sitzungPfad($id), $s);

    send(withFreshToken(antwortPaket($id, $roh, $offen), $uid, $user, $body));
}

/* -------------------------------------------------------------- Ergebnis */

function antwortPaket(string $id, array $roh, int $offen): array
{
    $werte = klammereNaehrwerte($roh);

    $titel = clean($roh['gericht'] ?? '', 80);
    if ($titel === '') {
        $titel = 'Mahlzeit';
    }

    // Nach der letzten Runde wird jede weitere Rückfrage verworfen. Der
    // Server zählt, nicht der Client.
    $frage = $offen > 0 ? clean($roh['rueckfrage'] ?? '', 200) : '';

    $optionen = [];
    if ($frage !== '' && is_array($roh['antwortoptionen'] ?? null)) {
        foreach (array_slice($roh['antwortoptionen'], 0, 4) as $o) {
            $sauber = clean($o, 60);
            if ($sauber !== '') {
                $optionen[] = $sauber;
            }
        }
    }

    return [
        'ok' => true,
        'ki' => true,
        'sessionId' => $id,
        'ergebnis' => [
            'title' => $titel,
            'menge' => clean($roh['menge'] ?? '', 60),
            ...$werte,
            'alternative' => clean($roh['alternative'] ?? '', 200),
        ],
        'rueckfrage' => $frage,
        'optionen' => $optionen,
        'offen' => $offen,
    ];
}

/* ------------------------------------------------------------------- Bild */

/**
 * Nimmt nur an, was wirklich ein JPEG ist.
 *
 * Der Client verkleinert jedes Foto über ein Canvas und gibt JPEG aus -
 * hier kann also nichts anderes ankommen, ausser jemand ruft die
 * Schnittstelle von Hand auf. Genau dafür ist die Prüfung da.
 */
function pruefeBild(mixed $roh): ?string
{
    if (!is_string($roh) || $roh === '') {
        return null;
    }
    if (strlen($roh) > 2 * 1024 * 1024) {
        fail('Foto zu groß.', 413, 'zu-gross');
    }

    $binaer = base64_decode($roh, true);
    if ($binaer === false || strlen($binaer) < 512) {
        fail('Foto unlesbar.', 400, 'bild');
    }
    // JPEG beginnt immer mit FF D8 FF.
    if (substr($binaer, 0, 3) !== "\xFF\xD8\xFF") {
        fail('Nur JPEG.', 400, 'bild');
    }
    return $roh;
}
