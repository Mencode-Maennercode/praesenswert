<?php
/**
 * Tagesablage: Mahlzeiten, Sport, Wasser - und das Aggregat für die Freunde.
 *
 * Eine Datei pro Nutzer und Tag. Das klingt nach vielen Dateien, ist aber
 * genau richtig: Ein Eintrag berührt nur den heutigen Tag, ein Wochenblick
 * liest sieben kleine Dateien, und zwei Nutzer kommen sich beim Schreiben
 * nie in die Quere.
 */

declare(strict_types=1);

/* ------------------------------------------------------------ Tagesgrenze */

/**
 * Zu welchem Tag gehört dieser Zeitpunkt?
 *
 * Nicht Mitternacht entscheidet, sondern die eingestellte Tagesgrenze
 * (Standard 4 Uhr). Was um 1 Uhr nachts gegessen wird, gehört gefühlt zum
 * Vortag - und wer es dem Folgetag zuschlägt, verdirbt beide Bilanzen.
 */
function dayKey(int $dayStartHour, ?int $ts = null): string
{
    $ts ??= time();
    return date('Y-m-d', $ts - $dayStartHour * 3600);
}

function validDate(mixed $d): string
{
    if (!is_string($d) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) !== 1) {
        return '';
    }
    // Nur Daten, die es wirklich gibt - "2026-02-31" ist keins.
    [$j, $m, $t] = array_map('intval', explode('-', $d));
    return checkdate($m, $t, $j) ? $d : '';
}

function dayPath(string $uid, string $datum): string
{
    return DAYS_DIR . '/' . $uid . '/' . $datum . '.json';
}

function leererTag(string $uid, string $datum): array
{
    return [
        'date' => $datum,
        'userId' => $uid,
        'meals' => [],
        'sport' => [],
        'waterMl' => 0,
        'trash' => [],
        'sums' => ['in' => 0, 'out' => 0, 'goal' => 0, 'rest' => 0, 'p' => 0, 'c' => 0, 'f' => 0],
        'updatedAt' => null,
    ];
}

function loadDay(string $uid, string $datum): array
{
    return readJson(dayPath($uid, $datum), leererTag($uid, $datum));
}

/**
 * Rechnet die Summen neu aus den Einträgen.
 *
 * Bewusst bei jedem Schreiben komplett neu und nie fortgeschrieben: Eine
 * mitgeführte Summe läuft irgendwann auseinander - beim Löschen, beim
 * Ändern, bei einer abgebrochenen Anfrage. Die Einträge sind die Wahrheit,
 * die Summe ist nur ihre Ableitung.
 */
function recomputeSums(array $tag, array $derived): array
{
    $in = $p = $c = $f = 0.0;
    foreach ($tag['meals'] as $m) {
        $in += (float) ($m['kcal'] ?? 0);
        $p += (float) ($m['p'] ?? 0);
        $c += (float) ($m['c'] ?? 0);
        $f += (float) ($m['f'] ?? 0);
    }

    $out = 0.0;
    foreach ($tag['sport'] as $s) {
        $out += (float) ($s['kcal'] ?? 0);
    }

    $goal = (int) ($derived['goalKcal'] ?? 0);
    $tag['sums'] = [
        'in' => (int) round($in),
        'out' => (int) round($out),
        'goal' => $goal,
        // Was heute noch reingeht. Sport zählt dazu - wer läuft, darf essen.
        'rest' => (int) round($goal - $in + $out),
        'p' => (int) round($p),
        'c' => (int) round($c),
        'f' => (int) round($f),
    ];
    return $tag;
}

function saveDay(string $uid, array $tag, array $derived): array
{
    $tag = recomputeSums($tag, $derived);
    $tag['updatedAt'] = date('c');
    writeJson(dayPath($uid, $tag['date']), $tag);
    return $tag;
}

/**
 * Räumt den Papierkorb auf.
 *
 * 30 Tage sind grosszügig für ein versehentliches Löschen und kurz genug,
 * dass die Datei nicht wächst.
 */
function purgeTrash(array $tag): array
{
    $grenze = time() - 30 * 86400;
    $tag['trash'] = array_values(array_filter(
        is_array($tag['trash'] ?? null) ? $tag['trash'] : [],
        static fn($e) => strtotime((string) ($e['deletedAt'] ?? '')) > $grenze,
    ));
    return $tag;
}

/* ----------------------------------------------------------------- Feed */

/**
 * Schreibt den Prozentwert des Nutzers in das Tagesaggregat aller Nutzer.
 *
 * Der Freunde-Tab liest dadurch EINE Datei statt einer pro Freund - egal
 * ob drei oder dreihundert. Der Feed ist reiner Zwischenspeicher; die
 * Wahrheit stehen in days/. Geht er verloren, baut cron.php ihn neu.
 *
 * Wichtig: Diese Sperre wird immer NACH der Tagessperre genommen und nie
 * darin verschachtelt. Zwei Sperren gleichzeitig zu halten ist der Weg in
 * eine Verklemmung.
 */
function updateFeed(string $uid, array $user, array $tag): void
{
    $sicht = (string) ($user['prefs']['feedVisibility'] ?? 'prozent');
    $datum = (string) $tag['date'];

    withLock('feed-' . $datum, static function () use ($uid, $user, $tag, $sicht, $datum): void {
        $datei = FEED_DIR . '/' . $datum . '.json';
        $feed = readJson($datei, ['date' => $datum, 'users' => []]);
        if (!is_array($feed['users'] ?? null)) {
            $feed['users'] = [];
        }

        if ($sicht === 'aus') {
            unset($feed['users'][$uid]);
        } else {
            $tdee = max(1, (int) ($user['derived']['tdee'] ?? 1));
            $in = (int) $tag['sums']['in'];
            $out = (int) $tag['sums']['out'];

            $knoten = [
                'name' => (string) ($user['name'] ?? ''),
                // Prozent statt Kalorien - nur so ist der Vergleich zwischen
                // einer 58-kg-Frau und einem 95-kg-Mann überhaupt fair.
                // Absolute Zahlen verlassen den eigenen Datensatz nie.
                'pctIn' => (int) round($in / $tdee * 100),
                'pctNet' => (int) round(($in - $out) / $tdee * 100),
                'meals' => count($tag['meals']),
                'sports' => count($tag['sport']),
                'updatedAt' => date('c'),
            ];

            if ($sicht === 'prozent') {
                $letzte = end($tag['meals']);
                $knoten['last'] = is_array($letzte)
                    ? ['title' => (string) ($letzte['title'] ?? ''), 'at' => (string) ($letzte['at'] ?? '')]
                    : null;
            }

            $feed['users'][$uid] = $knoten;
        }

        $feed['updatedAt'] = date('c');
        writeJson($datei, $feed);
    });
}

/* ------------------------------------------------------------- Häufiges */

/**
 * Pflegt die Liste der häufigen Einträge - die "Nochmal"-Leiste.
 *
 * Das ist der wichtigste Knopf der App. Niemand fotografiert 300 Tage
 * lang dasselbe Müsli; ab dem dritten Mal will man einen Tipp und fertig.
 *
 * Gespeichert wird im Nutzerdatensatz statt errechnet aus den Tagesdateien:
 * Sonst müsste jeder Aufruf der Startseite 30 Dateien öffnen, um die
 * Vorschläge zu bilden.
 */
function merkeHaeufig(array $user, array $eintrag): array
{
    $liste = is_array($user['haeufig'] ?? null) ? $user['haeufig'] : [];
    $schluessel = mb_strtolower(trim((string) $eintrag['title']));
    if ($schluessel === '') {
        return $user;
    }

    $vorhanden = $liste[$schluessel] ?? null;
    $liste[$schluessel] = [
        'title' => (string) $eintrag['title'],
        // Immer die zuletzt benutzten Werte - Rezepte ändern sich.
        'kcal' => (int) $eintrag['kcal'],
        'p' => (float) ($eintrag['p'] ?? 0),
        'c' => (float) ($eintrag['c'] ?? 0),
        'f' => (float) ($eintrag['f'] ?? 0),
        'count' => (int) ($vorhanden['count'] ?? 0) + 1,
        'last' => date('c'),
    ];

    /*
     * Deckel bei 60. Sortiert wird nach Häufigkeit, bei Gleichstand nach
     * Aktualität - so überlebt das tägliche Frühstück, und der einmalige
     * Ausrutscher vom letzten Sommer fällt heraus.
     */
    if (count($liste) > 60) {
        uasort($liste, static function (array $a, array $b): int {
            return [$b['count'], $b['last']] <=> [$a['count'], $a['last']];
        });
        $liste = array_slice($liste, 0, 60, true);
    }

    $user['haeufig'] = $liste;
    return $user;
}

/** Die Vorschläge für die Leiste: erst das Häufigste, dann das Neueste. */
function haeufigTop(array $user, int $anzahl = 8): array
{
    $liste = is_array($user['haeufig'] ?? null) ? $user['haeufig'] : [];
    uasort($liste, static function (array $a, array $b): int {
        return [$b['count'], $b['last']] <=> [$a['count'], $a['last']];
    });

    $out = [];
    foreach (array_slice($liste, 0, $anzahl, true) as $e) {
        $out[] = [
            'title' => $e['title'],
            'kcal' => (int) $e['kcal'],
            'p' => (float) ($e['p'] ?? 0),
            'c' => (float) ($e['c'] ?? 0),
            'f' => (float) ($e['f'] ?? 0),
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ Sport */

/**
 * MET-Werte nach dem Compendium of Physical Activities.
 *
 * Bewusst eine Tabelle und keine Schätzung durch das Sprachmodell: MET
 * sind Nachschlagewerte. Ein Modell, das heute 9,8 und morgen 10,5 für
 * dasselbe Laufen sagt, macht den Wochenverlauf unvergleichbar.
 *
 * Das Geschlecht fehlt hier nicht versehentlich - es steckt schon im
 * Gesamtumsatz, gegen den bilanziert wird. Zweimal einrechnen wäre falsch.
 */
function metTable(): array
{
    return [
        // Alltag
        'gehen_langsam' => ['label' => 'Spazieren', 'met' => 2.8, 'haeufig' => true],
        'gehen_zuegig' => ['label' => 'Zügig gehen', 'met' => 4.3, 'haeufig' => true],
        'treppensteigen' => ['label' => 'Treppensteigen', 'met' => 8.8],
        'hausputz' => ['label' => 'Putzen', 'met' => 3.3],
        'gartenarbeit' => ['label' => 'Gartenarbeit', 'met' => 3.8],
        'einkaufen' => ['label' => 'Einkaufen', 'met' => 2.3],
        'kinder_spielen' => ['label' => 'Mit Kindern toben', 'met' => 5.8],
        // Ausdauer
        'joggen_8kmh' => ['label' => 'Joggen', 'met' => 8.3, 'haeufig' => true],
        'laufen_10kmh' => ['label' => 'Laufen 10 km/h', 'met' => 9.8],
        'laufen_12kmh' => ['label' => 'Laufen 12 km/h', 'met' => 11.5],
        'wandern' => ['label' => 'Wandern', 'met' => 6.0, 'haeufig' => true],
        'rad_ruhig' => ['label' => 'Radfahren ruhig', 'met' => 5.8, 'haeufig' => true],
        'rad_zuegig' => ['label' => 'Radfahren zügig', 'met' => 8.0],
        'rad_rennrad' => ['label' => 'Rennrad', 'met' => 10.0],
        'schwimmen' => ['label' => 'Schwimmen', 'met' => 7.0, 'haeufig' => true],
        'rudern' => ['label' => 'Rudern', 'met' => 7.0],
        'crosstrainer' => ['label' => 'Crosstrainer', 'met' => 5.0],
        'seilspringen' => ['label' => 'Seilspringen', 'met' => 11.0],
        'inline' => ['label' => 'Inliner', 'met' => 7.5],
        'ski_langlauf' => ['label' => 'Langlauf', 'met' => 9.0],
        'ski_alpin' => ['label' => 'Ski alpin', 'met' => 5.3],
        // Kraft und Kurse
        'krafttraining_moderat' => ['label' => 'Krafttraining', 'met' => 3.5, 'haeufig' => true],
        'krafttraining_intensiv' => ['label' => 'Krafttraining hart', 'met' => 6.0],
        'hiit' => ['label' => 'HIIT', 'met' => 8.0],
        'crossfit' => ['label' => 'Crossfit', 'met' => 7.5],
        'zirkeltraining' => ['label' => 'Zirkeltraining', 'met' => 7.0],
        'yoga' => ['label' => 'Yoga', 'met' => 2.5, 'haeufig' => true],
        'pilates' => ['label' => 'Pilates', 'met' => 3.0],
        'dehnen' => ['label' => 'Dehnen', 'met' => 2.3],
        'aerobic' => ['label' => 'Aerobic', 'met' => 6.5],
        'tanzen' => ['label' => 'Tanzen', 'met' => 5.0],
        'boxen' => ['label' => 'Boxen', 'met' => 9.0],
        'kampfsport' => ['label' => 'Kampfsport', 'met' => 10.3],
        'klettern' => ['label' => 'Klettern', 'met' => 8.0],
        // Ballsport
        'fussball' => ['label' => 'Fußball', 'met' => 7.0],
        'basketball' => ['label' => 'Basketball', 'met' => 6.5],
        'volleyball' => ['label' => 'Volleyball', 'met' => 4.0],
        'handball' => ['label' => 'Handball', 'met' => 8.0],
        'tennis' => ['label' => 'Tennis', 'met' => 7.3],
        'tischtennis' => ['label' => 'Tischtennis', 'met' => 4.0],
        'badminton' => ['label' => 'Badminton', 'met' => 5.5],
        'squash' => ['label' => 'Squash', 'met' => 12.0],
        'golf' => ['label' => 'Golf', 'met' => 4.8],
        'reiten' => ['label' => 'Reiten', 'met' => 5.5],
        'sonstiges' => ['label' => 'Sonstiges', 'met' => 5.0],
    ];
}

function metFor(string $key): ?float
{
    $t = metTable();
    return isset($t[$key]) ? (float) $t[$key]['met'] : null;
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
