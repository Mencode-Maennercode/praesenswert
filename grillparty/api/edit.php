<?php
/**
 * Ein Gast korrigiert seinen eigenen Eintrag.
 *
 * Ausweis ist die E-Mail-Adresse, mit der er sich eingetragen hat - dieselbe,
 * an die später sein Rad-Link geht. Groß- und Kleinschreibung spielt keine
 * Rolle; "Marc@Beispiel.de" und "marc@beispiel.de" sind derselbe Mensch.
 *
 * Zwei Schritte, weil die Adresse nur einmal über die Leitung gehen soll:
 *   verify - Adresse prüfen, aktuelle Werte und einen kurzlebigen Ausweis geben
 *   save   - mit diesem Ausweis Name und Sache schreiben
 *
 * Die Adresse selbst wird nie zurückgegeben. Wer den falschen Namen rät,
 * erfährt hier also nichts, was er nicht ohnehin auf der Liste sieht - und
 * das Rate-Limit macht Durchprobieren zusätzlich unattraktiv.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody();

$id = validEventId($body['eventId'] ?? '');
if ($id === '') {
    fail('Kein gültiger Party-Link.');
}

$pid = clean($body['participantId'] ?? '', 40);
if ($pid === '') {
    fail('Kein gültiger Eintrag.');
}

$action = is_string($body['action'] ?? null) ? $body['action'] : 'verify';

if ($action === 'verify') {
    // Großzügig genug für vertippte Adressen, eng genug gegen das Durchprobieren
    // fremder Adressen.
    rateLimit('edit', 15, 900);

    $email = cleanEmail($body['email'] ?? '');
    if ($email === '') {
        fail('Bitte gib deine E-Mail-Adresse an.');
    }

    $event = loadEvent($id);
    $participant = findParticipant($event, $pid);

    if (strcasecmp($participant['email'] ?? '', $email) !== 0) {
        usleep(400_000); // wie beim Admin-Passwort: bremst das Durchprobieren
        fail('Diese E-Mail-Adresse gehört nicht zu diesem Eintrag.', 401);
    }

    requireOpen($event);

    send([
        'ok' => true,
        'token' => makeEditToken($id, $participant['id']),
        'name' => $participant['name'] ?? '',
        'item' => $participant['item'] ?? '',
    ]);
}

if ($action !== 'save') {
    fail('Unbekannte Aktion.');
}

/* ------------------------------------------------------------------ Speichern */

$token = is_string($body['token'] ?? null) ? $body['token'] : '';
if (!verifyEditToken($token, $id, $pid)) {
    fail('Das hat zu lange gedauert. Klick noch einmal auf den Stift.', 401);
}

$name = clean($body['name'] ?? '', MAX_NAME);
$item = clean($body['item'] ?? '', MAX_ITEM);

if ($name === '') {
    fail('Ohne Namen geht es nicht.');
}
if ($item === '') {
    fail('Was darf auf keiner Grillfeier fehlen? Ein Wort reicht.');
}

withEventLock($id, function (array $e) use ($pid, $name, $item) {
    $participant = findParticipant($e, $pid);
    requireOpen($e);

    foreach ($e['participants'] as $i => $p) {
        if ($p['id'] !== $participant['id']) {
            continue;
        }
        $e['participants'][$i]['name'] = $name;
        $e['participants'][$i]['item'] = $item;

        // createdAt bleibt stehen - das ist der Moment, in dem jemand dazukam.
        $e['participants'][$i]['updatedAt'] = date('c');

        // Der Gast war es selbst. Ein früheres "durch Admin geändert"
        // verschwindet damit aus der Liste, und das ist auch richtig so.
        $e['participants'][$i]['editedBy'] = 'self';
        break;
    }
    return $e;
});

send(['ok' => true]);

/* ------------------------------------------------------------------ Helfer */

function findParticipant(array $event, string $participantId): array
{
    foreach ($event['participants'] as $p) {
        if (($p['id'] ?? '') === $participantId) {
            return $p;
        }
    }
    fail('Diesen Eintrag gibt es nicht mehr.', 404);
}

/**
 * Nach der Auslosung steckt jede Sache in fremden Rädern und in schon
 * verschickten Mails. Ab da darf nur noch der Gastgeber eingreifen.
 */
function requireOpen(array $event): void
{
    if (($event['status'] ?? '') !== 'open') {
        fail('Die Auslosung ist schon gelaufen - jetzt lässt sich nichts mehr ändern.', 409);
    }
}
