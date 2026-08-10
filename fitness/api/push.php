<?php
/**
 * Push-Abos verwalten.
 *
 *   key          öffentlicher VAPID-Schlüssel für den Browser
 *   subscribe    Abo hinterlegen
 *   unsubscribe  Abo entfernen
 *   test         eine Nachricht sofort an die eigenen Geräte
 *
 * Abos werden über den Endpunkt entdoppelt: Derselbe Browser liefert
 * beim erneuten Abonnieren dieselbe Adresse. Ohne das sammelt sich mit
 * jedem App-Start ein weiteres Abo an, und man bekäme jede Erinnerung
 * fünfmal.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_push.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);

// Der öffentliche Schlüssel ist öffentlich - dafür braucht es kein Konto.
if (clean($body['action'] ?? '', 20) === 'key') {
    send(['ok' => true, 'key' => vapidKeys()['publicRaw']]);
}

[$uid, $user] = requireUser($body);

match (clean($body['action'] ?? '', 20)) {
    'subscribe' => abonnieren($uid, $user, $body),
    'unsubscribe' => abbestellen($uid, $user, $body),
    'test' => testen($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

function abonnieren(string $uid, array $user, array $body): never
{
    rateLimit('push', 30, 600, $uid);

    $abo = is_array($body['abo'] ?? null) ? $body['abo'] : [];
    $endpoint = (string) ($abo['endpoint'] ?? '');

    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
        fail('Ungültiges Abo.', 400, 'abo');
    }

    $neu = [
        'endpoint' => mb_substr($endpoint, 0, 500),
        'p256dh' => clean($abo['p256dh'] ?? '', 200),
        'auth' => clean($abo['auth'] ?? '', 100),
        'ua' => clean($body['geraet'] ?? '', 40),
        'addedAt' => date('c'),
        'lastOk' => null,
        'fails' => 0,
    ];

    $liste = is_array($user['push'] ?? null) ? $user['push'] : [];
    // Entdoppeln: derselbe Endpunkt ist dasselbe Gerät.
    $liste = array_values(array_filter(
        $liste,
        static fn($a) => (string) ($a['endpoint'] ?? '') !== $neu['endpoint'],
    ));
    $liste[] = $neu;

    // Höchstens fünf Geräte. Mehr hat niemand, und ohne Deckel wächst die
    // Liste bei jedem Browser-Neuaufsetzen weiter.
    $user['push'] = array_slice($liste, -5);
    saveUser($uid, $user);

    send(withFreshToken(['ok' => true, 'geraete' => count($user['push'])], $uid, $user, $body));
}

function abbestellen(string $uid, array $user, array $body): never
{
    $endpoint = (string) ($body['endpoint'] ?? '');
    $liste = is_array($user['push'] ?? null) ? $user['push'] : [];

    $user['push'] = $endpoint === ''
        ? []
        : array_values(array_filter(
            $liste,
            static fn($a) => (string) ($a['endpoint'] ?? '') !== $endpoint,
        ));

    saveUser($uid, $user);
    send(withFreshToken(['ok' => true, 'geraete' => count($user['push'])], $uid, $user, $body));
}

function testen(string $uid, array $user, array $body): never
{
    rateLimit('pushtest', 10, 600, $uid);

    [$erfolge, $user] = pushAnNutzer($user, 'aura-test');
    saveUser($uid, $user);

    send(withFreshToken([
        'ok' => true,
        'gesendet' => $erfolge,
        'geraete' => count($user['push']),
    ], $uid, $user, $body));
}
