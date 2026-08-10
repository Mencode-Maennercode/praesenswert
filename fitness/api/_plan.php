<?php
/**
 * Wochenplan und Einkaufsliste - die gemeinsamen Bausteine.
 *
 * Der Kern ist die Sortierung der Einkaufsliste. Sie folgt dem Weg durch
 * einen deutschen Supermarkt, vom Eingang bis zur Kasse - nicht dem
 * Alphabet und nicht der Reihenfolge der Rezepte. Wer mit einer nach
 * Rezepten sortierten Liste einkaufen geht, läuft den Laden dreimal ab.
 */

declare(strict_types=1);

/**
 * Die Warengruppen in der Reihenfolge, in der man sie im Laden trifft.
 *
 * Diese Abfolge ist in fast jedem deutschen Supermarkt gleich: Obst und
 * Gemüse stehen am Eingang, Tiefkühl kurz vor der Kasse, damit es nicht
 * auftaut, und die Getränke ganz hinten, weil sie schwer sind.
 *
 * Der Schlüssel ist gleichzeitig das, was das Sprachmodell zurückgeben
 * darf - als Enum im Schema. Damit kann keine Gruppe entstehen, für die
 * es keinen Platz im Laden gibt.
 */
function warengruppen(): array
{
    return [
        'obst_gemuese' => ['titel' => 'Obst & Gemüse', 'emoji' => '🥬'],
        'backwaren' => ['titel' => 'Brot & Backwaren', 'emoji' => '🥖'],
        'kuehlregal' => ['titel' => 'Kühlregal', 'emoji' => '🧀'],
        'fleisch_fisch' => ['titel' => 'Fleisch & Fisch', 'emoji' => '🥩'],
        'trocken' => ['titel' => 'Nudeln, Reis, Konserven', 'emoji' => '🍝'],
        'gewuerze' => ['titel' => 'Öl, Gewürze, Backen', 'emoji' => '🧂'],
        'suesses' => ['titel' => 'Snacks & Süßes', 'emoji' => '🍫'],
        'getraenke' => ['titel' => 'Getränke', 'emoji' => '🧃'],
        'tiefkuehl' => ['titel' => 'Tiefkühl', 'emoji' => '🧊'],
        'haushalt' => ['titel' => 'Haushalt & Drogerie', 'emoji' => '🧻'],
        'sonstiges' => ['titel' => 'Sonstiges', 'emoji' => '🛒'],
    ];
}

/**
 * Wie lange hält sich das?
 *
 * Entscheidet, an welchem Wochentag ein Gericht liegt. Fisch und
 * Blattsalat gehören direkt hinter den Einkauf, Wurzelgemüse und
 * Tiefkühl ans Ende der Woche. Ohne das wirft man am Freitag weg, was
 * man am Samstag gekauft hat.
 */
function frischeTage(string $gruppe): int
{
    return match ($gruppe) {
        'fleisch_fisch' => 2,
        'obst_gemuese', 'backwaren' => 3,
        'kuehlregal' => 6,
        default => 14,
    };
}

/* ------------------------------------------------------------- Einheiten */

/**
 * Bringt eine Menge auf eine Grundeinheit, damit sich Mengen addieren
 * lassen.
 *
 * "2 x 200 g" und "0,5 kg" müssen zu einer Zeile mit 900 g werden, nicht
 * zu drei Zeilen. Stückzahlen und Löffel bleiben, wie sie sind - 3 EL Öl
 * plus 100 ml Öl sinnvoll zusammenzuzählen geht nicht, und ein falsch
 * addierter Wert wäre schlimmer als zwei Zeilen.
 *
 * @return array{0: float, 1: string} [Menge, Einheit]
 */
function normEinheit(float $menge, string $einheit): array
{
    $e = mb_strtolower(trim($einheit));

    return match ($e) {
        'kg' => [$menge * 1000, 'g'],
        'g', 'gramm' => [$menge, 'g'],
        'l', 'liter' => [$menge * 1000, 'ml'],
        'ml' => [$menge, 'ml'],
        'el', 'esslöffel', 'esslöffel', 'essloeffel' => [$menge, 'EL'],
        'tl', 'teelöffel', 'teeloeffel' => [$menge, 'TL'],
        // "stueck" gehört dazu: Modelle liefern Umlaute mal ausgeschrieben.
        // Ohne diesen Fall stünde "36 stueck Eier" auf der Einkaufsliste.
        'stück', 'stueck', 'stk', 'st' => [$menge, 'Stück'],
        'bund' => [$menge, 'Bund'],
        'pck', 'packung', 'päckchen' => [$menge, 'Packung'],
        'dose' => [$menge, 'Dose'],
        'prise' => [$menge, 'Prise'],
        default => [$menge, $einheit === '' ? 'Stück' : mb_substr($einheit, 0, 12)],
    };
}

/** Wieder lesbar machen: 1500 g werden zu 1,5 kg. */
function schoeneMenge(float $menge, string $einheit): string
{
    if ($einheit === 'g' && $menge >= 1000) {
        return rtrim(rtrim(number_format($menge / 1000, 2, ',', ''), '0'), ',') . ' kg';
    }
    if ($einheit === 'ml' && $menge >= 1000) {
        return rtrim(rtrim(number_format($menge / 1000, 2, ',', ''), '0'), ',') . ' l';
    }
    $z = round($menge, 2);
    $text = $z == (int) $z ? (string) (int) $z : rtrim(rtrim(number_format($z, 2, ',', ''), '0'), ',');
    return $text . ' ' . $einheit;
}

/**
 * Fasst alle Zutaten der Woche zu einer Einkaufsliste zusammen.
 *
 * Zusammengelegt wird über Name UND Einheit: "Zwiebeln, 3 Stück" und
 * "Zwiebeln, 200 g" bleiben zwei Zeilen, weil niemand weiss, wie schwer
 * eine Zwiebel ist. Alles andere wäre geraten.
 *
 * @param array $rezepte  Rezepte mit 'zutaten' und 'portionen'
 * @param array $vorrat   Was schon da ist (kleingeschriebene Namen)
 */
function einkaufsliste(array $rezepte, array $vorrat = []): array
{
    $gesammelt = [];

    foreach ($rezepte as $r) {
        $faktor = max(0.1, (float) ($r['faktor'] ?? 1));

        foreach ($r['zutaten'] ?? [] as $z) {
            $name = clean($z['name'] ?? '', 60);
            if ($name === '') {
                continue;
            }
            if (in_array(mb_strtolower($name), $vorrat, true)) {
                continue;
            }

            $gruppe = (string) ($z['gruppe'] ?? 'sonstiges');
            if (!isset(warengruppen()[$gruppe])) {
                $gruppe = 'sonstiges';
            }

            [$menge, $einheit] = normEinheit(
                max(0.0, (float) ($z['menge'] ?? 0)) * $faktor,
                (string) ($z['einheit'] ?? ''),
            );

            $key = mb_strtolower($name) . '|' . $einheit;
            if (isset($gesammelt[$key])) {
                $gesammelt[$key]['menge'] += $menge;
            } else {
                $gesammelt[$key] = [
                    'id' => substr(md5($key), 0, 10),
                    'name' => $name,
                    'menge' => $menge,
                    'einheit' => $einheit,
                    'gruppe' => $gruppe,
                    'ab' => false,
                ];
            }
        }
    }

    // Nach Ladenweg sortieren, innerhalb der Gruppe alphabetisch.
    $reihenfolge = array_keys(warengruppen());
    uasort($gesammelt, static function (array $a, array $b) use ($reihenfolge): int {
        $ia = array_search($a['gruppe'], $reihenfolge, true);
        $ib = array_search($b['gruppe'], $reihenfolge, true);
        if ($ia !== $ib) {
            return $ia <=> $ib;
        }
        return strcoll($a['name'], $b['name']);
    });

    $out = [];
    foreach ($gesammelt as $e) {
        $e['text'] = $e['menge'] > 0 ? schoeneMenge($e['menge'], $e['einheit']) : '';
        $out[] = $e;
    }
    return $out;
}

/** Gruppiert die Liste für die Anzeige - in der Reihenfolge des Ladens. */
function nachGruppen(array $liste): array
{
    $gruppen = warengruppen();
    $out = [];

    foreach ($gruppen as $key => $meta) {
        $posten = array_values(array_filter($liste, static fn($e) => $e['gruppe'] === $key));
        if ($posten === []) {
            continue;
        }
        $out[] = ['key' => $key, 'titel' => $meta['titel'], 'emoji' => $meta['emoji'], 'posten' => $posten];
    }
    return $out;
}

/* ---------------------------------------------------------------- Rezepte */

function rezeptPfad(string $id): string
{
    return RECIPES_DIR . '/' . $id . '.json';
}

function rezeptLaden(string $id): ?array
{
    if (preg_match('/^r_[a-f0-9]{12}$/', $id) !== 1) {
        return null;
    }
    $r = readJson(rezeptPfad($id));
    return $r === [] ? null : $r;
}

/**
 * Legt ein Rezept ab - unter einem Namen, der sich aus dem Inhalt ergibt.
 *
 * Damit landet dasselbe Gericht nur einmal im Speicher, egal wie oft es
 * jemand erzeugt. Die Sammlung wächst mit der Zeit, und künftige Pläne
 * können darauf zurückgreifen, statt jedes Mal neu zu fragen.
 */
function rezeptSpeichern(array $rezept): string
{
    $id = 'r_' . substr(md5(mb_strtolower((string) $rezept['titel'])), 0, 12);
    $rezept['id'] = $id;

    $vorhanden = rezeptLaden($id);
    if ($vorhanden !== null) {
        // Bild und Herz nicht verlieren, wenn dasselbe Gericht neu kommt.
        $rezept['bild'] = $rezept['bild'] ?: (string) ($vorhanden['bild'] ?? '');
        $rezept['bildAlt'] = $vorhanden['bildAlt'] ?? [];
    }
    $rezept['updatedAt'] = date('c');
    writeJson(rezeptPfad($id), $rezept);
    return $id;
}
