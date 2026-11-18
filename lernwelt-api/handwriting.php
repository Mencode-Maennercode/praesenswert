<?php
/**
 * handwriting.php – Bild des Geschriebenen + Sollwort → Erkennung, Fehler mit Position.
 * Das Bild wird nicht gespeichert.
 *
 * Das Modell bekommt das Sollwort NICHT zu sehen: Kleine Modelle „erkennen“ sonst
 * zuverlässig das Sollwort, auch wenn etwas anderes (oder nichts) dasteht.
 * Es liest blind Buchstabe für Buchstabe mit Lage; den Vergleich mit dem
 * Sollwort macht der Server (Levenshtein-Ausrichtung).
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

cors();
$uid = requireUser();
$b = jsonBody(3_000_000);

[$bild, $mime] = pruefeBild($b['image'] ?? null, ['image/png']);
$ziel = clean($b['target'] ?? '', 60);
if ($ziel === '') {
    fail('target fehlt.');
}
$grade = grade($b['grade'] ?? 0);
$schrift = clean($b['schrift'] ?? '', 30);

budgetBuchen($uid, 'handwriting');

$anweisung = <<<TEXT
    Du liest Handschrift eines Grundschulkindes (Klasse {$grade}, Ausgangsschrift: {$schrift}) von einem Tablet.
    Lies GENAU, was dasteht – Buchstabe für Buchstabe, mit Groß-/Kleinschreibung, auch wenn es kein
    richtiges Wort ist. Ergänze oder verbessere nichts. Spiegelverkehrte Buchstaben als der gemeinte
    Buchstabe lesen und in 'mirrored' angeben.
    - 'letters': jeder geschriebene Buchstabe von links nach rechts mit 'char' und seiner horizontalen
      Lage im Bild, normalisiert 0–1 ('x' linker Rand, 'w' Breite).
    - 'legibility': Leserlichkeit 0–1.
    Ist nichts Handgeschriebenes zu sehen (leer, Gekritzel, Foto, Symbol), gib 'letters' leer und 'legibility' 0.
    TEXT;

$roh = aiJson([bildTeil($bild, $mime), textTeil('Was steht hier geschrieben?')], [
    'type' => 'object',
    'properties' => [
        'letters' => ['type' => 'array', 'items' => [
            'type' => 'object',
            'properties' => [
                'char' => ['type' => 'string'],
                'x' => ['type' => 'number'],
                'w' => ['type' => 'number'],
                'mirrored' => ['type' => 'boolean'],
            ],
            'required' => ['char', 'x', 'w', 'mirrored'],
            'additionalProperties' => false,
        ]],
        'legibility' => ['type' => 'number'],
    ],
    'required' => ['letters', 'legibility'],
    'additionalProperties' => false,
], $anweisung, KETTE_BILD, 0.0);

/* ------------------------------------------------- Buchstaben säubern */

$buchstaben = [];
foreach (array_slice(is_array($roh['letters'] ?? null) ? $roh['letters'] : [], 0, 60) as $l) {
    $c = is_array($l) ? mb_substr(clean($l['char'] ?? '', 4), 0, 1) : '';
    if ($c === '' || $c === ' ') {
        continue;
    }
    $x = max(0.0, min(1.0, (float) ($l['x'] ?? 0)));
    $buchstaben[] = ['c' => $c, 'x' => $x, 'w' => max(0.0, min(1.0 - $x, (float) ($l['w'] ?? 0))), 'm' => ($l['mirrored'] ?? false) === true];
}
$erkannt = implode('', array_column($buchstaben, 'c'));
$soll = mb_str_split($ziel);
$ist = array_column($buchstaben, 'c');

/* ------------------------------------------- Ausrichtung Soll ↔ Ist */

$n = count($soll);
$m = count($ist);
$d = [];
for ($i = 0; $i <= $n; $i++) {
    $d[$i][0] = $i;
}
for ($j = 0; $j <= $m; $j++) {
    $d[0][$j] = $j;
}
for ($i = 1; $i <= $n; $i++) {
    for ($j = 1; $j <= $m; $j++) {
        $kosten = $soll[$i - 1] === $ist[$j - 1] ? 0 : (mb_strtolower($soll[$i - 1]) === mb_strtolower($ist[$j - 1]) ? 0.5 : 1);
        $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $kosten);
    }
}
$schritte = []; // [typ, sollIndex, istIndex]
for ($i = $n, $j = $m; $i > 0 || $j > 0;) {
    if ($i > 0 && $j > 0) {
        $kosten = $soll[$i - 1] === $ist[$j - 1] ? 0 : (mb_strtolower($soll[$i - 1]) === mb_strtolower($ist[$j - 1]) ? 0.5 : 1);
        if (abs($d[$i][$j] - ($d[$i - 1][$j - 1] + $kosten)) < 1e-9) {
            array_unshift($schritte, [$kosten === 0 ? 'gleich' : 'ersetzt', $i - 1, $j - 1]);
            $i--;
            $j--;
            continue;
        }
    }
    if ($i > 0 && abs($d[$i][$j] - ($d[$i - 1][$j] + 1)) < 1e-9) {
        array_unshift($schritte, ['fehlt', $i - 1, $j]);
        $i--;
    } else {
        array_unshift($schritte, ['zuviel', $i, $j - 1]);
        $j--;
    }
}

/* ------------------------------------------------------- Fehlerliste */

$box = function (int $j) use ($buchstaben, $m): array {
    if ($m === 0) {
        return ['x' => 0.0, 'w' => 1.0];
    }
    if ($j >= $m) { // fehlt am Ende: hinter dem letzten Buchstaben
        $l = $buchstaben[$m - 1];
        $x = min(1.0, $l['x'] + $l['w']);
        return ['x' => round($x, 3), 'w' => round(min(0.08, 1.0 - $x), 3)];
    }
    return ['x' => round($buchstaben[$j]['x'], 3), 'w' => round($buchstaben[$j]['w'], 3)];
};

$fehler = [];
foreach ($schritte as [$typ, $si, $ij]) {
    if ($typ === 'gleich') {
        if ($buchstaben[$ij]['m']) {
            $fehler[] = ['index' => $si, 'expected' => $soll[$si], 'written' => $ist[$ij], 'kind' => 'deutsch.spiegelschrift', 'box' => $box($ij),
                'explanation' => "Das {$soll[$si]} ist andersherum geschrieben. Schau, in welche Richtung sein Bauch zeigt."];
        }
        continue;
    }
    $index = max(0, min($n - 1, $si));
    $e = $soll[$index] ?? '';
    $fehler[] = match ($typ) {
        'ersetzt' => mb_strtolower($soll[$si]) === mb_strtolower($ist[$ij])
            ? ['index' => $si, 'expected' => $soll[$si], 'written' => $ist[$ij], 'kind' => 'deutsch.gross_klein', 'box' => $box($ij),
                'explanation' => $si === 0 ? "Am Anfang schreibst du hier ein großes {$soll[$si]}." : "Hier gehört ein kleines {$soll[$si]} hin."]
            : ['index' => $si, 'expected' => $soll[$si], 'written' => $ist[$ij], 'kind' => 'deutsch.buchstabe_vertauscht', 'box' => $box($ij),
                'explanation' => "Hier gehört ein {$soll[$si]} hin, nicht ein {$ist[$ij]}."],
        'fehlt' => ['index' => $si, 'expected' => $soll[$si], 'written' => '', 'kind' => 'deutsch.buchstabe_fehlt', 'box' => $box($ij),
            'explanation' => "Hier fehlt noch das {$soll[$si]}."],
        default => ['index' => $index, 'expected' => '', 'written' => $ist[$ij], 'kind' => 'deutsch.buchstabe_zuviel', 'box' => $box($ij),
            'explanation' => "Das {$ist[$ij]} ist hier zu viel."],
    };
}

if ($m === 0) {
    $fehler = []; // nichts lesbar: keine Fehlerliste, der Client bittet ums Neuschreiben
}
$richtig = $erkannt === $ziel && $fehler === [];
send([
    'recognized' => $erkannt,
    'correct' => $richtig,
    'errors' => array_slice($fehler, 0, 20),
    'legibility' => round(max(0.0, min(1.0, (float) ($roh['legibility'] ?? 0))), 2),
]);
