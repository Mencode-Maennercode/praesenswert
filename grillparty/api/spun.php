<?php
/** Merkt sich, dass jemand sein Rad gedreht hat - beim erneuten Öffnen
 *  erscheint das Ergebnis dann direkt statt eines zweiten Drehs. */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody();
$id = validEventId($body['eventId'] ?? '');
$token = is_string($body['token'] ?? null) ? trim($body['token']) : '';

if ($id === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    fail('Dieser Link stimmt nicht.', 404);
}

withEventLock($id, function (array $event) use ($token) {
    foreach ($event['participants'] as $i => $p) {
        if (hash_equals((string) $p['token'], $token)) {
            $event['participants'][$i]['spun'] = true;
            return $event;
        }
    }
    fail('Dieser Link stimmt nicht.', 404);
});

send(['ok' => true]);
