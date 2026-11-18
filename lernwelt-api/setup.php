<?php
/**
 * Einmalige Übergabe des Mistral-Schlüssels nach _data/mistral.key, ohne dass er
 * je ins Repo gelangt. Geschützt durch ein Zufallsgeheimnis (256 Bit), von dem
 * hier nur der SHA-256-Hash steht. Nimmt nur an, solange noch kein Schlüssel
 * liegt; wird nach der Einrichtung wieder gelöscht.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

const SETUP_HASH = '2f987fca563b12969e16df9115555a6faa1bb93c9879e67a1f6fd9e95877679c';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Nur POST.', 405);
}
if (!hash_equals(SETUP_HASH, hash('sha256', (string) ($_SERVER['HTTP_X_SETUP_SECRET'] ?? '')))) {
    usleep(random_int(300_000, 600_000));
    fail('Nicht erlaubt.', 403);
}
$datei = DATA_DIR . '/mistral.key';
if (is_file($datei)) {
    fail('Schlüssel liegt bereits.', 409);
}
$key = (string) (jsonBody(1000)['key'] ?? '');
if (preg_match('/^[A-Za-z0-9]{20,100}$/', $key) !== 1) {
    fail('Schlüssel ungültig.');
}
ensureDir(DATA_DIR);
file_put_contents($datei, $key, LOCK_EX);
@chmod($datei, 0600);
send(['ok' => true]);
