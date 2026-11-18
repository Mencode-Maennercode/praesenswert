<?php
/**
 * Gemini-Anbindung – Muster aus fitness/_ai.php (Modellkette, Schema-JSON,
 * Denk-Einstellung mit Rückfall), ergänzt um TTS.
 *
 * Das Gratis-Kontingent gilt PRO MODELL. Läuft eines leer (429), rückt das
 * nächste nach. Sind alle leer, antwortet der Endpunkt 429 mit retryAfter –
 * der Client greift dann auf seinen lokalen Fallback zurück.
 */

declare(strict_types=1);

const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta';

/** Text: schnelle lite-Modelle zuerst (größte Kontingente). */
const KETTE_TEXT = [
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-3.5-flash',
    'gemini-3-flash-preview',
    'gemini-2.5-flash-lite',
];
/** Bilder (Handschrift, Buchseiten): stärkere Modelle zuerst. */
const KETTE_BILD = [
    'gemini-3.5-flash',
    'gemini-3-flash-preview',
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-2.5-flash',
];

/**
 * Schlüssel: GEMINI_API_KEY → _data/ai.key → Schlüssel der Grillparty
 * (bzw. Fitness) auf demselben Webspace. Verlässt den Server nie.
 */
function aiKey(): string
{
    $env = getenv('GEMINI_API_KEY');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    foreach ([DATA_DIR . '/ai.key', __DIR__ . '/../grillparty/_data/ai.key', __DIR__ . '/../fitness/_data/ai.key'] as $pfad) {
        if (is_file($pfad)) {
            $wert = trim((string) file_get_contents($pfad));
            if ($wert !== '') {
                return $wert;
            }
        }
    }
    return '';
}

function requireKey(): string
{
    $key = aiKey();
    if ($key === '') {
        fail('KI nicht eingerichtet.', 503);
    }
    return $key;
}

/** Wartezeit aus Googles RetryInfo ("37s"), sonst Standard. */
function retryAusAntwort(?array $daten, int $standard): int
{
    foreach ($daten['error']['details'] ?? [] as $d) {
        if (preg_match('/^(\d+)(?:\.\d+)?s$/', (string) ($d['retryDelay'] ?? ''), $m) === 1) {
            return (int) $m[1] + 1;
        }
    }
    return $standard;
}

/**
 * Ruft Gemini mit erzwungenem JSON-Schema. Gibt das geparste Ergebnis zurück
 * oder beendet mit 429 (alle Modelle leer) bzw. 502 (Fehler).
 */
function geminiJson(array $teile, array $schema, string $anweisung, array $kette = KETTE_TEXT, float $temperatur = 0.3): array
{
    $key = requireKey();
    $header = ['Content-Type: application/json', 'x-goog-api-key: ' . $key];
    $retry = 0;

    foreach ($kette as $m) {
        foreach (['low', null] as $denken) {
            $gen = ['responseMimeType' => 'application/json', 'responseSchema' => $schema, 'temperature' => $temperatur];
            if ($denken !== null) {
                $gen['thinkingConfig'] = ['thinkingLevel' => $denken];
            }
            $r = httpRequest('POST', GEMINI_BASE . "/models/$m:generateContent", $header, json_encode([
                'systemInstruction' => ['parts' => [['text' => $anweisung]]],
                'contents' => [['role' => 'user', 'parts' => $teile]],
                'generationConfig' => $gen,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $daten = json_decode($r['body'], true);

            if ($r['status'] >= 200 && $r['status'] < 300) {
                $text = $daten['candidates'][0]['content']['parts'][0]['text'] ?? null;
                $json = is_string($text) ? json_decode($text, true) : null;
                if (is_array($json)) {
                    header('X-Model: ' . $m);
                    return $json;
                }
                fail('KI-Antwort unbrauchbar.', 502);
            }
            $meldung = (string) ($daten['error']['message'] ?? '');
            if ($r['status'] === 400 && $denken !== null && stripos($meldung, 'thinking') !== false) {
                continue; // älteres Modell kennt thinkingLevel nicht
            }
            if (in_array($r['status'], [0, 429, 404, 500, 503, 504], true)) {
                // leer, nicht (mehr) vorhanden oder überlastet: nächstes Modell
                if ($r['status'] === 429) {
                    $retry = max($retry, retryAusAntwort($daten, 60));
                }
                break;
            }
            fail("KI-Fehler ({$r['status']}).", 502);
        }
    }
    fail('KI-Kontingent erschöpft.', 429, $retry ?: 60);
}

/* ---------------------------------------------------------------- TTS */

/**
 * Aktuelle TTS-Modelle per models.list, einmal am Tag. Flash vor Pro
 * (Pro hat kein Gratis-Kontingent), neuere Version zuerst.
 */
function ttsModelle(string $key): array
{
    $datei = DATA_DIR . '/tts-models.json';
    $cache = readJson($datei, []);
    if (($cache['until'] ?? 0) > time() && !empty($cache['models'])) {
        return $cache['models'];
    }
    $modelle = [];
    $token = '';
    do {
        $r = httpRequest('GET', GEMINI_BASE . '/models?pageSize=1000' . ($token ? '&pageToken=' . urlencode($token) : ''), ['x-goog-api-key: ' . $key], null, 10);
        $d = json_decode($r['body'], true);
        foreach ($d['models'] ?? [] as $m) {
            $name = substr((string) ($m['name'] ?? ''), 7); // "models/"
            if (str_contains($name, 'tts') && in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                $modelle[] = $name;
            }
        }
        $token = (string) ($d['nextPageToken'] ?? '');
    } while ($token !== '' && $r['status'] === 200);

    if ($modelle === []) {
        return $cache['models'] ?? ['gemini-2.5-flash-preview-tts'];
    }
    usort($modelle, function (string $a, string $b): int {
        $rang = fn (string $n) => [str_contains($n, 'pro') ? 1 : 0, -(float) (preg_match('/gemini-(\d+(?:\.\d+)?)/', $n, $m) ? $m[1] : 0), str_contains($n, 'preview') ? 1 : 0, $n];
        return $rang($a) <=> $rang($b);
    });
    writeJson($datei, ['until' => time() + 86400, 'models' => $modelle]);
    return $modelle;
}

/** PCM s16le mono → WAV. */
function pcmZuWav(string $pcm, int $rate): string
{
    return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVE'
        . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
        . 'data' . pack('V', strlen($pcm)) . $pcm;
}

/** Spricht $prompt mit $stimme. Gibt WAV-Bytes zurück oder beendet mit 429/502. */
function geminiTts(string $prompt, string $stimme): string
{
    $key = requireKey();
    $header = ['Content-Type: application/json', 'x-goog-api-key: ' . $key];
    $retry = 0;

    foreach (array_slice(ttsModelle($key), 0, 4) as $m) {
        $r = httpRequest('POST', GEMINI_BASE . "/models/$m:generateContent", $header, json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $stimme]]],
            ],
        ], JSON_UNESCAPED_UNICODE), 40);
        $daten = json_decode($r['body'], true);

        if ($r['status'] === 200) {
            $inline = $daten['candidates'][0]['content']['parts'][0]['inlineData'] ?? null;
            $pcm = is_array($inline) ? base64_decode((string) ($inline['data'] ?? ''), true) : false;
            if ($pcm === false || $pcm === '') {
                fail('KI-Antwort unbrauchbar.', 502);
            }
            $rate = preg_match('/rate=(\d+)/', (string) ($inline['mimeType'] ?? ''), $t) === 1 ? (int) $t[1] : 24000;
            header('X-Model: ' . $m);
            return pcmZuWav($pcm, $rate);
        }
        if (in_array($r['status'], [0, 429, 404, 500, 503, 504], true)) {
            if ($r['status'] === 429) {
                $retry = max($retry, retryAusAntwort($daten, 60));
            }
            continue;
        }
        fail("KI-Fehler ({$r['status']}).", 502);
    }
    fail('KI-Kontingent erschöpft.', 429, $retry ?: 60);
}
