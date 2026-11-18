<?php
/**
 * KI-Anbindung: Mistral AI (Paris). Muster aus fitness/_ai.php: Modellkette,
 * erzwungenes JSON-Schema, ehrliche Fehlercodes.
 *
 * Warum nicht Gemini: Die Gemini-/Vertex-Bedingungen verbieten den Einsatz in
 * Apps, die sich an unter 18-Jährige richten. Mistral erlaubt das mit
 * Einwilligung der Eltern und ohne personenbezogene Daten von Kindern in den
 * Anfragen (Commercial Terms §2.2(c)). Training ist im Konto abgeschaltet.
 *
 * Läuft ein Modell auf 429, rückt das nächste nach. Sind alle gebremst,
 * antwortet der Endpunkt 429 mit retryAfter – der Client nutzt dann seinen
 * lokalen Fallback.
 */

declare(strict_types=1);

const MISTRAL_BASE = 'https://api.mistral.ai/v1';

/*
 * Gemessen 18.11.2026 im Gratis-Tarif: mistral-small/-medium haben dort ein
 * Limit von 0 Anfragen/Minute (antworten sofort 429), die Ministral-Modelle
 * je 2/Minute. Die Ketten stehen trotzdem mit small/medium vorn: Nach einem
 * Wechsel in den bezahlten Tarif werden sie ohne Codeänderung genutzt.
 */
/** Text. */
const KETTE_TEXT = ['mistral-small-latest', 'ministral-14b-latest', 'ministral-8b-latest', 'ministral-3b-latest'];
/** Bilder (Handschrift, Buchseiten). */
const KETTE_BILD = ['mistral-medium-latest', 'mistral-small-latest', 'ministral-14b-latest', 'ministral-8b-latest'];
/** TTS: neueres Modell zuerst. */
const KETTE_TTS = ['voxtral-mini-tts-3', 'voxtral-mini-tts-latest'];

/** Schlüssel: MISTRAL_API_KEY → _data/mistral.key. Verlässt den Server nie. */
function aiKey(): string
{
    $env = getenv('MISTRAL_API_KEY');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    $datei = DATA_DIR . '/mistral.key';
    return is_file($datei) ? trim((string) file_get_contents($datei)) : '';
}

function requireKey(): string
{
    $key = aiKey();
    if ($key === '') {
        fail('KI nicht eingerichtet.', 503);
    }
    return $key;
}

function retryAus(array $r, int $standard): int
{
    $h = $r['headers']['retry-after'] ?? '';
    return ctype_digit($h) ? (int) $h + 1 : $standard;
}

/** Bildteil für eine Nachricht. */
function bildTeil(string $base64, string $mime): array
{
    return ['type' => 'image_url', 'image_url' => "data:$mime;base64,$base64"];
}

function textTeil(string $text): array
{
    return ['type' => 'text', 'text' => $text];
}

/**
 * Chat mit erzwungenem JSON-Schema (JSON Schema, strict). Gibt das geparste
 * Ergebnis zurück oder beendet mit 429 (alle gebremst) bzw. 502.
 */
function aiJson(array $teile, array $schema, string $anweisung, array $kette = KETTE_TEXT, float $temperatur = 0.3): array
{
    $header = ['Content-Type: application/json', 'Authorization: Bearer ' . requireKey()];
    $retry = 0;
    $status = 0;

    foreach ($kette as $m) {
        $r = httpRequest('POST', MISTRAL_BASE . '/chat/completions', $header, json_encode([
            'model' => $m,
            'temperature' => $temperatur,
            'messages' => [
                ['role' => 'system', 'content' => $anweisung],
                ['role' => 'user', 'content' => $teile],
            ],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'antwort', 'schema' => $schema, 'strict' => true]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 40);
        $status = $r['status'];

        if ($status === 200) {
            $d = json_decode($r['body'], true);
            $text = $d['choices'][0]['message']['content'] ?? null;
            $json = is_string($text) ? json_decode($text, true) : null;
            if (is_array($json)) {
                header('X-Model: ' . $m);
                return $json;
            }
            continue; // unbrauchbare Antwort: nächstes Modell versuchen
        }
        if ($status === 429) {
            $retry = max($retry, retryAus($r, 30));
            continue;
        }
        if (in_array($status, [0, 404, 500, 502, 503, 504], true)) {
            continue;
        }
        fail("KI-Fehler ($status).", 502);
    }
    if ($retry > 0) {
        fail('KI-Kontingent erschöpft.', 429, $retry);
    }
    fail("KI nicht erreichbar ($status).", 502);
}

/* ---------------------------------------------------------------- TTS */

/**
 * Spricht $text mit der Stimme $stimme (Mistral-Voice-ID).
 * @return array{0:string,1:string} [Audio-Bytes, MIME]
 */
function aiTts(string $text, string $stimme): array
{
    $header = ['Content-Type: application/json', 'Authorization: Bearer ' . requireKey()];
    $retry = 0;
    $status = 0;

    foreach (KETTE_TTS as $m) {
        $r = httpRequest('POST', MISTRAL_BASE . '/audio/speech', $header, json_encode([
            'model' => $m,
            'voice_id' => $stimme,
            'input' => $text,
            'response_format' => 'mp3',
        ], JSON_UNESCAPED_UNICODE), 40);
        $status = $r['status'];

        if ($status === 200) {
            $typ = strtolower($r['headers']['content-type'] ?? '');
            if (str_starts_with($typ, 'audio/')) {
                header('X-Model: ' . $m);
                return [$r['body'], explode(';', $typ)[0]];
            }
            $d = json_decode($r['body'], true);
            $b64 = $d['audio_data'] ?? $d['audio'] ?? $d['data'] ?? null;
            $audio = is_string($b64) ? base64_decode($b64, true) : false;
            if ($audio !== false && $audio !== '') {
                header('X-Model: ' . $m);
                return [$audio, str_starts_with($audio, 'RIFF') ? 'audio/wav' : 'audio/mpeg'];
            }
            continue;
        }
        if ($status === 429) {
            $retry = max($retry, retryAus($r, 30));
            continue;
        }
        if (in_array($status, [0, 400, 404, 500, 502, 503, 504], true)) {
            continue; // 400: Modell kennt die Stimme nicht → nächstes
        }
        fail("KI-Fehler ($status).", 502);
    }
    if ($retry > 0) {
        fail('KI-Kontingent erschöpft.', 429, $retry);
    }
    fail("Sprachausgabe nicht verfügbar ($status).", 502);
}
