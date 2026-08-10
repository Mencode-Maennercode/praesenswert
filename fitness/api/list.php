<?php
/**
 * Die Einkaufsliste.
 *
 *   erzeugen   aus dem Wochenplan bauen und einen Teil-Link vergeben
 *   holen      Liste zum Token (OHNE Konto - das ist der geteilte Zugang)
 *   haken      Posten abhaken oder zurücknehmen (ebenfalls ohne Konto)
 *   dazu       einen eigenen Posten ergänzen
 *   loeschen   Link entwerten
 *
 * Der geteilte Link zeigt ausschliesslich Einkaufsposten. Keine Kalorien,
 * kein Gewicht, kein Name, kein Zugang zum Konto - wer ihn hat, sieht
 * eine Einkaufsliste und sonst nichts.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';
require __DIR__ . '/_plan.php';

cors();
requireMethod('POST');

$body = jsonBody(64 * 1024);
$action = clean($body['action'] ?? '', 20);

// Diese drei brauchen kein Konto - sie sind der geteilte Zugang.
if (in_array($action, ['holen', 'haken', 'dazu'], true)) {
    match ($action) {
        'holen' => oeffentlichHolen($body),
        'haken' => haken($body),
        'dazu' => dazu($body),
    };
}

[$uid, $user] = requireUser($body);

match ($action) {
    'erzeugen' => erzeugen($uid, $user, $body),
    'loeschen' => loeschen($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

/* ---------------------------------------------------------------- Ablage */

function listePfad(string $token): string
{
    return LISTS_DIR . '/' . $token . '.json';
}

function listeLaden(string $token): array
{
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        fail('Liste nicht gefunden.', 404, 'weg');
    }
    $l = readJson(listePfad($token), []);
    if ($l === []) {
        fail('Liste nicht gefunden.', 404, 'weg');
    }
    // Vierzehn Tage - danach ist eine Einkaufsliste ohnehin wertlos, und
    // der Link soll nicht ewig gelten.
    if (strtotime((string) ($l['erzeugtAm'] ?? '')) < time() - 14 * 86400) {
        @unlink(listePfad($token));
        fail('Liste abgelaufen.', 410, 'alt');
    }
    return $l;
}

/* -------------------------------------------------------------- Erzeugen */

function erzeugen(string $uid, array $user, array $body): never
{
    rateLimit('liste', 20, 3600, $uid);

    $woche = clean($body['woche'] ?? '', 10) ?: date('o-\WW');
    $plan = readJson(PLANS_DIR . '/' . $uid . '/' . $woche . '.json', []);
    if ($plan === []) {
        fail('Kein Plan da.', 404, 'weg');
    }

    $prefs = is_array($user['planPrefs'] ?? null) ? $user['planPrefs'] : [];
    $erw = (int) ($prefs['erwachsene'] ?? 2);
    $kinder = (int) ($prefs['kinder'] ?? 0);
    // Ein Kind zählt als halbe Portion. Die Mengen skalieren mit, die
    // Kalorien im Tagebuch NICHT - dort steht nur die eigene Portion.
    $esser = max(1.0, $erw + $kinder * 0.5);

    $rezepte = [];
    $gesehen = [];
    foreach ($plan['gerichte'] as $g) {
        // Reste-Tage kaufen nicht doppelt ein.
        $key = $g['rezept'] . '|' . ($g['reste'] ? 'reste' : $g['tag']);
        if (($g['reste'] ?? false) && isset($gesehen[$g['rezept']])) {
            continue;
        }
        $gesehen[$g['rezept']] = true;

        $r = rezeptLaden((string) $g['rezept']);
        if ($r === null) {
            continue;
        }
        $rezepte[] = ['zutaten' => $r['zutaten'] ?? [], 'faktor' => $esser, '_k' => $key];
    }

    $vorrat = is_array($user['vorrat'] ?? null) ? $user['vorrat'] : [];
    $posten = einkaufsliste($rezepte, $vorrat);

    // Bestehenden Link derselben Woche weiterverwenden - sonst hat die
    // Partnerin plötzlich eine tote Adresse im Chat.
    $token = (string) ($plan['listToken'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(16));
        $plan['listToken'] = $token;
        writeJson(PLANS_DIR . '/' . $uid . '/' . $woche . '.json', $plan);
    }

    $alt = readJson(listePfad($token), []);
    $abgehakt = [];
    foreach ($alt['posten'] ?? [] as $p) {
        if ($p['ab'] ?? false) {
            $abgehakt[$p['id']] = true;
        }
    }
    // Schon Abgehaktes bleibt abgehakt, auch wenn die Liste neu gebaut wird.
    foreach ($posten as $i => $p) {
        $posten[$i]['ab'] = isset($abgehakt[$p['id']]);
    }
    foreach ($alt['posten'] ?? [] as $p) {
        if (($p['eigen'] ?? false) === true) {
            $posten[] = $p;
        }
    }

    writeJson(listePfad($token), [
        'token' => $token,
        'woche' => $woche,
        'erzeugtAm' => date('c'),
        'posten' => $posten,
    ]);

    send(withFreshToken([
        'ok' => true,
        'token' => $token,
        'gruppen' => nachGruppen($posten),
        'anzahl' => count($posten),
    ], $uid, $user, $body));
}

function loeschen(string $uid, array $user, array $body): never
{
    $woche = clean($body['woche'] ?? '', 10) ?: date('o-\WW');
    $datei = PLANS_DIR . '/' . $uid . '/' . $woche . '.json';
    $plan = readJson($datei, []);

    $token = (string) ($plan['listToken'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $token) === 1) {
        @unlink(listePfad($token));
        unset($plan['listToken']);
        writeJson($datei, $plan);
    }
    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

/* ------------------------------------------------------- Geteilter Zugang */

function oeffentlichHolen(array $body): never
{
    rateLimit('listeoeffentlich', 200, 600);
    $l = listeLaden(clean($body['token'] ?? '', 40));

    send([
        'ok' => true,
        'gruppen' => nachGruppen($l['posten']),
        'anzahl' => count($l['posten']),
        'offen' => count(array_filter($l['posten'], static fn($p) => !($p['ab'] ?? false))),
        'stand' => $l['erzeugtAm'],
    ]);
}

function haken(array $body): never
{
    rateLimit('listeoeffentlich', 300, 600);

    $token = clean($body['token'] ?? '', 40);
    $id = clean($body['id'] ?? '', 20);
    $ab = (bool) ($body['ab'] ?? true);

    $l = withLock('liste-' . $token, static function () use ($token, $id, $ab): array {
        $l = listeLaden($token);
        foreach ($l['posten'] as $i => $p) {
            if ($p['id'] === $id) {
                $l['posten'][$i]['ab'] = $ab;
                break;
            }
        }
        writeJson(listePfad($token), $l);
        return $l;
    });

    send([
        'ok' => true,
        'gruppen' => nachGruppen($l['posten']),
        'offen' => count(array_filter($l['posten'], static fn($p) => !($p['ab'] ?? false))),
    ]);
}

/**
 * Etwas dazuschreiben.
 *
 * Wer mit der Liste im Laden steht, fällt ein, dass Klopapier fehlt.
 * Ohne diesen Weg schreibt er es auf die Hand - und die Liste ist ab
 * dann nur noch die halbe Wahrheit.
 */
function dazu(array $body): never
{
    rateLimit('listeoeffentlich', 100, 600);

    $token = clean($body['token'] ?? '', 40);
    $name = clean($body['name'] ?? '', 60);
    if ($name === '') {
        fail('Was denn?', 400, 'leer');
    }

    $gruppe = (string) ($body['gruppe'] ?? 'sonstiges');
    if (!isset(warengruppen()[$gruppe])) {
        $gruppe = 'sonstiges';
    }

    $l = withLock('liste-' . $token, static function () use ($token, $name, $gruppe): array {
        $l = listeLaden($token);
        if (count($l['posten']) >= 200) {
            fail('Liste ist voll.', 409, 'voll');
        }
        $l['posten'][] = [
            'id' => substr(md5($name . microtime()), 0, 10),
            'name' => $name,
            'menge' => 0,
            'einheit' => '',
            'text' => '',
            'gruppe' => $gruppe,
            'ab' => false,
            'eigen' => true,
        ];
        writeJson(listePfad($token), $l);
        return $l;
    });

    send(['ok' => true, 'gruppen' => nachGruppen($l['posten'])]);
}
