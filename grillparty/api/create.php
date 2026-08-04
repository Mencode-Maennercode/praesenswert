<?php
/** Legt eine neue Party an und liefert Gast-Link plus Admin-Code zurück. */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody();
checkHoneypot($body);
rateLimit('create', 5, 3600);

$title     = clean($body['title'] ?? '', MAX_TEXT);
$host      = clean($body['host'] ?? '', MAX_NAME);
$datetime  = clean($body['datetime'] ?? '', 32);
$adminCode = is_string($body['adminCode'] ?? null) ? trim($body['adminCode']) : '';

if ($title === '') {
    fail('Gib deiner Party einen Namen.');
}
if (mb_strlen($adminCode) < 3) {
    fail('Der Admin-Code braucht mindestens 3 Zeichen.');
}
if (mb_strlen($adminCode) > 64) {
    fail('Der Admin-Code ist zu lang.');
}

ensureDirs();

// Lesbare ID aus dem Titel, bei Kollision mit Zufallssuffix.
$base = slugify($title);
if (mb_strlen($base) < 2) {
    $base = 'party';
}
// Namen echter Verzeichnisse sind tabu - sonst zeigt der Kurzlink
// /grillparty/<slug> auf den Ordner statt auf die Party.
$reserved = ['api', 'rad', 'data', 'next', 'assets', 'static', 'party', 'admin', 'index'];
if (in_array($base, $reserved, true)) {
    $base .= '-fest';
}

$id = $base;
for ($attempt = 0; eventExists($id); $attempt++) {
    if ($attempt > 20) {
        fail('Konnte keine freie Adresse finden. Versuch einen anderen Titel.', 409);
    }
    $id = $base . '-' . bin2hex(random_bytes(2));
}
if (validEventId($id) === '') {
    $id = 'party-' . bin2hex(random_bytes(3));
}

saveEvent([
    'id' => $id,
    'createdAt' => date('c'),
    'adminHash' => password_hash($adminCode, PASSWORD_DEFAULT),
    'meta' => [
        'title' => $title,
        'host' => $host,
        'datetime' => $datetime,
        'hostProvides' => '',
        'location' => '',
    ],
    'suggestions' => defaultSuggestions(),
    'participants' => [],
    'status' => 'open',
    'drawnAt' => null,
]);

send([
    'id' => $id,
    'adminCode' => $adminCode,
    'url' => baseUrl() . appBase() . '/p/?e=' . rawurlencode($id),
], 201);
