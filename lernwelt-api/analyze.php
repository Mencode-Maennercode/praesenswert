<?php
/**
 * analyze.php – Lernprofil-Auswertung, 1× pro Sitzung. Nur Aggregate, keine Namen.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

cors();
$uid = requireUser();
$b = jsonBody(64_000);

$grade = grade($b['grade'] ?? 0);
$mastery = [];
foreach (array_slice(is_array($b['mastery'] ?? null) ? $b['mastery'] : [], 0, 120) as $m) {
    if (is_array($m) && isSkillId($m['skillId'] ?? null)) {
        $mastery[] = ['skillId' => $m['skillId'], 'p' => round(max(0, min(1, (float) ($m['p'] ?? 0))), 2), 'attempts' => max(0, (int) ($m['attempts'] ?? 0))];
    }
}
$errors = [];
foreach (array_slice(is_array($b['errors'] ?? null) ? $b['errors'] : [], 0, 60) as $e) {
    if (is_array($e) && isMisconceptionId($e['id'] ?? null)) {
        $errors[] = ['id' => $e['id'], 'count' => max(0, (int) ($e['count'] ?? 0)), 'recentRate' => round(max(0, min(1, (float) ($e['recentRate'] ?? 0))), 2)];
    }
}
$s = is_array($b['session'] ?? null) ? $b['session'] : [];
$daten = [
    'klasse' => $grade,
    'mastery' => $mastery,
    'fehlvorstellungen' => $errors,
    'misreadScore' => round(max(0, min(1, (float) ($b['misreadScore'] ?? 0))), 2),
    'readingWpm' => max(0, min(400, (int) ($b['readingWpm'] ?? 0))),
    'sitzung' => ['aufgaben' => max(0, (int) ($s['tasks'] ?? 0)), 'richtig' => max(0, (int) ($s['correct'] ?? 0)), 'minuten' => max(0, (int) ($s['minutes'] ?? 0))],
];
$vorher = clean($b['previousNote'] ?? '', 800);

budgetBuchen($uid, 'analyze');

$anweisung = <<<TEXT
    Du wertest den Lernstand eines Grundschulkindes (Klasse {$grade}) in einer Lern-App für Mathe und Deutsch aus.
    Du bekommst nur Kennzahlen: mastery p = geschätzte Beherrschung 0–1, Fehlvorstellungen mit Häufigkeit,
    misreadScore = Anteil Fehler durch ungenaues Lesen 0–1, Lesetempo in Wörtern/Minute.
    Antworte auf Deutsch.
    - 'note': knappe interne Notiz (max. 400 Zeichen) für die nächste Auswertung: Trends, Vermutungen.
    - 'focus': 1 bis 3 skillIds aus den Daten, die als Nächstes geübt werden sollten (nur IDs aus der Eingabe).
    - 'parentText': 2–3 freundliche, konkrete Sätze für die Eltern. Keine Diagnosen, keine Etiketten
      (nie "Legasthenie", "Dyskalkulie", "schwach"), Stärken zuerst, ein konkreter Übungstipp.
    - 'kidPraise': ein kurzer, ehrlicher Lobsatz fürs Kind (max. 100 Zeichen), ohne Namen.
    TEXT;
$eingabe = json_encode($daten, JSON_UNESCAPED_UNICODE) . ($vorher !== '' ? "\nNotiz der letzten Auswertung: $vorher" : '');

$roh = aiJson([textTeil($eingabe)], [
    'type' => 'object',
    'properties' => [
        'note' => ['type' => 'string'],
        'focus' => ['type' => 'array', 'items' => ['type' => 'string']],
        'parentText' => ['type' => 'string'],
        'kidPraise' => ['type' => 'string'],
    ],
    'required' => ['note', 'focus', 'parentText', 'kidPraise'],
    'additionalProperties' => false,
], $anweisung);

$bekannt = array_column($mastery, 'skillId');
$focus = array_values(array_unique(array_filter(is_array($roh['focus'] ?? null) ? $roh['focus'] : [], fn ($f) => in_array($f, $bekannt, true))));
$antwort = [
    'note' => clean($roh['note'] ?? '', 600),
    'focus' => array_slice($focus, 0, 3),
    'parentText' => clean($roh['parentText'] ?? '', 500),
];
$lob = clean($roh['kidPraise'] ?? '', 140);
if ($lob !== '') {
    $antwort['kidPraise'] = $lob;
}
send($antwort);
