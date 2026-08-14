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

    /*
     * Man selbst steht mit in der Liste.
     *
     * Ein Vergleich ohne den eigenen Wert ist keiner - man müsste in
     * einen anderen Reiter wechseln und im Kopf umrechnen. Und die eigene
     * Sichtbarkeitseinstellung gilt hier nicht: Was man selbst sieht,
     * geht niemanden sonst etwas an.
     */
    $eintraege = [];
    foreach ([$uid, ...$freunde] as $fid) {
        $u = loadUser($fid);
        if ($u === null) {
            continue;
        }
        $ich = $fid === $uid;
        $sicht = $ich ? 'prozent' : (string) ($u['prefs']['feedVisibility'] ?? 'prozent');
        if ($sicht === 'aus') {
            continue;
        }

        $k = is_array($knoten[$fid] ?? null) ? $knoten[$fid] : null;
        $ab = abnahme($fid);

        $eintraege[] = [
            'id' => $fid,
            'name' => $ich ? 'Du' : (string) ($u['name'] ?? ''),
            'ich' => $ich,
            /*
             * Der Wert, um den es hier geht - und der einzige, der für
             * ALLE sichtbar ist, unabhängig von der Sichtbarkeits-
             * einstellung. Wer sich verbindet, macht mit; ein
             * Wettbewerb, bei dem die Hälfte ihre Zahl verbirgt, ist
             * keiner.
             */
            'abnahmePct' => $ab['pct'],
            'messungen' => $ab['messungen'],
            'seit' => $ab['seit'],
            'streak' => (int) ($u['streak']['days'] ?? 0),
            'aktiv' => $k !== null,
            // Bei "teilnahme" gibt es nur die Information, DASS jemand
            // eingetragen hat - keine Zahl.
            'pctIn' => $sicht === 'prozent' && $k ? (int) ($k['pctIn'] ?? 0) : null,
            'pctNet' => $sicht === 'prozent' && $k ? (int) ($k['pctNet'] ?? 0) : null,
            // Das Ergebnis gegen das eigene Tagesziel - die Zahl, die zählt.
            'pctZiel' => $sicht === 'prozent' && $k && isset($k['pctZiel'])
                ? (int) $k['pctZiel']
                : null,
            'sportListe' => $sicht === 'prozent' && is_array($k['sportListe'] ?? null)
                ? array_slice($k['sportListe'], 0, 3)
                : [],
            'mahlzeiten' => $k ? (int) ($k['meals'] ?? 0) : 0,
            'sport' => $k ? (int) ($k['sports'] ?? 0) : 0,
            'letztes' => $sicht === 'prozent' && $k ? ($k['last'] ?? null) : null,
            'kudos' => is_array($k['kudos'] ?? null) ? array_values($k['kudos']) : [],
            'meinKudo' => $k['kudos'][$uid] ?? null,
        ];
    }

    /*
     * Der niedrigste Tageswert steht oben, wer nichts eingetragen hat
     * ganz unten.
     *
     * Wichtig ist die zweite Hälfte: Ein leerer Tag ist KEIN Wert von
     * null. Wer nichts eingetragen hat, hat nicht nichts gegessen - er
     * hätte sonst automatisch den ersten Platz, und die Liste würde
     * genau das Gegenteil dessen belohnen, wozu sie da ist.
     */
    /*
     * Platz eins hat, wer am meisten Prozent abgenommen hat.
     *
     * Wer noch keine zweite Messung hat, steht unten - nicht bei null
     * Prozent mittendrin. Eine fehlende Messung ist keine Nullabnahme,
     * und wer sich nie wiegt, soll nicht zwischen denen stehen, die es tun.
     */
    usort($eintraege, static function (array $a, array $b): int {
        $aHat = $a['abnahmePct'] !== null && $a['messungen'] > 1;
        $bHat = $b['abnahmePct'] !== null && $b['messungen'] > 1;
        if ($aHat !== $bHat) {
            return $aHat ? -1 : 1;
        }
        return ($b['abnahmePct'] ?? -999) <=> ($a['abnahmePct'] ?? -999);
    });

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
        $gewicht = gewichtsWoche($fid);

        $liste[] = [
            'id' => $fid,
            'name' => $fid === $uid ? 'Du' : (string) ($u['name'] ?? ''),
            'ich' => $fid === $uid,
            'tage' => $w['tage'],
            'schnittPct' => $w['tage'] > 0 ? (int) round($w['summePct'] / $w['tage']) : null,
            // Absolut UND als Anteil vom Körpergewicht: Ein Kilo bei 58 kg
            // ist etwas anderes als ein Kilo bei 95 kg. Erst der Anteil
            // macht die Zeilen vergleichbar.
            'kg' => $gewicht['kg'],
            'kgPct' => $gewicht['pct'],
        ];
    }

    /*
     * Niedrigster Wochenschnitt oben, wer nichts eingetragen hat unten.
     *
     * Wieder: Ein Tag ohne Eintrag ist kein Nullwert. Wer gar nichts
     * erfasst, steht hinten - nicht vorn.
     */
    usort($liste, static function (array $a, array $b): int {
        $aLeer = $a['tage'] === 0;
        $bLeer = $b['tage'] === 0;
        if ($aLeer !== $bLeer) {
            return $aLeer ? 1 : -1;
        }
        return ($a['schnittPct'] ?? 1000) <=> ($b['schnittPct'] ?? 1000);
    });

    send(withFreshToken(['ok' => true, 'liste' => $liste], $uid, $user, $body));
}

/**
 * Wie viel Prozent hat jemand seit dem Start abgenommen?
 *
 * Bezug ist die ERSTE Messung - beim Onboarding wird sie automatisch
 * angelegt, es gibt sie also für jeden ab dem ersten Tag.
 *
 * Prozent statt Kilo, aus demselben Grund wie überall in dieser App:
 * Drei Kilo bei 58 kg sind ein anderer Erfolg als drei Kilo bei 110 kg.
 * Nur der Anteil macht zwei Menschen vergleichbar.
 *
 * Positiv heisst abgenommen. Das ist umgekehrt zur Rechenrichtung, aber
 * es ist die Richtung, in der Menschen denken: "Ich habe 4 % geschafft."
 *
 * @return array{pct: float|null, messungen: int, seit: string|null}
 */
function abnahme(string $uid): array
{
    $d = readJson(WEIGHT_DIR . '/' . $uid . '.json', ['points' => []]);
    $punkte = is_array($d['points'] ?? null) ? $d['points'] : [];
    if ($punkte === []) {
        return ['pct' => null, 'messungen' => 0, 'seit' => null];
    }

    usort($punkte, static fn($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
    $start = (float) $punkte[0]['kg'];
    $jetzt = (float) $punkte[count($punkte) - 1]['kg'];

    if ($start <= 0) {
        return ['pct' => null, 'messungen' => count($punkte), 'seit' => null];
    }

    return [
        'pct' => round(($start - $jetzt) / $start * 100, 1),
        'messungen' => count($punkte),
        'seit' => (string) $punkte[0]['d'],
    ];
}

/**
 * Gewichtsveränderung der letzten sieben Tage.
 *
 * Verglichen wird der letzte Wert der Woche mit dem letzten Wert DAVOR -
 * nicht mit dem ersten der Woche. Sonst hinge das Ergebnis daran, an
 * welchem Wochentag jemand zufällig zuerst auf die Waage gestiegen ist.
 *
 * Weitergegeben wird nur die Differenz, nie das Gewicht selbst. Wie
 * schwer jemand ist, geht niemanden etwas an; wie viel sich verändert
 * hat, ist der Punkt der Liste.
 *
 * @return array{kg: float|null, pct: float|null}
 */
function gewichtsWoche(string $uid): array
{
    $d = readJson(WEIGHT_DIR . '/' . $uid . '.json', ['points' => []]);
    $punkte = is_array($d['points'] ?? null) ? $d['points'] : [];
    if (count($punkte) < 2) {
        return ['kg' => null, 'pct' => null];
    }

    usort($punkte, static fn($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
    $grenze = date('Y-m-d', strtotime('-7 day'));

    $davor = null;
    $jetzt = null;
    foreach ($punkte as $p) {
        if ((string) $p['d'] <= $grenze) {
            $davor = (float) $p['kg'];
        } else {
            $jetzt = (float) $p['kg'];
        }
    }

    // Ohne Messung vor dem Fenster gibt es keinen Bezug - dann lieber
    // nichts anzeigen als eine Zahl, die etwas anderes bedeutet.
    if ($davor === null || $jetzt === null || $davor <= 0) {
        return ['kg' => null, 'pct' => null];
    }

    return [
        'kg' => round($jetzt - $davor, 1),
        'pct' => round(($jetzt - $davor) / $davor * 100, 1),
    ];
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
