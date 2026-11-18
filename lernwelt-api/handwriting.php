<?php
/**
 * handwriting.php – Bild des Geschriebenen + Sollwort → Erkennung, Fehler mit Position.
 * Das Bild wird nicht gespeichert.
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
    Du prüfst Handschrift eines Grundschulkindes (Klasse {$grade}, Ausgangsschrift: {$schrift}) auf einem Tablet.
    Das Kind sollte genau schreiben: "{$ziel}". Lies, was WIRKLICH dasteht – Buchstabe für Buchstabe,
    auch Groß/Klein. Korrigiere nichts stillschweigend und rate nicht das Sollwort herbei.
    - 'recognized': das tatsächlich Geschriebene.
    - 'errors': je abweichender Stelle ein Eintrag: 'index' = Zeichenindex im Sollwort (ab 0),
      'expected' = Soll-Buchstabe(n), 'written' = geschriebene(r) Buchstabe(n) ("" wenn fehlt),
      'kind' = Fehlerart als ID, z. B. deutsch.gross_klein, deutsch.buchstabe_fehlt, deutsch.buchstabe_zuviel,
      deutsch.buchstabe_vertauscht, deutsch.spiegelschrift, deutsch.doppelkonsonant, deutsch.dehnung_ie,
      deutsch.dehnungs_h, deutsch.auslautverhaertung, deutsch.ck_tz, deutsch.sp_st, deutsch.ei_ie,
      deutsch.umlaut, deutsch.s_laut, deutsch.sonstiges,
      'box' = horizontale Lage der Stelle im Bild, normalisiert 0–1 (x = linker Rand, w = Breite),
      'explanation' = ein kurzer, freundlicher Satz fürs Kind (max. 120 Zeichen), ohne Tadel.
    - 'legibility': Leserlichkeit 0–1.
    Bei richtig geschriebenem Wort ist 'errors' leer.
    TEXT;

$roh = geminiJson([
    ['inline_data' => ['mime_type' => $mime, 'data' => $bild]],
    ['text' => "Sollwort: $ziel"],
], [
    'type' => 'OBJECT',
    'properties' => [
        'recognized' => ['type' => 'STRING'],
        'errors' => ['type' => 'ARRAY', 'items' => [
            'type' => 'OBJECT',
            'properties' => [
                'index' => ['type' => 'INTEGER'],
                'expected' => ['type' => 'STRING'],
                'written' => ['type' => 'STRING'],
                'kind' => ['type' => 'STRING'],
                'box' => ['type' => 'OBJECT', 'properties' => ['x' => ['type' => 'NUMBER'], 'w' => ['type' => 'NUMBER']], 'required' => ['x', 'w']],
                'explanation' => ['type' => 'STRING'],
            ],
            'required' => ['index', 'expected', 'written', 'kind', 'box', 'explanation'],
        ]],
        'legibility' => ['type' => 'NUMBER'],
    ],
    'required' => ['recognized', 'errors', 'legibility'],
    'propertyOrdering' => ['recognized', 'errors', 'legibility'],
], $anweisung, KETTE_BILD, 0.1);

$erkannt = clean($roh['recognized'] ?? '', 80);
$laenge = max(1, mb_strlen($ziel));
$fehler = [];
foreach (array_slice(is_array($roh['errors'] ?? null) ? $roh['errors'] : [], 0, 20) as $e) {
    if (!is_array($e)) {
        continue;
    }
    $x = max(0.0, min(1.0, (float) ($e['box']['x'] ?? 0)));
    $fehler[] = [
        'index' => max(0, min($laenge - 1, (int) ($e['index'] ?? 0))),
        'expected' => clean($e['expected'] ?? '', 10),
        'written' => clean($e['written'] ?? '', 10),
        'kind' => isMisconceptionId($e['kind'] ?? null) ? $e['kind'] : 'deutsch.sonstiges',
        'box' => ['x' => round($x, 3), 'w' => round(max(0.0, min(1.0 - $x, (float) ($e['box']['w'] ?? 0))), 3)],
        'explanation' => clean($e['explanation'] ?? '', 160),
    ];
}
$richtig = $erkannt === $ziel;

send([
    'recognized' => $erkannt,
    'correct' => $richtig,
    'errors' => $richtig ? [] : $fehler,
    'legibility' => round(max(0.0, min(1.0, (float) ($roh['legibility'] ?? 0.5))), 2),
]);
