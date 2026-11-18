<?php
/**
 * tts.php – Gemini-TTS, deutsche kindliche Stimme. Liefert audio/wav (kein JSON).
 * Jeder Satz wird nur einmal bezahlt: Audio-Cache als Dateien in _data/tts/.
 * Bei 429/5xx nutzt der Client speechSynthesis.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

/** Vorgefertigte Gemini-Stimmen; Standard: Leda (jugendlich). */
const STIMMEN = [
    'Zephyr', 'Puck', 'Charon', 'Kore', 'Fenrir', 'Leda', 'Orus', 'Aoede', 'Callirrhoe', 'Autonoe',
    'Enceladus', 'Iapetus', 'Umbriel', 'Algieba', 'Despina', 'Erinome', 'Algenib', 'Rasalgethi',
    'Laomedeia', 'Achernar', 'Alnilam', 'Schedar', 'Gacrux', 'Pulcherrima', 'Achird',
    'Zubenelgenubi', 'Vindemiatrix', 'Sadachbia', 'Sadaltager', 'Sulafat',
];
const STIL = [
    'erklaeren' => 'ruhig, langsam und deutlich, geduldig erklärend',
    'jubeln' => 'fröhlich, begeistert und lebhaft',
    'ermutigen' => 'warm, freundlich und ermutigend',
];

cors();
$uid = requireUser();
$b = jsonBody(8_000);

$text = clean($b['text'] ?? '', 400);
if ($text === '') {
    fail('text fehlt.');
}
$stimme = 'Leda';
foreach (STIMMEN as $s) {
    if (strcasecmp($s, (string) ($b['voice'] ?? '')) === 0) {
        $stimme = $s;
    }
}
$stil = is_string($b['style'] ?? null) && isset(STIL[$b['style']]) ? $b['style'] : 'erklaeren';

$hash = hash('sha256', "v1|$stimme|$stil|$text");
$datei = DATA_DIR . '/tts/' . substr($hash, 0, 2) . "/$hash.wav";

if (is_file($datei)) {
    header('X-Cache: HIT');
    $wav = (string) file_get_contents($datei);
} else {
    budgetBuchen($uid, 'tts');
    $prompt = 'Lies den folgenden deutschen Text für ein Grundschulkind vor – mit heller, kindlicher, '
        . 'freundlicher Stimme, ' . STIL[$stil] . ", in klarem Hochdeutsch:\n" . $text;
    $wav = geminiTts($prompt, $stimme);
    ensureDir(dirname($datei));
    file_put_contents($datei, $wav, LOCK_EX);
    header('X-Cache: MISS');
}

header('Content-Type: audio/wav');
header('Content-Length: ' . strlen($wav));
header('Cache-Control: private, max-age=31536000, immutable');
echo $wav;
