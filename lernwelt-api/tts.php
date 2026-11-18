<?php
/**
 * tts.php – Sprachausgabe über Mistral (Voxtral TTS). Liefert Audio (kein JSON).
 * Jeder Satz wird nur einmal bezahlt: Audio-Cache als Dateien in _data/tts/.
 * Bei 429/5xx nutzt der Client speechSynthesis.
 *
 * Stimme: `voice` = Mistral-Voice-ID (UUID); sonst die deutsche Standardstimme
 * „Heidi“. Eine echte Kinderstimme bietet Mistral nicht an. `style` ändert
 * die Ausgabe nicht (Voxtral kennt keine Stilanweisung).
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

const STIMME_STANDARD = '01a0f318-4ae8-7446-a3e4-45aa092940b2'; // Heidi, de, weiblich

cors();
$uid = requireUser();
$b = jsonBody(8_000);

$text = clean($b['text'] ?? '', 400);
if ($text === '') {
    fail('text fehlt.');
}
$voice = (string) ($b['voice'] ?? '');
$stimme = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $voice) === 1 ? $voice : STIMME_STANDARD;

$hash = hash('sha256', "mistral|$stimme|$text");
$basis = DATA_DIR . '/tts/' . substr($hash, 0, 2) . "/$hash";

$treffer = glob("$basis.*") ?: [];
if ($treffer !== []) {
    header('X-Cache: HIT');
    $mime = str_ends_with($treffer[0], '.wav') ? 'audio/wav' : 'audio/mpeg';
    $audio = (string) file_get_contents($treffer[0]);
} else {
    budgetBuchen($uid, 'tts');
    [$audio, $mime] = aiTts($text, $stimme);
    ensureDir(dirname($basis));
    file_put_contents($basis . ($mime === 'audio/wav' ? '.wav' : '.mp3'), $audio, LOCK_EX);
    header('X-Cache: MISS');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($audio));
header('Cache-Control: private, max-age=31536000, immutable');
echo $audio;
