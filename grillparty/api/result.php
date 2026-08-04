<?php
/**
 * Liefert einer Person ihr zugelostes Ding samt Radfeldern.
 *
 * Das Ergebnis steht seit der Auslosung fest - der Client animiert es nur noch.
 * Zugang ausschließlich über den 128-Bit-Token aus dem persönlichen Link.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('GET');

rateLimit('result', 60, 600);

$id = validEventId($_GET['e'] ?? '');
$token = is_string($_GET['t'] ?? null) ? trim($_GET['t']) : '';

if ($id === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    fail('Dieser Link stimmt nicht.', 404);
}

$event = loadEvent($id);

if ($event['status'] !== 'drawn') {
    fail('Die Auslosung läuft noch. Schau gleich nochmal rein.', 409);
}

$me = null;
foreach ($event['participants'] as $p) {
    // hash_equals: konstante Laufzeit, damit Tokens nicht Zeichen für Zeichen erraten werden können.
    if (hash_equals((string) $p['token'], $token)) {
        $me = $p;
        break;
    }
}

if ($me === null || empty($me['assignedItem'])) {
    fail('Dieser Link stimmt nicht.', 404);
}

$wheel = is_array($me['wheelItems'] ?? null) && count($me['wheelItems']) > 0
    ? array_values($me['wheelItems'])
    : [$me['assignedItem']];

$targetIndex = array_search($me['assignedItem'], $wheel, true);
if ($targetIndex === false) {
    // Sollte nicht vorkommen; lieber reparieren als eine kaputte Seite ausliefern.
    $wheel[] = $me['assignedItem'];
    $targetIndex = count($wheel) - 1;
}

send([
    'eventId' => $event['id'],
    'name' => $me['name'],
    'assignedItem' => $me['assignedItem'],
    'wheelItems' => $wheel,
    'targetIndex' => (int) $targetIndex,
    'spun' => (bool) ($me['spun'] ?? false),
    'meta' => [
        'title' => $event['meta']['title'],
        'host' => $event['meta']['host'],
        'datetime' => $event['meta']['datetime'],
        'location' => $event['meta']['location'],
    ],
]);
