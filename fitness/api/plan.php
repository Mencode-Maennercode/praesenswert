<?php
/**
 * Der Wochenplan.
 *
 *   setup      Einstellungen speichern (einmalig, danach änderbar)
 *   erzeugen   Woche vom Modell planen lassen
 *   holen      aktuellen Plan samt Rezepten
 *   tauschen   ein einzelnes Gericht neu würfeln
 *   gegessen   ein geplantes Gericht in den Tag eintragen
 *   liste      Einkaufsliste erzeugen und teilbar machen
 *
 * Die Kalorien eines geplanten Gerichts sind VORHER bekannt. Damit dreht
 * sich die Logik der ganzen App um: Statt hinterher zu schätzen, was auf
 * dem Teller lag, bestätigt man, was geplant war - ein Tipp, keine
 * Rückfrage, kein Schätzfehler.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';
require __DIR__ . '/_ai.php';
require __DIR__ . '/_plan.php';

cors();
requireMethod('POST');

$body = jsonBody(64 * 1024);
[$uid, $user] = requireUser($body);

if (!is_array($user['derived'] ?? null)) {
    fail('Erst das Onboarding abschließen.', 409, 'kein-profil');
}

match (clean($body['action'] ?? '', 20)) {
    'setup' => setup($uid, $user, $body),
    'erzeugen' => erzeugen($uid, $user, $body),
    'holen' => holen($uid, $user, $body),
    'tauschen' => tauschen($uid, $user, $body),
    'gegessen' => gegessen($uid, $user, $body),
    'vorrat' => vorrat($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

/* --------------------------------------------------------- Einstellungen */

function setup(string $uid, array $user, array $body): never
{
    $e = is_array($body['plan'] ?? null) ? $body['plan'] : [];

    $mahlzeiten = [];
    foreach (['fruehstueck', 'mittag', 'abend', 'snack'] as $m) {
        if (in_array($m, (array) ($e['mahlzeiten'] ?? []), true)) {
            $mahlzeiten[] = $m;
        }
    }
    if ($mahlzeiten === []) {
        $mahlzeiten = ['mittag', 'abend'];
    }

    $user['planPrefs'] = [
        'mahlzeiten' => $mahlzeiten,
        'erwachsene' => asInt($e['erwachsene'] ?? 2, 1, 8, 2),
        'kinder' => asInt($e['kinder'] ?? 0, 0, 8, 0),
        'umfang' => in_array($e['umfang'] ?? '', ['wenig', 'mittel', 'viel'], true) ? $e['umfang'] : 'mittel',
        'budget' => in_array($e['budget'] ?? '', ['guenstig', 'mittel', 'teuer'], true) ? $e['budget'] : 'mittel',
        'einkaufstag' => asInt($e['einkaufstag'] ?? 6, 1, 7, 6),
        'ernaehrung' => clean($e['ernaehrung'] ?? '', 80),
        'abneigungen' => clean($e['abneigungen'] ?? '', 200),
        'zeitWerktag' => asInt($e['zeitWerktag'] ?? 30, 10, 120, 30),
        'zeitWochenende' => asInt($e['zeitWochenende'] ?? 60, 10, 180, 60),
        'geraete' => clean($e['geraete'] ?? '', 120),
        'reste' => (bool) ($e['reste'] ?? true),
    ];
    saveUser($uid, $user);

    send(withFreshToken(['ok' => true, 'plan' => $user['planPrefs']], $uid, $user, $body));
}

function vorrat(string $uid, array $user, array $body): never
{
    $liste = [];
    foreach ((array) ($body['vorrat'] ?? []) as $v) {
        $n = mb_strtolower(clean($v, 60));
        if ($n !== '') {
            $liste[] = $n;
        }
    }
    $user['vorrat'] = array_values(array_unique(array_slice($liste, 0, 80)));
    saveUser($uid, $user);
    send(withFreshToken(['ok' => true, 'vorrat' => $user['vorrat']], $uid, $user, $body));
}

/* ------------------------------------------------------------- Erzeugen */

function wocheKey(int $ts): string
{
    return date('o-\WW', $ts);
}

function planPfad(string $uid, string $woche): string
{
    return PLANS_DIR . '/' . $uid . '/' . $woche . '.json';
}

function schema(array $prefs): array
{
    return [
        'type' => 'OBJECT',
        'properties' => [
            'gerichte' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'tag' => ['type' => 'INTEGER'],
                        'mahlzeit' => ['type' => 'STRING', 'enum' => $prefs['mahlzeiten']],
                        'titel' => ['type' => 'STRING'],
                        'zeitMin' => ['type' => 'INTEGER'],
                        'kcal' => ['type' => 'INTEGER'],
                        'eiweiss_g' => ['type' => 'NUMBER'],
                        'kohlenhydrate_g' => ['type' => 'NUMBER'],
                        'fett_g' => ['type' => 'NUMBER'],
                        'reste' => ['type' => 'BOOLEAN'],
                        'schritte' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                        'zutaten' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'menge' => ['type' => 'NUMBER'],
                                    'einheit' => ['type' => 'STRING'],
                                    'gruppe' => ['type' => 'STRING', 'enum' => array_keys(warengruppen())],
                                ],
                                'required' => ['name', 'menge', 'einheit', 'gruppe'],
                            ],
                        ],
                    ],
                    'required' => ['tag', 'mahlzeit', 'titel', 'zeitMin', 'kcal', 'eiweiss_g',
                        'kohlenhydrate_g', 'fett_g', 'reste', 'schritte', 'zutaten'],
                ],
            ],
        ],
        'required' => ['gerichte'],
    ];
}

function anweisung(array $user, array $prefs, array $bekannt): string
{
    $d = $user['derived'];
    $tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $einkauf = $tage[$prefs['einkaufstag'] - 1];

    $anteile = [
        'fruehstueck' => 25, 'mittag' => 35, 'abend' => 30, 'snack' => 10,
    ];
    $summe = 0;
    foreach ($prefs['mahlzeiten'] as $m) {
        $summe += $anteile[$m];
    }
    $teile = [];
    foreach ($prefs['mahlzeiten'] as $m) {
        $teile[] = $m . ': ca. ' . (int) round($d['goalKcal'] * $anteile[$m] / $summe) . ' kcal';
    }
    $verteilung = implode(' | ', $teile);

    $personen = $prefs['erwachsene'] . ' Erwachsene'
        . ($prefs['kinder'] > 0 ? ' und ' . $prefs['kinder'] . ' Kinder' : '');

    $abneigung = $prefs['abneigungen'] !== '' ? "Mag nicht: {$prefs['abneigungen']}." : '';
    $ernaehrung = $prefs['ernaehrung'] !== '' ? "Ernährung: {$prefs['ernaehrung']}." : '';
    $geraete = $prefs['geraete'] !== '' ? "Vorhandene Geräte: {$prefs['geraete']}." : '';
    $reste = $prefs['reste']
        ? "Plane Reste ein: Ein Gericht darf für zwei Tage reichen. Setze dann am Folgetag denselben Titel und 'reste' auf true."
        : "Keine Resteverwertung.";
    $altbekannt = $bekannt !== []
        ? "Diese Gerichte gab es zuletzt, nimm sie NICHT wieder: " . implode(', ', array_slice($bekannt, 0, 25)) . '.'
        : '';

    $umfang = match ($prefs['umfang']) {
        'wenig' => 'Wenig einkaufen: Zutaten sollen sich über mehrere Gerichte wiederholen.',
        'viel' => 'Abwechslung ist wichtiger als eine kurze Einkaufsliste.',
        default => 'Ausgewogen zwischen Abwechslung und überschaubarem Einkauf.',
    };
    $budget = match ($prefs['budget']) {
        'guenstig' => 'Günstig kochen: Grundzutaten, Hülsenfrüchte, Saisonales, wenig Fleisch.',
        'teuer' => 'Qualität darf kosten.',
        default => 'Normales Budget.',
    };

    return <<<TEXT
        Du planst eine Woche Essen für ein privates Ernährungstagebuch.
        Antworte auf Deutsch.

        Gekocht wird für {$personen}.

        WICHTIG: Nährwerte UND Zutatenmengen gelten beide für EINE
        Erwachsenenportion. Rechne nicht auf den Haushalt hoch - das macht
        der Server, der die genaue Zahl der Esser kennt. Zwei Stellen, die
        beide skalieren, ergeben sonst die dreifache Menge.

        Tagesziel {$d['goalKcal']} kcal, Eiweiß {$d['proteinG']} g.
        Verteilung: {$verteilung}

        {$ernaehrung}
        {$abneigung}
        {$geraete}
        {$umfang}
        {$budget}

        Zeit: werktags höchstens {$prefs['zeitWerktag']} Minuten, am Wochenende
        bis {$prefs['zeitWochenende']} Minuten.

        Eingekauft wird am {$einkauf}. 'tag' ist 1 für Montag bis 7 für Sonntag.
        Ordne die Gerichte nach Haltbarkeit: Fisch, Blattsalat und Hackfleisch
        direkt nach dem Einkaufstag, Wurzelgemüse, Hülsenfrüchte und Tiefkühl
        ans Ende der Woche. Sonst wird weggeworfen, was frisch gekauft wurde.

        {$reste}
        {$altbekannt}

        Für jede Zutat die passende Warengruppe angeben - danach wird die
        Einkaufsliste in der Reihenfolge des Supermarkts sortiert.

        'schritte' sind drei bis acht kurze Sätze, je ein Arbeitsschritt.
        TEXT;
}

function erzeugen(string $uid, array $user, array $body): never
{
    rateLimit('plan', 6, 3600, $uid);

    $prefs = is_array($user['planPrefs'] ?? null) ? $user['planPrefs'] : null;
    if ($prefs === null) {
        fail('Erst die Planung einrichten.', 409, 'kein-setup');
    }

    [$erlaubt, $user] = aiUserBudget($user);
    $key = aiKey();
    if (!$erlaubt || $key === '' || !aiBudgetAvailable()) {
        saveUser($uid, $user);
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'budget'], $uid, $user, $body));
    }
    saveUser($uid, $user);

    // Was es zuletzt gab, kommt nicht wieder - sonst gibt es jede Woche
    // dasselbe, und der Plan wird nach drei Wochen ignoriert.
    $bekannt = [];
    foreach (array_slice(glob(PLANS_DIR . '/' . $uid . '/*.json') ?: [], -2) as $f) {
        foreach (readJson($f)['gerichte'] ?? [] as $g) {
            $bekannt[] = (string) ($g['titel'] ?? '');
        }
    }

    $roh = geminiJson($key, [['text' => 'Plane die Woche.']], schema($prefs), anweisung($user, $prefs, $bekannt), $status);
    if ($roh === null || !is_array($roh['gerichte'] ?? null)) {
        send(withFreshToken([
            'ok' => true,
            'ki' => false,
            'grund' => $status === 429 ? 'gebremst' : 'fehler',
        ], $uid, $user, $body));
    }

    $woche = wocheKey(time());
    $plan = planAusRoh($roh['gerichte'], $prefs, $user);
    $plan['woche'] = $woche;
    $plan['erzeugtAm'] = date('c');

    writeJson(planPfad($uid, $woche), $plan);
    send(withFreshToken(['ok' => true, 'ki' => true, ...planPaket($plan)], $uid, $user, $body));
}

/**
 * Prüft und klemmt, was das Modell geliefert hat.
 *
 * Ein Rezept mit unmöglichen Mengen schleppt sich sonst über die
 * Einkaufsliste durch die ganze Woche - man merkt es erst im Laden.
 */
function planAusRoh(array $gerichte, array $prefs, array $user): array
{
    $ziel = (int) $user['derived']['goalKcal'];
    $out = [];

    foreach (array_slice($gerichte, 0, 28) as $g) {
        $tag = asInt($g['tag'] ?? 1, 1, 7, 1);
        $mahlzeit = (string) ($g['mahlzeit'] ?? '');
        if (!in_array($mahlzeit, $prefs['mahlzeiten'], true)) {
            continue;
        }

        $titel = clean($g['titel'] ?? '', 70);
        if ($titel === '') {
            continue;
        }

        $werte = klammereNaehrwerte([
            'kcal' => $g['kcal'] ?? 0,
            'eiweiss_g' => $g['eiweiss_g'] ?? 0,
            'kohlenhydrate_g' => $g['kohlenhydrate_g'] ?? 0,
            'fett_g' => $g['fett_g'] ?? 0,
        ]);
        // Eine einzelne Mahlzeit über dem ganzen Tagesziel ist ein Fehler
        // des Modells, kein Gericht.
        if ($werte['kcal'] > $ziel) {
            $werte['kcal'] = (int) round($ziel * 0.45);
        }

        $zutaten = [];
        foreach (array_slice((array) ($g['zutaten'] ?? []), 0, 20) as $z) {
            $name = clean($z['name'] ?? '', 60);
            if ($name === '') {
                continue;
            }
            $zutaten[] = [
                'name' => $name,
                'menge' => max(0.0, min(5000.0, (float) ($z['menge'] ?? 0))),
                'einheit' => clean($z['einheit'] ?? 'Stück', 12),
                'gruppe' => isset(warengruppen()[$z['gruppe'] ?? '']) ? $z['gruppe'] : 'sonstiges',
            ];
        }
        if ($zutaten === []) {
            continue;
        }

        $schritte = [];
        foreach (array_slice((array) ($g['schritte'] ?? []), 0, 10) as $s) {
            $t = clean($s, 240);
            if ($t !== '') {
                $schritte[] = $t;
            }
        }

        $id = rezeptSpeichern([
            'titel' => $titel,
            'zeitMin' => asInt($g['zeitMin'] ?? 30, 5, 240, 30),
            ...$werte,
            'zutaten' => $zutaten,
            'schritte' => $schritte,
            'bild' => '',
        ]);

        $out[] = [
            'tag' => $tag,
            'mahlzeit' => $mahlzeit,
            'rezept' => $id,
            'titel' => $titel,
            'kcal' => $werte['kcal'],
            'reste' => (bool) ($g['reste'] ?? false),
            'gegessen' => false,
        ];
    }

    /*
     * Nach Tag, dann nach Tagesablauf - NICHT alphabetisch.
     *
     * Sortiert man die Mahlzeit als Text, steht "abend" vor "fruehstueck"
     * und "mittag". Der Plan liest sich dann rückwärts, und niemand
     * versteht sofort warum.
     */
    $reihenfolge = ['fruehstueck' => 0, 'mittag' => 1, 'abend' => 2, 'snack' => 3];
    usort($out, static function (array $a, array $b) use ($reihenfolge): int {
        return [$a['tag'], $reihenfolge[$a['mahlzeit']] ?? 9]
            <=> [$b['tag'], $reihenfolge[$b['mahlzeit']] ?? 9];
    });
    return ['gerichte' => $out];
}

/* ---------------------------------------------------------------- Lesen */

function planPaket(array $plan): array
{
    $rezepte = [];
    foreach ($plan['gerichte'] as $g) {
        $r = rezeptLaden((string) $g['rezept']);
        if ($r !== null) {
            $rezepte[$r['id']] = $r;
        }
    }
    return ['plan' => $plan, 'rezepte' => $rezepte];
}

function holen(string $uid, array $user, array $body): never
{
    $woche = clean($body['woche'] ?? '', 10) ?: wocheKey(time());
    $plan = readJson(planPfad($uid, $woche), []);

    send(withFreshToken([
        'ok' => true,
        'woche' => $woche,
        'prefs' => $user['planPrefs'] ?? null,
        'vorrat' => $user['vorrat'] ?? [],
        ...($plan === [] ? ['plan' => null, 'rezepte' => []] : planPaket($plan)),
    ], $uid, $user, $body));
}

/* -------------------------------------------------------------- Tauschen */

function tauschen(string $uid, array $user, array $body): never
{
    rateLimit('plan', 20, 3600, $uid);

    $woche = clean($body['woche'] ?? '', 10) ?: wocheKey(time());
    $tag = asInt($body['tag'] ?? 0, 1, 7, 0);
    $mahlzeit = clean($body['mahlzeit'] ?? '', 20);

    $plan = readJson(planPfad($uid, $woche), []);
    if ($plan === []) {
        fail('Kein Plan da.', 404, 'weg');
    }

    $prefs = is_array($user['planPrefs'] ?? null) ? $user['planPrefs'] : [];
    [$erlaubt, $user] = aiUserBudget($user);
    $key = aiKey();
    if (!$erlaubt || $key === '') {
        saveUser($uid, $user);
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'budget'], $uid, $user, $body));
    }
    saveUser($uid, $user);

    $bekannt = array_map(static fn($g) => (string) $g['titel'], $plan['gerichte']);
    $einzel = schema($prefs);

    $roh = geminiJson(
        $key,
        [['text' => "Ersetze NUR das Gericht an Tag {$tag} für '{$mahlzeit}'. Gib genau ein Gericht zurück."]],
        $einzel,
        anweisung($user, $prefs, $bekannt),
        $status,
    );

    if ($roh === null || !is_array($roh['gerichte'] ?? null) || $roh['gerichte'] === []) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'fehler'], $uid, $user, $body));
    }

    $neu = planAusRoh([$roh['gerichte'][0] + ['tag' => $tag, 'mahlzeit' => $mahlzeit]], $prefs, $user);
    if ($neu['gerichte'] === []) {
        send(withFreshToken(['ok' => true, 'ki' => false, 'grund' => 'fehler'], $uid, $user, $body));
    }

    foreach ($plan['gerichte'] as $i => $g) {
        if ((int) $g['tag'] === $tag && $g['mahlzeit'] === $mahlzeit) {
            $plan['gerichte'][$i] = $neu['gerichte'][0];
            break;
        }
    }
    writeJson(planPfad($uid, $woche), $plan);

    send(withFreshToken(['ok' => true, 'ki' => true, ...planPaket($plan)], $uid, $user, $body));
}

/* ------------------------------------------------------------- Gegessen */

/**
 * Ein geplantes Gericht in den Tag eintragen.
 *
 * Das ist der Punkt, an dem der Plan seinen Wert entfaltet: Die Werte
 * stehen fest, es gibt nichts zu schätzen und nichts zu bestätigen.
 */
function gegessen(string $uid, array $user, array $body): never
{
    rateLimit('eintrag', 120, 600, $uid);

    $rezept = rezeptLaden(clean($body['rezept'] ?? '', 20));
    if ($rezept === null) {
        fail('Rezept nicht gefunden.', 404, 'weg');
    }

    $faktor = asFloat($body['faktor'] ?? 1, 0.25, 3, 1);
    $mahlzeit = clean($body['mahlzeit'] ?? '', 20);
    $slot = match ($mahlzeit) {
        'fruehstueck' => 'fruehstueck',
        'mittag' => 'mittag',
        'abend' => 'abend',
        default => 'snack',
    };

    $derived = $user['derived'];
    $startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
    $datum = dayKey($startStunde);

    $eintrag = [
        'id' => 'm_' . bin2hex(random_bytes(6)),
        'at' => date('c'),
        'source' => 'plan',
        'title' => (string) $rezept['titel'],
        'slot' => $slot,
        'kcal' => (int) round((int) $rezept['kcal'] * $faktor),
        'p' => round((float) $rezept['p'] * $faktor, 1),
        'c' => round((float) $rezept['c'] * $faktor, 1),
        'f' => round((float) $rezept['f'] * $faktor, 1),
    ];

    $tag = withLock('day-' . $uid . '-' . $datum, static function () use ($uid, $datum, $derived, $eintrag): array {
        $t = purgeTrash(loadDay($uid, $datum));
        $t['meals'][] = $eintrag;
        return saveDay($uid, $t, $derived);
    });

    $user = merkeHaeufig($user, $eintrag);
    $user = serieFortschreiben($user, $datum);
    saveUser($uid, $user);
    updateFeed($uid, $user, $tag);

    // Im Plan abhaken, damit man sieht, was schon gegessen ist.
    $woche = clean($body['woche'] ?? '', 10) ?: wocheKey(time());
    $planDatei = planPfad($uid, $woche);
    $plan = readJson($planDatei, []);
    if ($plan !== []) {
        foreach ($plan['gerichte'] as $i => $g) {
            if ($g['rezept'] === $rezept['id'] && $g['mahlzeit'] === $mahlzeit) {
                $plan['gerichte'][$i]['gegessen'] = true;
                break;
            }
        }
        writeJson($planDatei, $plan);
    }

    send(withFreshToken(['ok' => true, 'tag' => $tag, 'eintrag' => $eintrag], $uid, $user, $body));
}
