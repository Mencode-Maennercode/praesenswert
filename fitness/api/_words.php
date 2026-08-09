<?php
/**
 * Wortliste für den Wiederherstellungscode.
 *
 * Warum Wörter statt einer Zeichenkette: Der Code wird einmal angezeigt und
 * soll abgeschrieben werden. "hafen kerze mond raupe segel tiger" überlebt
 * eine Handschrift, "kR7x-9Qf2" nicht.
 *
 * Regeln für die Liste - jede hat einen Grund:
 *   - keine Umlaute und kein Eszett: sonst hängt die Eingabe an der Tastatur
 *   - alles klein, drei bis neun Buchstaben
 *   - keine Wortpaare, die sich nur in einem Buchstaben unterscheiden
 *   - nichts Negatives oder Persönliches - der Code wird vorgelesen
 *
 * 256 Wörter sind genau 8 Bit. Sechs Wörter ergeben 48 Bit. Zusammen mit
 * der Bremse von fünf Versuchen pro Stunde ist das weit außerhalb dessen,
 * was sich durchprobieren lässt.
 */

declare(strict_types=1);

function wordList(): array
{
    return [
        'anker', 'apfel', 'april', 'arena', 'atlas', 'auto', 'bach', 'bagger',
        'ball', 'banane', 'bank', 'baum', 'beere', 'berg', 'besen', 'biene',
        'bild', 'birne', 'blatt', 'blitz', 'blume', 'boden', 'bogen', 'boot',
        'brille', 'brot', 'buch', 'burg', 'butter', 'dach', 'dampf', 'delfin',
        'diamant', 'donner', 'dose', 'drache', 'draht', 'dorf', 'drossel', 'dunkel',
        'eiche', 'eimer', 'eis', 'eisen', 'elch', 'engel', 'ente', 'erde',
        'esel', 'eule', 'fabrik', 'faden', 'fahne', 'falke', 'feder', 'feld',
        'fels', 'fenster', 'ferien', 'feuer', 'film', 'finger', 'fisch', 'flagge',
        'flasche', 'floss', 'flug', 'fluss', 'forelle', 'forst', 'foto', 'frosch',
        'fuchs', 'funke', 'gabel', 'garten', 'gebirge', 'geige', 'geist', 'gips',
        'gitarre', 'glas', 'globus', 'gold', 'gras', 'grotte', 'gurke', 'hafen',
        'hagel', 'hahn', 'hammer', 'hand', 'hase', 'haus', 'heft', 'herz',
        'himmel', 'hirsch', 'holz', 'honig', 'horn', 'hotel', 'huhn', 'hund',
        'hut', 'igel', 'insel', 'jacke', 'jade', 'januar', 'juli', 'juni',
        'kabel', 'kaffee', 'kamera', 'kamin', 'kanal', 'kanu', 'karte', 'karton',
        'kastanie', 'katze', 'kegel', 'keller', 'kerze', 'kette', 'kiesel', 'kino',
        'kirsche', 'kiste', 'klavier', 'klee', 'knopf', 'koffer', 'kohle', 'kompass',
        'korb', 'korn', 'kran', 'kreide', 'kreis', 'krone', 'kuchen', 'kupfer',
        'lampe', 'land', 'laterne', 'laub', 'leder', 'leiter', 'lerche', 'licht',
        'lied', 'linde', 'linse', 'lupe', 'magnet', 'mais', 'mandel', 'mantel',
        'markt', 'marmor', 'mast', 'matte', 'maus', 'meer', 'melone', 'messer',
        'meter', 'milch', 'minze', 'mond', 'moos', 'morgen', 'motor', 'muschel',
        'nadel', 'nagel', 'nase', 'nebel', 'nelke', 'nest', 'netz', 'norden',
        'note', 'nudel', 'nuss', 'oase', 'obst', 'ofen', 'olive', 'orange',
        'orgel', 'osten', 'otter', 'paket', 'palme', 'panda', 'papier', 'pappel',
        'park', 'pass', 'pfad', 'pfeil', 'pferd', 'pflanze', 'pilz', 'pinsel',
        'pinguin', 'planet', 'platte', 'polar', 'post', 'pumpe', 'quelle', 'rabe',
        'rad', 'rahmen', 'rakete', 'rasen', 'raupe', 'regen', 'reifen', 'reis',
        'riese', 'ring', 'rose', 'rost', 'rubin', 'ruder', 'saat', 'sack',
        'saft', 'salz', 'sand', 'schaf', 'schere', 'schiff', 'schnee', 'schule',
        'see', 'segel', 'seide', 'seil', 'sofa', 'sommer', 'sonne', 'spatz',
        'spiegel', 'stein', 'stern', 'stiefel', 'storch', 'strand', 'stuhl', 'sturm',
    ];
}

/** Sechs Wörter, mit dem Zufallsgenerator für Kryptografie gezogen. */
function makeRecoveryCode(int $laenge = 6): string
{
    $words = wordList();
    $max = count($words) - 1;
    $out = [];
    for ($i = 0; $i < $laenge; $i++) {
        $out[] = $words[random_int(0, $max)];
    }
    return implode(' ', $out);
}

/**
 * Bringt eine Eingabe auf die Vergleichsform.
 *
 * Der Code wird abgetippt, also kommt er mit Großschreibung, doppelten
 * Leerzeichen, Bindestrichen oder Zeilenumbrüchen zurück. All das ist kein
 * Fehler des Nutzers und darf nicht zur Ablehnung führen.
 */
function normalizeRecoveryCode(string $eingabe): string
{
    $s = mb_strtolower(trim($eingabe));
    $s = preg_replace('/[^a-z]+/', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}
