<?php
/**
 * Gemini-Anbindung.
 *
 * Aufgebaut wie check.php der Grillparty - dasselbe postJson mit cURL und
 * Stream-Rückfall, dieselbe Haltung: Die KI darf nie der Grund sein, dass
 * etwas nicht geht. Fällt sie aus, ist sie langsam oder ist das Kontingent
 * erschöpft, bleibt die Eingabe von Hand.
 *
 * Der Schlüssel verlässt den Server nie. Der Browser schickt nur das Bild,
 * die Anfrage an Google stellt PHP.
 */

declare(strict_types=1);

/**
 * Die Modellkette.
 *
 * Nicht ein Modell, sondern eine Reihenfolge - und das aus einem
 * gemessenen Grund: Das kostenlose Kontingent gilt PRO MODELL und ist
 * knapp. gemini-3.6-flash erlaubt zwanzig Anfragen am Tag. Für eine App,
 * in der man drei Mahlzeiten fotografiert und dabei Rückfragen beantwortet,
 * ist das nach einem halben Tag aufgebraucht.
 *
 * Läuft eines leer (HTTP 429), rückt das nächste nach. Die Reihenfolge
 * ist bewusst: erst die schnellen lite-Modelle mit den grössten
 * Kontingenten, dann die stärkeren als Reserve.
 *
 * Gemessene Antwortzeiten (Text, mit Schema):
 *   3.5-flash-lite 1,2 s | 3.1-flash-lite 4,3 s | 3.5-flash 2,6 s
 */
const AI_MODELLE = [
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-3.5-flash',
    'gemini-3-flash-preview',
    'gemini-2.5-flash-lite',
];
const AI_THINKING = 'low';
const AI_TIMEOUT = 25;      // Sekunden; ein Foto braucht länger als ein Satz
const AI_DAILY_MAX = 400;   // globale Tagesbremse
const AI_USER_MAX = 40;     // pro Nutzer und Tag

/* ------------------------------------------------------------- Kostenbremse */

/**
 * Globale Tagesbremse.
 *
 * Zwei gleichzeitige Aufrufe können sich hier gegenseitig überschreiben.
 * Bei einer Obergrenze, die im Normalbetrieb niemand erreicht, ist das
 * kein Problem - eine Sperre dafür wäre teurer als der Fehler.
 */
function aiBudgetAvailable(): bool
{
    ensureDirs();
    $file = DATA_DIR . '/ai-usage.json';
    $today = date('Y-m-d');
    $count = 0;

    $data = readJson($file, []);
    if (($data['day'] ?? '') === $today) {
        $count = (int) ($data['count'] ?? 0);
    }
    if ($count >= AI_DAILY_MAX) {
        return false;
    }

    @file_put_contents($file, json_encode(['day' => $today, 'count' => $count + 1]), LOCK_EX);
    return true;
}

/**
 * Bremse pro Nutzer.
 *
 * Ohne die könnte ein einzelnes Konto das Tageskontingent leerfahren und
 * alle anderen aussperren. Der Zähler steht im Nutzerdatensatz und wird
 * vom Aufrufer mitgespeichert.
 *
 * @return array{0: bool, 1: array} [erlaubt, aktualisierter Nutzer]
 */
function aiUserBudget(array $user): array
{
    $heute = date('Y-m-d');
    $stand = is_array($user['ai'] ?? null) ? $user['ai'] : [];
    $zahl = ($stand['day'] ?? '') === $heute ? (int) ($stand['count'] ?? 0) : 0;

    if ($zahl >= AI_USER_MAX) {
        return [false, $user];
    }

    $user['ai'] = ['day' => $heute, 'count' => $zahl + 1];
    return [true, $user];
}

/* --------------------------------------------------------------- Transport */

/**
 * Ein POST mit JSON.
 *
 * Gibt Status UND Rumpf zurück, nicht nur "hat geklappt oder nicht". Der
 * Unterschied entscheidet darüber, ob ein zweiter Versuch sinnvoll ist:
 * Bei 429 lohnt er sich, bei 403 nie.
 *
 * @return array{status: int, daten: ?array}
 */
function postJson(string $url, array $payload, array $headers, int $timeout = AI_TIMEOUT): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['status' => 0, 'daten' => null];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'daten' => null];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return ['status' => $status, 'daten' => is_array($decoded) ? $decoded : null];
    }

    // Ohne cURL: derselbe Aufruf über Streams. Langsamer, aber vorhanden.
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $json,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $zeile) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $zeile, $t) === 1) {
            $status = (int) $t[1];
        }
    }
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'daten' => is_array($decoded) ? $decoded : null];
}

/**
 * Ruft Gemini und gibt die geparste JSON-Antwort zurück.
 *
 * @param array $teile Inhalt der Anfrage: Text und/oder inline_data
 * @return array|null  null bei jedem Fehler - der Aufrufer weicht dann aus
 */
/**
 * @param int|null $status Letzter HTTP-Status - für eine ehrliche Meldung.
 *                         429 heisst "gerade zu viele", nicht "kaputt".
 */
function geminiJson(
    string $key,
    array $teile,
    array $schema,
    string $anweisung,
    ?int &$status = null,
    ?string &$modell = null,
): ?array {
    $header = ['Content-Type: application/json', 'x-goog-api-key: ' . $key];

    foreach (AI_MODELLE as $m) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent";

        // Zwei Anläufe je Modell: das Denken kostet manche Modelle einen
        // 400er, weil sie die Einstellung gar nicht kennen.
        foreach ([true, false] as $mitDenken) {
            $gen = [
                // Erzwungenes Schema statt Prompt-Bitte: Damit kommt garantiert
                // gültiges JSON zurück, und das Parsen kann nicht scheitern.
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
                'temperature' => 0.2,
            ];
            if ($mitDenken) {
                $gen['thinkingConfig'] = ['thinkingLevel' => AI_THINKING];
            }

            $antwort = postJson($url, [
                'systemInstruction' => ['parts' => [['text' => $anweisung]]],
                'contents' => [['role' => 'user', 'parts' => $teile]],
                'generationConfig' => $gen,
            ], $header);

            $status = $antwort['status'];
            $modell = $m;

            if ($status >= 200 && $status < 300) {
                $text = $antwort['daten']['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($text)) {
                    $daten = json_decode($text, true);
                    if (is_array($daten)) {
                        return $daten;
                    }
                }
                // Antwort kam an, war aber unbrauchbar - ein anderes Modell
                // hilft dagegen nicht.
                return null;
            }

            /*
             * "Thinking level is not supported for this model." - ältere
             * Modelle kennen die Einstellung nicht. Ohne sie noch einmal,
             * sonst fiele die halbe Kette wegen einer Zeile Konfiguration aus.
             */
            $meldung = (string) ($antwort['daten']['error']['message'] ?? '');
            if ($status === 400 && $mitDenken && stripos($meldung, 'thinking') !== false) {
                continue;
            }

            // Kontingent erschöpft: nächstes Modell. Alles andere ist ein
            // echter Fehler und wird beim nächsten Modell nicht besser.
            if ($status === 429) {
                break;
            }
            return null;
        }
    }

    return null;
}

/* --------------------------------------------------------------- Sitzungen */

/**
 * Eine Sitzung hält das, was nicht bei jeder Rückfrage erneut hochgeladen
 * werden soll: das Foto oder die Aufnahme.
 *
 * Ohne sie müsste ein 150-KB-Bild bei jeder Antwort erneut über die
 * Leitung und erneut an Google - dreifache Kosten, dreifache Wartezeit,
 * auf Mobilfunk deutlich spürbar. Und die Obergrenze von drei Rückfragen
 * zählt hier der Server; eine Grenze, die der Client zählt, ist keine.
 */
const SITZUNG_TTL = 900; // 15 Minuten
const MAX_RUECKFRAGEN = 3;

function sitzungPfad(string $id): string
{
    return AI_DIR . '/' . $id . '.json';
}

function sitzungNeu(string $uid, array $inhalt): string
{
    $id = 's_' . bin2hex(random_bytes(12));
    writeJson(sitzungPfad($id), [
        'uid' => $uid,
        'createdAt' => date('c'),
        'turns' => 0,
        'verlauf' => [],
        ...$inhalt,
    ]);
    return $id;
}

function sitzungLaden(string $id, string $uid): array
{
    if (preg_match('/^s_[a-f0-9]{24}$/', $id) !== 1) {
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    $s = readJson(sitzungPfad($id), []);
    // Die Prüfung auf den Besitzer ist die eigentliche Absicherung: Ohne sie
    // könnte jeder mit einer geratenen ID fremde Fotos weiteranalysieren.
    if ($s === [] || ($s['uid'] ?? '') !== $uid) {
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    if (strtotime((string) ($s['createdAt'] ?? '')) < time() - SITZUNG_TTL) {
        @unlink(sitzungPfad($id));
        fail('Sitzung abgelaufen.', 410, 'sitzung');
    }
    return $s;
}

function sitzungSpeichern(string $id, array $s): void
{
    writeJson(sitzungPfad($id), $s);
}

/**
 * Alte Sitzungen wegräumen - gelegentlich statt bei jedem Aufruf.
 *
 * Ein Verzeichnis mit ein paar hundert Kilobyte bei jeder Anfrage
 * durchzugehen lohnt nicht, und die Dateien schaden bis dahin niemandem.
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

/* ------------------------------------------------------------- Prüfklammer */

/**
 * Bringt eine KI-Schätzung in einen Bereich, in dem sie nicht schaden kann.
 *
 * Zwei Dinge passieren hier:
 *   1. Kalorien werden auf 1 bis 4000 geklemmt. Ein Modell, das 9000 kcal
 *      für einen Salat behauptet, darf das Tagesprotokoll nicht ruinieren.
 *   2. Die Makros werden gegen die Kalorien gegengerechnet (4/4/9). Weicht
 *      die Summe um mehr als ein Viertel ab, werden sie neu skaliert -
 *      sonst zeigt die App eine Eiweissmenge an, die zur Kalorienzahl
 *      nicht passt, und beide Zahlen verlieren ihren Wert.
 */
function klammereNaehrwerte(array $roh): array
{
    $kcal = (int) round(max(1.0, min(4000.0, (float) ($roh['kcal'] ?? 0))));
    $p = max(0.0, min(400.0, (float) ($roh['eiweiss_g'] ?? 0)));
    $c = max(0.0, min(800.0, (float) ($roh['kohlenhydrate_g'] ?? 0)));
    $f = max(0.0, min(400.0, (float) ($roh['fett_g'] ?? 0)));

    $ausMakros = 4 * $p + 4 * $c + 9 * $f;
    if ($ausMakros > 0 && abs($ausMakros - $kcal) / $kcal > 0.25) {
        $faktor = $kcal / $ausMakros;
        $p *= $faktor;
        $c *= $faktor;
        $f *= $faktor;
    }

    return [
        'kcal' => $kcal,
        'p' => round($p, 1),
        'c' => round($c, 1),
        'f' => round($f, 1),
        'konfidenz' => max(0.0, min(1.0, (float) ($roh['konfidenz'] ?? 0.5))),
    ];
}

/* ------------------------------------------------------------- Anweisungen */

/**
 * Der Ton ist festgeschrieben, nicht dem Modell überlassen.
 *
 * In einer Kalorien-App ist das kein Geschmack, sondern ein
 * Sicherheitsmerkmal: Beschämung ist der Weg, auf dem so eine App Schaden
 * anrichtet. Deshalb steht hier ausdrücklich, was nicht passieren darf.
 */
function tonRegeln(): string
{
    return <<<'TEXT'
        Ton:
        - Nie beschämen, nie moralisieren, nie "zu viel" oder "leider".
        - Kein Verzicht vorschlagen, sondern Austausch: eine Variante dessen,
          was die Person eigentlich wollte. Niemals "iss lieber Salat".
        - Sachlich und knapp. Keine Begrüssung, keine Floskeln.
        - Ein einzelner Tag bedeutet nichts.
        TEXT;
}

function profilText(array $user): string
{
    $p = is_array($user['profile'] ?? null) ? $user['profile'] : [];
    $d = is_array($user['derived'] ?? null) ? $user['derived'] : [];

    $sex = match ((string) ($p['sex'] ?? 'd')) {
        'm' => 'männlich',
        'w' => 'weiblich',
        default => 'divers',
    };

    return sprintf(
        'Person: %s, %d Jahre, %d cm, %.1f kg. Tagesziel %d kcal, Grundumsatz %d kcal.',
        $sex,
        alterAus((int) ($p['birthYear'] ?? 1990)),
        (int) ($p['heightCm'] ?? 175),
        (float) ($p['weightKg'] ?? 75),
        (int) ($d['goalKcal'] ?? 2000),
        (int) ($d['bmr'] ?? 1600),
    );
}
