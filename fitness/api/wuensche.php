<?php
/**
 * Wünsche an die App.
 *
 *   liste   alle Wünsche
 *   neu     einen eintragen
 *   erledigt   abhaken (nur Eigentümer)
 *   weg     löschen (nur Eigentümer)
 *
 * Bewusst grob gebaut: Alle sehen alles, jeder darf schreiben, aufgeräumt
 * wird von Hand. Die App nutzt ein kleiner privater Kreis - ein
 * Abstimmungssystem mit Zuständen und Zuweisungen wäre hier mehr Aufwand
 * als der Zweck hergibt.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

const WUNSCH_FILE = DATA_DIR . '/wuensche.json';
const MAX_WUENSCHE = 300;

$body = jsonBody(8 * 1024);
[$uid, $user] = requireUser($body);

match (clean($body['action'] ?? '', 20)) {
    'liste' => liste($uid, $user, $body),
    'neu' => neu($uid, $user, $body),
    'erledigt' => erledigt($uid, $user, $body),
    'weg' => weg($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

function alle(): array
{
    $d = readJson(WUNSCH_FILE, ['items' => []]);
    return is_array($d['items'] ?? null) ? $d['items'] : [];
}

function liste(string $uid, array $user, array $body): never
{
    $items = alle();
    // Offene zuerst, innerhalb dessen das Neueste oben.
    usort($items, static fn($a, $b) => [$a['erledigt'] ?? false, $b['at'] ?? ''] <=> [$b['erledigt'] ?? false, $a['at'] ?? '']);

    send(withFreshToken([
        'ok' => true,
        'items' => $items,
        'owner' => (bool) ($user['owner'] ?? false),
        'ich' => $uid,
    ], $uid, $user, $body));
}

function neu(string $uid, array $user, array $body): never
{
    rateLimit('wunsch', 20, 3600, $uid);

    $text = clean($body['text'] ?? '', 300);
    if (mb_strlen($text) < 3) {
        fail('Etwas mehr bitte.', 400, 'kurz');
    }

    withLock('wuensche', static function () use ($uid, $user, $text): void {
        $items = alle();
        if (count($items) >= MAX_WUENSCHE) {
            fail('Die Liste ist voll.', 409, 'voll');
        }
        array_unshift($items, [
            'id' => 'w_' . bin2hex(random_bytes(6)),
            'text' => $text,
            'von' => (string) ($user['name'] ?? ''),
            'vonId' => $uid,
            'at' => date('c'),
            'erledigt' => false,
        ]);
        writeJson(WUNSCH_FILE, ['items' => $items]);
    });

    send(withFreshToken(['ok' => true, 'items' => alle()], $uid, $user, $body));
}

function erledigt(string $uid, array $user, array $body): never
{
    if (($user['owner'] ?? false) !== true) {
        fail('Nicht erlaubt.', 403, 'verboten');
    }
    $id = clean($body['id'] ?? '', 40);
    $wert = (bool) ($body['wert'] ?? true);

    withLock('wuensche', static function () use ($id, $wert): void {
        $items = alle();
        foreach ($items as $i => $w) {
            if (($w['id'] ?? '') === $id) {
                $items[$i]['erledigt'] = $wert;
                break;
            }
        }
        writeJson(WUNSCH_FILE, ['items' => $items]);
    });

    send(withFreshToken(['ok' => true, 'items' => alle()], $uid, $user, $body));
}

function weg(string $uid, array $user, array $body): never
{
    $id = clean($body['id'] ?? '', 40);
    $istOwner = ($user['owner'] ?? false) === true;

    withLock('wuensche', static function () use ($id, $uid, $istOwner): void {
        $items = alle();
        $neu = [];
        foreach ($items as $w) {
            // Der Eigentümer räumt auf, alle anderen dürfen nur ihr eigenes
            // wieder zurücknehmen.
            if (($w['id'] ?? '') === $id && ($istOwner || ($w['vonId'] ?? '') === $uid)) {
                continue;
            }
            $neu[] = $w;
        }
        writeJson(WUNSCH_FILE, ['items' => $neu]);
    });

    send(withFreshToken(['ok' => true, 'items' => alle()], $uid, $user, $body));
}
