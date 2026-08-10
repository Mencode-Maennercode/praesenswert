<?php
/**
 * Sprache: einmal reden, die App sortiert es ein.
 *
 *   start   Aufnahme oder Text hinein
 *   answer  Antwort auf eine Rückfrage
 *
 * Der entscheidende Punkt: Es gibt KEINE Vorauswahl zwischen "Essen" und
 * "Sport". Wer redet, will reden - nicht vorher entscheiden, in welche
 * Schublade sein Satz gehört. Das Modell erkennt es selbst und liefert
 * 'art' mit.
 *
 * Bei Sport rechnet ausschliesslich PHP. Das Modell darf nur zuordnen,
 * welche Aktivität gemeint war, und wie lange sie dauerte - die Kalorien
 * kommen aus der MET-Tabelle. Ein Modell, das heute 9,8 und morgen 10,5
 * für dasselbe Laufen sagt, macht den Wochenverlauf unvergleichbar.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';
require __DIR__ . '/_ai.php';

cors();
requireMethod('POST');

// 30 Sekunden 16-kHz-WAV sind knapp 1 MB, base64 rund 1,3 MB.
$body = jsonBody(3 * 1024 * 1024);
[$uid, $user] = requireUser($body);

if (!is_array($user['derived'] ?? null)) {
    fail('Erst das Onboarding abschließen.', 409, 'kein-profil');
}

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
            'art' => ['type' => 'STRING', 'enum' => ['essen', 'sport', 'unklar']],
            // Essen
            'gericht' => ['type' => 'STRING'],
            'menge' => ['type' => 'STRING'],
            'kcal' => ['type' => 'INTEGER'],
            'eiweiss_g' => ['type' => 'NUMBER'],
            'kohlenhydrate_g' => ['type' => 'NUMBER'],
            'fett_g' => ['type' => 'NUMBER'],
            'alternative' => ['type' => 'STRING'],
            // Sport - die Aktivität ist eine feste Auswahl, kein Freitext.
            // Damit kann nur ein Schlüssel zurückkommen, den die MET-Tabelle
            // wirklich kennt.
            'aktivitaet' => ['type' => 'STRING', 'enum' => array_keys(metTable())],
            'minuten' => ['type' => 'INTEGER'],
            // Beides
            'konfidenz' => ['type' => 'NUMBER'],
            'rueckfrage' => ['type' => 'STRING'],
            'antwortoptionen' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        ],
        'required' => [
            'art', 'gericht', 'menge', 'kcal', 'eiweiss_g', 'kohlenhydrate_g', 'fett_g',
            'alternative', 'aktivitaet', 'minuten', 'konfidenz', 'rueckfrage', 'antwortoptionen',
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
            . "antippbare Antworten an. Sonst lass beide Felder leer."
        : "Stelle KEINE Rückfrage mehr. 'rueckfrage' und 'antwortoptionen' bleiben leer.";

    return <<<TEXT
        Du hörst dir an, was jemand gegessen oder an Bewegung gemacht hat, und
        trägst es in ein privates Tagebuch ein. Antworte auf Deutsch.

        {$profil}

        Setze 'art':
        - "essen" bei Mahlzeiten, Getränken, Snacks
        - "sport" bei Bewegung und Training
        - "unklar", wenn beides oder nichts davon gemeint ist

        Bei "essen":
        - 'gericht' ist eine kurze Bezeichnung für eine Liste, ohne Beiwerk.
        - 'menge' beschreibt die Portion, so wie sie genannt wurde.
        - Nährwerte gelten für die GESAMTE genannte Portion, nicht pro 100 g.
        - Bei über 700 kcal nenne in 'alternative' EINEN konkreten Austausch
          mit ungefährer Ersparnis. Sonst bleibt das Feld leer.
        - 'aktivitaet' auf "sonstiges", 'minuten' auf 0.

        Bei "sport":
        - Ordne der am besten passenden 'aktivitaet' aus der Liste zu.
        - 'minuten' ist die genannte Dauer. Wurde keine genannt, setze 0 und
          frage danach - die Dauer ist die einzige Angabe, ohne die nichts geht.
        - Nährwertfelder auf 0, 'gericht' und 'menge' leer.
        - Rechne KEINE Kalorien aus. Das macht der Server.

        'konfidenz' ist deine ehrliche Sicherheit von 0 bis 1.

        {$fragen}

        {$ton}
        TEXT;
}

/* --------------------------------------------------------------- Aufrufen */

function frageGemini(array $user, array $teile, int $offen, ?int &$status = null): ?array
{
    return geminiJson(aiKey(), AI_MODEL_TEXT, $teile, schema(), anweisung($user, $offen), $status);
}

/**
 * "Gerade zu viele" ist etwas anderes als "kaputt".
 *
 * Bei 429 lohnt ein zweiter Anlauf in einer Minute, bei allem anderen
 * nicht. Wer das nicht unterscheidet, schickt Nutzer zum Warten, wenn
 * nichts kommt - oder lässt sie aufgeben, wo Geduld gereicht hätte.
 */
function grundFuer(?int $status): string
{
    return $status === 429 ? 'gebremst' : 'fehler';
}

function starten(string $uid, array $user, array $body): never
{
    rateLimit('ai', 20, 600, $uid);
    sitzungenAufraeumen();

    $audio = pruefeAudio($body['audio'] ?? null);
    $text = clean($body['text'] ?? '', 400);

    if ($audio === null && $text === '') {
        fail('Nichts gehört.', 400, 'leer');
    }

    [$erlaubt, $user] = aiUserBudget($user);
    $key = aiKey();

    if (!$erlaubt || $key === '' || !aiBudgetAvailable()) {
        saveUser($uid, $user);
        send(withFreshToken([
            'ok' => true,
            'ki' => false,
            'grund' => $key === '' ? 'kein-schluessel' : 'budget',
        ], $uid, $user, $body));
    }
    saveUser($uid, $user);

    $teile = [];
    if ($audio !== null) {
        $teile[] = ['inline_data' => ['mime_type' => 'audio/wav', 'data' => $audio]];
    }
    if ($text !== '') {
        $teile[] = ['text' => $text];
    } else {
        $teile[] = ['text' => 'Trag das ein, was in der Aufnahme gesagt wird.'];
    }

    $roh = frageGemini($user, $teile, MAX_RUECKFRAGEN, $status);
    if ($roh === null) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => grundFuer($status)], $uid, $user, $body));
    }

    $id = sitzungNeu($uid, ['audio' => $audio, 'text' => $text]);
    send(withFreshToken(paket($id, $roh, $user, MAX_RUECKFRAGEN), $uid, $user, $body));
}

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
    if (!$erlaubt || aiKey() === '' || !aiBudgetAvailable()) {
        saveUser($uid, $user);
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'budget'], $uid, $user, $body));
    }
    saveUser($uid, $user);

    $s['verlauf'][] = $antwort;

    $teile = [];
    if (is_string($s['audio'] ?? null)) {
        $teile[] = ['inline_data' => ['mime_type' => 'audio/wav', 'data' => $s['audio']]];
    }
    if (($s['text'] ?? '') !== '') {
        $teile[] = ['text' => 'Ursprüngliche Angabe: ' . $s['text']];
    }
    $teile[] = ['text' => "Zusätzliche Angaben der Person:\n- " . implode("\n- ", $s['verlauf'])];

    $roh = frageGemini($user, $teile, $offen, $status);
    if ($roh === null) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => grundFuer($status)], $uid, $user, $body));
    }

    $s['turns'] = $turns;
    sitzungSpeichern($id, $s);

    send(withFreshToken(paket($id, $roh, $user, $offen), $uid, $user, $body));
}

/* -------------------------------------------------------------- Ergebnis */

function paket(string $id, array $roh, array $user, int $offen): array
{
    $art = (string) ($roh['art'] ?? 'unklar');
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

    $paket = [
        'ok' => true,
        'ki' => true,
        'sessionId' => $id,
        'art' => $art,
        'rueckfrage' => $frage,
        'optionen' => $optionen,
        'offen' => $offen,
        'konfidenz' => max(0.0, min(1.0, (float) ($roh['konfidenz'] ?? 0.5))),
    ];

    if ($art === 'sport') {
        $key = (string) ($roh['aktivitaet'] ?? '');
        $met = metFor($key);
        if ($met === null) {
            $key = 'sonstiges';
            $met = metFor('sonstiges') ?? 5.0;
        }

        $minuten = max(0, min(600, (int) ($roh['minuten'] ?? 0)));
        $tabelle = metTable();
        $kg = (float) ($user['profile']['weightKg'] ?? 75);

        $paket['sport'] = [
            'activity' => $key,
            'label' => $tabelle[$key]['label'],
            'minutes' => $minuten,
            // Aus der Tabelle gerechnet, nicht vom Modell übernommen.
            'kcal' => $minuten > 0 ? sportKcal($met, $kg, $minuten) : 0,
            // Der MET-Wert geht mit, damit die Oberfläche beim Verschieben
            // der Dauer sofort mitrechnen kann - mit derselben Formel.
            'met' => $met,
        ];
        return $paket;
    }

    $werte = klammereNaehrwerte($roh);
    $titel = clean($roh['gericht'] ?? '', 80);

    $paket['essen'] = [
        'title' => $titel !== '' ? $titel : 'Mahlzeit',
        'menge' => clean($roh['menge'] ?? '', 60),
        ...$werte,
        'alternative' => clean($roh['alternative'] ?? '', 200),
    ];
    return $paket;
}

/* ------------------------------------------------------------------ Audio */

/**
 * Nimmt nur an, was wirklich ein WAV ist.
 *
 * Der Browser baut die Datei selbst zusammen, hier kann also nichts
 * anderes ankommen - ausser jemand ruft die Schnittstelle von Hand auf.
 * Genau dafür steht die Prüfung hier.
 */
function pruefeAudio(mixed $roh): ?string
{
    if (!is_string($roh) || $roh === '') {
        return null;
    }
    if (strlen($roh) > 2 * 1024 * 1024) {
        fail('Aufnahme zu lang.', 413, 'zu-gross');
    }

    $binaer = base64_decode($roh, true);
    if ($binaer === false || strlen($binaer) < 1024) {
        fail('Aufnahme unlesbar.', 400, 'audio');
    }
    if (substr($binaer, 0, 4) !== 'RIFF' || substr($binaer, 8, 4) !== 'WAVE') {
        fail('Nur WAV.', 400, 'audio');
    }
    return $roh;
}
