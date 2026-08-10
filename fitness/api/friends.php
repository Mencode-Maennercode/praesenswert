<?php
/**
 * Freunde.
 *
 *   suche        Nutzer per Name finden
 *   anfrage      Freundschaft anfragen
 *   annehmen     Anfrage annehmen
 *   entfernen    Anfrage ablehnen oder Freundschaft beenden
 *   liste        Freunde von heute samt Anfragen
 *   woche        Bestenliste der laufenden Woche
 *   kudos        Reaktion auf den heutigen Tag eines Freundes
 *
 * Der wichtigste Punkt steht nicht im Code, sondern in dem, was NICHT
 * gesendet wird: Freunde sehen ausschliesslich Prozentwerte, nie
 * Kalorien. Eine 58-kg-Frau mit einem Grundumsatz von 1300 und ein
 * 95-kg-Mann mit 2000 sind in absoluten Zahlen unvergleichbar - und
 * jeder Vergleich, der so tut, ist falsch. Am eigenen Bedarf gemessen
 * stehen beide auf derselben Skala.
 *
 * Alle Beziehungen liegen in EINER Datei mit EINER Sperre. Zwei
 * Profildateien unter zwei Sperren zu ändern ist der klassische Weg in
 * eine Verklemmung - und eine Freundschaft betrifft immer zwei.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);
[$uid, $user] = requireUser($body);

const SOCIAL_FILE = DATA_DIR . '/social.json';
const MAX_FREUNDE = 50;

match (clean($body['action'] ?? '', 20)) {
    'suche' => suchen($uid, $user, $body),
    'anfrage' => anfragen($uid, $user, $body),
    'annehmen' => annehmen($uid, $user, $body),
    'entfernen' => entfernen($uid, $user, $body),
    'liste' => liste($uid, $user, $body),
    'woche' => woche($uid, $user, $body),
    'kudos' => kudos($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

/* ------------------------------------------------------------- Speicher */

function social(): array
{
    $d = readJson(SOCIAL_FILE, ['edges' => [], 'requests' => []]);
    $d['edges'] = is_array($d['edges'] ?? null) ? $d['edges'] : [];
    $d['requests'] = is_array($d['requests'] ?? null) ? $d['requests'] : [];
    return $d;
}

/** Kanten liegen immer sortiert - so gibt es jede Freundschaft nur einmal. */
function kante(string $a, string $b): array
{
    return $a < $b ? [$a, $b] : [$b, $a];
}

function freundeVon(array $d, string $uid): array
{
    $out = [];
    foreach ($d['edges'] as $e) {
        if (!is_array($e) || count($e) !== 2) {
            continue;
        }
        if ($e[0] === $uid) {
            $out[] = $e[1];
        } elseif ($e[1] === $uid) {
            $out[] = $e[0];
        }
    }
    return array_values(array_unique($out));
}

function sindFreunde(array $d, string $a, string $b): bool
{
    $k = kante($a, $b);
    foreach ($d['edges'] as $e) {
        if (is_array($e) && ($e[0] ?? '') === $k[0] && ($e[1] ?? '') === $k[1]) {
            return true;
        }
    }
    return false;
}

/* --------------------------------------------------------------- Suchen */

function suchen(string $uid, array $user, array $body): never
{
    rateLimit('suche', 40, 600, $uid);

    $name = normalizeName(clean($body['name'] ?? '', MAX_NAME));
    if (mb_strlen($name) < 3) {
        fail('Mindestens 3 Zeichen.', 400, 'kurz');
    }

    $index = userIndex();
    $treffer = [];
    $d = social();

    foreach ($index as $nameLower => $anderer) {
        if (!is_string($anderer) || $anderer === $uid) {
            continue;
        }
        // Anfang des Namens muss passen. Kein Volltext über alle Nutzer -
        // die Nutzerliste ist nichts, worin man stöbern können soll.
        if (!str_starts_with((string) $nameLower, $name)) {
            continue;
        }

        $u = loadUser($anderer);
        if ($u === null) {
            continue;
        }

        $treffer[] = [
            'id' => $anderer,
            'name' => (string) ($u['name'] ?? ''),
            'schonFreund' => sindFreunde($d, $uid, $anderer),
            'angefragt' => offeneAnfrage($d, $uid, $anderer),
        ];
        if (count($treffer) >= 8) {
            break;
        }
    }

    send(withFreshToken(['ok' => true, 'treffer' => $treffer], $uid, $user, $body));
}

function offeneAnfrage(array $d, string $a, string $b): bool
{
    foreach ($d['requests'] as $r) {
        $von = (string) ($r['from'] ?? '');
        $an = (string) ($r['to'] ?? '');
        if (($von === $a && $an === $b) || ($von === $b && $an === $a)) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------- Anfragen */

function anfragen(string $uid, array $user, array $body): never
{
    rateLimit('anfrage', 30, 3600, $uid);
    $ziel = clean($body['id'] ?? '', 40);

    if (!validUserId($ziel) || $ziel === $uid || loadUser($ziel) === null) {
        fail('Person nicht gefunden.', 404, 'weg');
    }

    withLock('social', static function () use ($uid, $ziel): void {
        $d = social();

        if (sindFreunde($d, $uid, $ziel)) {
            fail('Ihr seid schon verbunden.', 409, 'schon');
        }
        if (count(freundeVon($d, $uid)) >= MAX_FREUNDE) {
            fail('Deine Freundesliste ist voll.', 409, 'voll');
        }

        /*
         * Liegt bereits eine Anfrage in die GEGENRICHTUNG vor, ist das
         * eine Zusage - nicht eine zweite Anfrage. Sonst müssten zwei
         * Leute, die sich gleichzeitig anfragen, ewig aufeinander warten.
         */
        foreach ($d['requests'] as $i => $r) {
            if (($r['from'] ?? '') === $ziel && ($r['to'] ?? '') === $uid) {
                array_splice($d['requests'], $i, 1);
                $d['edges'][] = kante($uid, $ziel);
                writeJson(SOCIAL_FILE, $d);
                return;
            }
            if (($r['from'] ?? '') === $uid && ($r['to'] ?? '') === $ziel) {
                return; // schon angefragt, nichts zu tun
            }
        }

        $d['requests'][] = ['from' => $uid, 'to' => $ziel, 'at' => date('c')];
        writeJson(SOCIAL_FILE, $d);
    });

    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

function annehmen(string $uid, array $user, array $body): never
{
    $von = clean($body['id'] ?? '', 40);

    withLock('social', static function () use ($uid, $von): void {
        $d = social();
        foreach ($d['requests'] as $i => $r) {
            if (($r['from'] ?? '') !== $von || ($r['to'] ?? '') !== $uid) {
                continue;
            }
            array_splice($d['requests'], $i, 1);
            if (!sindFreunde($d, $uid, $von)) {
                $d['edges'][] = kante($uid, $von);
            }
            writeJson(SOCIAL_FILE, $d);
            return;
        }
        fail('Anfrage nicht gefunden.', 404, 'weg');
    });

    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

function entfernen(string $uid, array $user, array $body): never
{
    $anderer = clean($body['id'] ?? '', 40);

    withLock('social', static function () use ($uid, $anderer): void {
        $d = social();
        $k = kante($uid, $anderer);

        $d['edges'] = array_values(array_filter(
            $d['edges'],
            static fn($e) => !(is_array($e) && ($e[0] ?? '') === $k[0] && ($e[1] ?? '') === $k[1]),
        ));
        $d['requests'] = array_values(array_filter(
            $d['requests'],
            static fn($r) => !(
                (($r['from'] ?? '') === $uid && ($r['to'] ?? '') === $anderer)
                || (($r['from'] ?? '') === $anderer && ($r['to'] ?? '') === $uid)
            ),
        ));
        writeJson(SOCIAL_FILE, $d);
    });

    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

/* ----------------------------------------------------------------- Heute */

function liste(string $uid, array $user, array $body): never
{
    rateLimit('freunde', 120, 600, $uid);

    $d = social();
    $freunde = freundeVon($d, $uid);
    $startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
    $heute = dayKey($startStunde);

    // EINE Datei für alle Freunde - unabhängig davon, ob es drei oder
    // dreihundert sind. Das ist der ganze Sinn des Tagesaggregats.
    $feed = readJson(FEED_DIR . '/' . $heute . '.json', ['users' => []]);
    $knoten = is_array($feed['users'] ?? null) ? $feed['users'] : [];

    $eintraege = [];
    foreach ($freunde as $fid) {
        $u = loadUser($fid);
        if ($u === null) {
            continue;
        }
        $sicht = (string) ($u['prefs']['feedVisibility'] ?? 'prozent');
        if ($sicht === 'aus') {
            continue;
        }

        $k = is_array($knoten[$fid] ?? null) ? $knoten[$fid] : null;

        $eintraege[] = [
            'id' => $fid,
            'name' => (string) ($u['name'] ?? ''),
            'streak' => (int) ($u['streak']['days'] ?? 0),
            'aktiv' => $k !== null,
            // Bei "teilnahme" gibt es nur die Information, DASS jemand
            // eingetragen hat - keine Zahl.
            'pctIn' => $sicht === 'prozent' && $k ? (int) ($k['pctIn'] ?? 0) : null,
            'pctNet' => $sicht === 'prozent' && $k ? (int) ($k['pctNet'] ?? 0) : null,
            'mahlzeiten' => $k ? (int) ($k['meals'] ?? 0) : 0,
            'sport' => $k ? (int) ($k['sports'] ?? 0) : 0,
            'letztes' => $sicht === 'prozent' && $k ? ($k['last'] ?? null) : null,
            'kudos' => is_array($k['kudos'] ?? null) ? array_values($k['kudos']) : [],
            'meinKudo' => $k['kudos'][$uid] ?? null,
        ];
    }

    // Wer heute schon etwas gemacht hat, steht oben.
    usort($eintraege, static fn($a, $b) => [$b['aktiv'], $b['mahlzeiten']] <=> [$a['aktiv'], $a['mahlzeiten']]);

    $offen = [];
    foreach ($d['requests'] as $r) {
        if (($r['to'] ?? '') !== $uid) {
            continue;
        }
        $u = loadUser((string) $r['from']);
        if ($u !== null) {
            $offen[] = ['id' => (string) $r['from'], 'name' => (string) ($u['name'] ?? '')];
        }
    }

    send(withFreshToken([
        'ok' => true,
        'freunde' => $eintraege,
        'anfragen' => $offen,
        'sichtbarkeit' => (string) ($user['prefs']['feedVisibility'] ?? 'prozent'),
    ], $uid, $user, $body));
}

/* ------------------------------------------------------------ Bestenliste */

/**
 * Die Wochenliste misst Beständigkeit, nicht Defizit.
 *
 * Das ist eine bewusste Entscheidung: Eine Rangliste nach "wer isst am
 * wenigsten" würde in einer Ernährungs-App genau das Falsche belohnen.
 * Gezählt wird, wer eingetragen hat - der einzige Wert, den man ohne
 * Schaden maximieren kann.
 */
function woche(string $uid, array $user, array $body): never
{
    rateLimit('freunde', 120, 600, $uid);

    $d = social();
    $ids = freundeVon($d, $uid);
    $ids[] = $uid; // man selbst steht mit in der Liste

    $startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
    $heute = dayKey($startStunde);

    $werte = [];
    for ($i = 0; $i < 7; $i++) {
        $datum = date('Y-m-d', strtotime($heute . " -{$i} day"));
        $feed = readJson(FEED_DIR . '/' . $datum . '.json', ['users' => []]);
        foreach (is_array($feed['users'] ?? null) ? $feed['users'] : [] as $fid => $k) {
            if (!in_array($fid, $ids, true)) {
                continue;
            }
            $werte[$fid] ??= ['tage' => 0, 'summePct' => 0];
            $werte[$fid]['tage']++;
            $werte[$fid]['summePct'] += (int) ($k['pctNet'] ?? 0);
        }
    }

    $liste = [];
    foreach ($ids as $fid) {
        $u = loadUser($fid);
        if ($u === null) {
            continue;
        }
        if ($fid !== $uid && (string) ($u['prefs']['feedVisibility'] ?? 'prozent') === 'aus') {
            continue;
        }
        $w = $werte[$fid] ?? ['tage' => 0, 'summePct' => 0];
        $liste[] = [
            'id' => $fid,
            'name' => (string) ($u['name'] ?? ''),
            'ich' => $fid === $uid,
            'tage' => $w['tage'],
            'schnittPct' => $w['tage'] > 0 ? (int) round($w['summePct'] / $w['tage']) : null,
        ];
    }

    // Erst Beständigkeit, dann Nähe zum eigenen Ziel.
    usort($liste, static function (array $a, array $b): int {
        if ($a['tage'] !== $b['tage']) {
            return $b['tage'] <=> $a['tage'];
        }
        $da = $a['schnittPct'] === null ? 999 : abs(100 - $a['schnittPct']);
        $db = $b['schnittPct'] === null ? 999 : abs(100 - $b['schnittPct']);
        return $da <=> $db;
    });

    send(withFreshToken(['ok' => true, 'liste' => $liste], $uid, $user, $body));
}

/* ------------------------------------------------------------------ Kudos */

function kudos(string $uid, array $user, array $body): never
{
    rateLimit('kudos', 60, 600, $uid);

    $ziel = clean($body['id'] ?? '', 40);
    $emoji = clean($body['emoji'] ?? '', 8);

    if (!in_array($emoji, ['👏', '🔥', '💪', ''], true)) {
        fail('Unbekannte Reaktion.', 400, 'emoji');
    }
    if (!sindFreunde(social(), $uid, $ziel)) {
        fail('Nicht befreundet.', 403, 'fremd');
    }

    $startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
    $datum = dayKey($startStunde);

    withLock('feed-' . $datum, static function () use ($datum, $ziel, $uid, $emoji): void {
        $datei = FEED_DIR . '/' . $datum . '.json';
        $feed = readJson($datei, ['date' => $datum, 'users' => []]);
        if (!isset($feed['users'][$ziel])) {
            fail('Heute noch nichts eingetragen.', 404, 'leer');
        }

        $bisher = is_array($feed['users'][$ziel]['kudos'] ?? null) ? $feed['users'][$ziel]['kudos'] : [];
        // Leeres Emoji nimmt die eigene Reaktion zurück - derselbe Knopf
        // hin und zurück, kein zweiter zum Löschen.
        if ($emoji === '') {
            unset($bisher[$uid]);
        } else {
            $bisher[$uid] = $emoji;
        }

        $feed['users'][$ziel]['kudos'] = $bisher;
        writeJson($datei, $feed);
    });

    send(withFreshToken(['ok' => true], $uid, $user, $body));
}
