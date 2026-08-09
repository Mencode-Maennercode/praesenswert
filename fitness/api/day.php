<?php
/**
 * Der Tag: Mahlzeiten, Sport, Wasser.
 *
 *   get      Tagesdaten + häufige Einträge + Sportliste
 *   meal     Mahlzeit eintragen
 *   sport    Bewegung eintragen
 *   edit     Eintrag ändern
 *   delete   Eintrag in den Papierkorb
 *   undo     aus dem Papierkorb zurückholen
 *   water    Wasserstand setzen
 *
 * Jeder schreibende Aufruf tut zwei Dinge nacheinander: erst die Tagesdatei
 * unter ihrer Sperre, dann das Feed-Aggregat unter seiner. Nie beide
 * gleichzeitig gehalten - daraus entstünde eine Verklemmung.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';

cors();
requireMethod('POST');

$body = jsonBody(16 * 1024);
[$uid, $user] = requireUser($body);

$derived = is_array($user['derived'] ?? null) ? $user['derived'] : [];
if ($derived === []) {
    fail('Erst das Onboarding abschließen.', 409, 'kein-profil');
}

$startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
$heute = dayKey($startStunde);

// Ein anderes Datum ist erlaubt, aber nur rückwärts: die Zukunft zu
// protokollieren ergibt keinen Sinn und würde den Feed verwirren.
$datum = validDate($body['datum'] ?? null) ?: $heute;
if ($datum > $heute) {
    $datum = $heute;
}

match (clean($body['action'] ?? '', 20)) {
    'get' => holen($uid, $user, $datum, $derived, $body),
    'meal' => mahlzeit($uid, $user, $datum, $derived, $body),
    'sport' => bewegung($uid, $user, $datum, $derived, $body),
    'edit' => aendern($uid, $user, $datum, $derived, $body),
    'delete' => loeschen($uid, $user, $datum, $derived, $body),
    'undo' => zurueckholen($uid, $user, $datum, $derived, $body),
    'water' => wasser($uid, $user, $datum, $derived, $body),
    default => fail('Unbekannte Aktion.'),
};

/* -------------------------------------------------------------------- Lesen */

function holen(string $uid, array $user, string $datum, array $derived, array $body): never
{
    $tag = recomputeSums(loadDay($uid, $datum), $derived);

    send(withFreshToken([
        'ok' => true,
        'tag' => oeffentlich($tag),
        'haeufig' => haeufigTop($user),
        'sport' => sportListe(),
        'streak' => $user['streak'] ?? ['days' => 0, 'last' => null],
    ], $uid, $user, $body));
}

/** Der Papierkorb geht den Client nichts an - ausser dem letzten Eintrag. */
function oeffentlich(array $tag): array
{
    unset($tag['trash']);
    return $tag;
}

function sportListe(): array
{
    $out = [];
    foreach (metTable() as $key => $e) {
        $out[] = [
            'key' => $key,
            'label' => $e['label'],
            'met' => $e['met'],
            'haeufig' => (bool) ($e['haeufig'] ?? false),
        ];
    }
    return $out;
}

/* ---------------------------------------------------------------- Mahlzeit */

function mahlzeit(string $uid, array $user, string $datum, array $derived, array $body): never
{
    rateLimit('eintrag', 120, 600, $uid);

    $titel = clean($body['title'] ?? '', 80);
    if ($titel === '') {
        fail('Was war es?', 400, 'titel');
    }

    /*
     * 4000 kcal für eine einzelne Mahlzeit ist absurd hoch, aber nicht
     * unmöglich. Die Grenze soll den Vertipper abfangen (12000 statt 1200),
     * nicht den Feiertagsbraten verbieten.
     */
    $kcal = requireIntRange($body['kcal'] ?? null, 1, 4000, 'Kalorien prüfen.', 'kcal');

    $slot = (string) ($body['slot'] ?? '');
    if (!in_array($slot, ['fruehstueck', 'mittag', 'abend', 'snack'], true)) {
        $slot = slotAusUhrzeit();
    }

    $eintrag = [
        'id' => 'm_' . bin2hex(random_bytes(6)),
        'at' => date('c'),
        'source' => clean($body['source'] ?? 'manuell', 20),
        'title' => $titel,
        'slot' => $slot,
        'kcal' => $kcal,
        'p' => asFloat($body['p'] ?? null, 0, 400, 0),
        'c' => asFloat($body['c'] ?? null, 0, 800, 0),
        'f' => asFloat($body['f'] ?? null, 0, 400, 0),
    ];

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $eintrag): array {
        $tag = purgeTrash(loadDay($uid, $datum));
        $tag['meals'][] = $eintrag;
        return saveDay($uid, $tag, $derived);
    });

    // Häufigkeit und Serie stehen im Nutzerdatensatz, nicht im Tag.
    $user = merkeHaeufig($user, $eintrag);
    $user = serieFortschreiben($user, $datum);
    saveUser($uid, $user);

    updateFeed($uid, $user, $tag);

    send(withFreshToken([
        'ok' => true,
        'tag' => oeffentlich($tag),
        'eintrag' => $eintrag,
        'haeufig' => haeufigTop($user),
        'streak' => $user['streak'],
    ], $uid, $user, $body));
}

function slotAusUhrzeit(): string
{
    $h = (int) date('G');
    if ($h < 11) {
        return 'fruehstueck';
    }
    if ($h < 16) {
        return 'mittag';
    }
    if ($h < 22) {
        return 'abend';
    }
    return 'snack';
}

/* ------------------------------------------------------------------- Sport */

function bewegung(string $uid, array $user, string $datum, array $derived, array $body): never
{
    rateLimit('eintrag', 120, 600, $uid);

    $key = clean($body['activity'] ?? '', 40);
    $met = metFor($key);
    if ($met === null) {
        fail('Unbekannte Aktivität.', 400, 'aktivitaet');
    }

    $minuten = requireIntRange($body['minutes'] ?? null, 1, 600, 'Dauer prüfen.', 'minuten');
    $kg = (float) ($user['profile']['weightKg'] ?? 75);
    $tabelle = metTable();

    $eintrag = [
        'id' => 's_' . bin2hex(random_bytes(6)),
        'at' => date('c'),
        'source' => clean($body['source'] ?? 'manuell', 20),
        'activity' => $key,
        'label' => $tabelle[$key]['label'],
        'met' => $met,
        'minutes' => $minuten,
        'kcal' => sportKcal($met, $kg, $minuten),
    ];

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $eintrag): array {
        $tag = purgeTrash(loadDay($uid, $datum));
        $tag['sport'][] = $eintrag;
        return saveDay($uid, $tag, $derived);
    });

    $user = serieFortschreiben($user, $datum);
    saveUser($uid, $user);
    updateFeed($uid, $user, $tag);

    send(withFreshToken([
        'ok' => true,
        'tag' => oeffentlich($tag),
        'eintrag' => $eintrag,
        'streak' => $user['streak'],
    ], $uid, $user, $body));
}

/* ------------------------------------------------------------------ Ändern */

function aendern(string $uid, array $user, string $datum, array $derived, array $body): never
{
    rateLimit('eintrag', 120, 600, $uid);
    $id = clean($body['id'] ?? '', 40);
    $kg = (float) ($user['profile']['weightKg'] ?? 75);

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $id, $body, $kg): array {
        $tag = purgeTrash(loadDay($uid, $datum));

        foreach ($tag['meals'] as $i => $m) {
            if (($m['id'] ?? '') !== $id) {
                continue;
            }
            $titel = clean($body['title'] ?? $m['title'], 80);
            $tag['meals'][$i] = [
                ...$m,
                'title' => $titel !== '' ? $titel : $m['title'],
                'kcal' => requireIntRange($body['kcal'] ?? $m['kcal'], 1, 4000, 'Kalorien prüfen.', 'kcal'),
                'p' => asFloat($body['p'] ?? $m['p'], 0, 400, 0),
                'c' => asFloat($body['c'] ?? $m['c'], 0, 800, 0),
                'f' => asFloat($body['f'] ?? $m['f'], 0, 400, 0),
                'edited' => true,
            ];
            return saveDay($uid, $tag, $derived);
        }

        foreach ($tag['sport'] as $i => $s) {
            if (($s['id'] ?? '') !== $id) {
                continue;
            }
            $minuten = requireIntRange($body['minutes'] ?? $s['minutes'], 1, 600, 'Dauer prüfen.', 'minuten');
            $tag['sport'][$i] = [
                ...$s,
                'minutes' => $minuten,
                // Neu rechnen statt den alten Wert zu skalieren: das Gewicht
                // kann sich seit dem Eintrag geändert haben.
                'kcal' => sportKcal((float) $s['met'], $kg, $minuten),
                'edited' => true,
            ];
            return saveDay($uid, $tag, $derived);
        }

        fail('Eintrag nicht gefunden.', 404, 'weg');
    });

    updateFeed($uid, $user, $tag);
    send(withFreshToken(['ok' => true, 'tag' => oeffentlich($tag)], $uid, $user, $body));
}

/* ------------------------------------------------------------------ Löschen */

function loeschen(string $uid, array $user, string $datum, array $derived, array $body): never
{
    rateLimit('eintrag', 120, 600, $uid);
    $id = clean($body['id'] ?? '', 40);

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $id): array {
        $tag = purgeTrash(loadDay($uid, $datum));

        foreach (['meals', 'sport'] as $feld) {
            foreach ($tag[$feld] as $i => $e) {
                if (($e['id'] ?? '') !== $id) {
                    continue;
                }
                // Nicht wegwerfen, sondern beiseitelegen. Ein Fehlgriff auf
                // dem Handy ist häufig, und "weg ist weg" wäre unnötig hart.
                $tag['trash'][] = ['art' => $feld, 'eintrag' => $e, 'deletedAt' => date('c')];
                array_splice($tag[$feld], $i, 1);
                return saveDay($uid, $tag, $derived);
            }
        }

        fail('Eintrag nicht gefunden.', 404, 'weg');
    });

    updateFeed($uid, $user, $tag);
    send(withFreshToken(['ok' => true, 'tag' => oeffentlich($tag)], $uid, $user, $body));
}

function zurueckholen(string $uid, array $user, string $datum, array $derived, array $body): never
{
    $id = clean($body['id'] ?? '', 40);

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $id): array {
        $tag = purgeTrash(loadDay($uid, $datum));

        foreach ($tag['trash'] as $i => $t) {
            if (($t['eintrag']['id'] ?? '') !== $id) {
                continue;
            }
            $tag[$t['art']][] = $t['eintrag'];
            array_splice($tag['trash'], $i, 1);
            return saveDay($uid, $tag, $derived);
        }

        fail('Nichts zum Zurückholen.', 404, 'weg');
    });

    updateFeed($uid, $user, $tag);
    send(withFreshToken(['ok' => true, 'tag' => oeffentlich($tag)], $uid, $user, $body));
}

/* ------------------------------------------------------------------- Wasser */

function wasser(string $uid, array $user, string $datum, array $derived, array $body): never
{
    rateLimit('eintrag', 200, 600, $uid);
    $ml = requireIntRange($body['ml'] ?? null, 0, 10000, 'Menge prüfen.', 'ml');

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $ml): array {
        $tag = purgeTrash(loadDay($uid, $datum));
        $tag['waterMl'] = $ml;
        return saveDay($uid, $tag, $derived);
    });

    send(withFreshToken(['ok' => true, 'tag' => oeffentlich($tag)], $uid, $user, $body));
}

/* -------------------------------------------------------------------- Serie */

/**
 * Zählt aufeinanderfolgende Tage mit mindestens einem Eintrag.
 *
 * Nur der heutige Tag zählt fort. Wer gestern etwas nachträgt, bekommt
 * dafür keine Serie zurück - sonst wäre sie beliebig herstellbar und
 * verlöre genau die Wirkung, wegen der es sie gibt.
 */
function serieFortschreiben(array $user, string $datum): array
{
    $serie = is_array($user['streak'] ?? null) ? $user['streak'] : ['days' => 0, 'last' => null];
    $letzter = (string) ($serie['last'] ?? '');

    if ($letzter === $datum) {
        return $user;
    }

    $gestern = date('Y-m-d', strtotime($datum . ' -1 day'));
    $serie['days'] = $letzter === $gestern ? (int) $serie['days'] + 1 : 1;
    $serie['last'] = $datum;

    $user['streak'] = $serie;
    return $user;
}
