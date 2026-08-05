<?php
/** Ein Gast trägt sich mit einer Sache ein. */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody();
checkHoneypot($body);
rateLimit('join', 12, 600);

$id = validEventId($body['eventId'] ?? '');
if ($id === '') {
    fail('Kein gültiger Party-Link.');
}

$name  = clean($body['name'] ?? '', MAX_NAME);
$email = cleanEmail($body['email'] ?? '');
$item  = clean($body['item'] ?? '', MAX_ITEM);

if ($name === '') {
    fail('Bitte trag deinen Namen ein.');
}
if ($email === '') {
    fail('Bitte gib eine gültige E-Mail-Adresse an.');
}
if ($item === '') {
    fail('Was darf auf keiner Grillfeier fehlen? Ein Wort reicht.');
}

$participantId = 'p_' . bin2hex(random_bytes(5));

$event = withEventLock($id, function (array $event) use ($name, $email, $item, $participantId) {
    if ($event['status'] !== 'open') {
        fail('Die Auslosung ist schon gelaufen - jetzt kann sich niemand mehr eintragen.', 409);
    }
    if (count($event['participants']) >= MAX_GUESTS) {
        fail('Diese Party ist voll.', 409);
    }

    // Gleiche E-Mail = gleicher Mensch. Eintrag wird aktualisiert statt verdoppelt.
    foreach ($event['participants'] as $i => $p) {
        if (strcasecmp($p['email'], $email) === 0) {
            $event['participants'][$i]['name'] = $name;
            $event['participants'][$i]['item'] = $item;
            // createdAt bleibt stehen (wann jemand dazukam), updatedAt hält
            // fest, wann der jetzt sichtbare Eintrag entstanden ist.
            $event['participants'][$i]['updatedAt'] = date('c');
            return $event;
        }
    }

    $event['participants'][] = [
        'id' => $participantId,
        'name' => $name,
        'email' => $email,
        'item' => $item,
        'token' => bin2hex(random_bytes(16)), // 128 Bit - nicht erratbar
        'assignedItem' => null,
        'wheelItems' => null,
        'spun' => false,
        'createdAt' => date('c'),
    ];
    return $event;
});

// Die eigene ID zurückgeben, damit der Browser den eigenen Eintrag hervorheben kann.
$own = $participantId;
foreach ($event['participants'] as $p) {
    if (strcasecmp($p['email'], $email) === 0) {
        $own = $p['id'];
        break;
    }
}

send(['ok' => true, 'participantId' => $own]);
