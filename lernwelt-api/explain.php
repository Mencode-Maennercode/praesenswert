<?php
/**
 * explain.php – kindgerechte Erklärung zu einem konkreten Fehler.
 * Antwort-Cache per Hash über die Anfrage: jede Erklärung wird nur einmal bezahlt.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

const EXPLAIN_VERSION = 'v2-mistral'; // erhöhen, wenn sich die Anweisung ändert → Cache neu

cors();
$uid = requireUser();
$b = jsonBody(16_000);

$req = [
    'subject' => subject($b['subject'] ?? null),
    'grade' => grade($b['grade'] ?? 0),
    'skillId' => isSkillId($b['skillId'] ?? null) ? $b['skillId'] : fail('skillId ungültig.'),
    'misconception' => isMisconceptionId($b['misconception'] ?? null) ? $b['misconception'] : null,
    'task' => clean($b['task'] ?? '', 300),
    'answer' => clean($b['answer'] ?? '', 120),
    'correct' => clean($b['correct'] ?? '', 120),
];
if ($req['task'] === '' || $req['correct'] === '') {
    fail('task und correct sind Pflicht.');
}

$hash = hash('sha256', EXPLAIN_VERSION . json_encode($req, JSON_UNESCAPED_UNICODE));
$datei = DATA_DIR . '/explain/' . substr($hash, 0, 2) . "/$hash.json";
if (is_file($datei)) {
    header('X-Cache: HIT');
    send(readJson($datei));
}

budgetBuchen($uid, 'explain');

$fach = $req['subject'] === 'mathe' ? 'Mathe' : 'Deutsch';
$anweisung = <<<TEXT
    Du bist ein freundliches Maskottchen in einer Lern-App für Grundschulkinder ({$fach}, Klasse {$req['grade']}).
    Ein Kind hat eine Aufgabe falsch gelöst. Erkläre auf Deutsch, warum die richtige Lösung stimmt –
    so, dass ein Kind dieser Klasse es versteht.
    - 'speech': höchstens 3 kurze Sätze zum Vorlesen. Einfache Wörter, keine Fachbegriffe ohne Erklärung,
      kein Tadel, kein "falsch" oder "leider". Ermutigend, konkret auf diese Aufgabe bezogen.
      Keine Emojis, keine Sonderzeichen, Zahlen als Ziffern.
    - 'steps': 2 bis 5 sehr kurze Rechen- oder Denkschritte für eine Animation (je max. 60 Zeichen),
      oder leer, wenn Schritte nicht helfen.
    TEXT;
$eingabe = "Kompetenz: {$req['skillId']}\n"
    . ($req['misconception'] ? "Vermutete Fehlvorstellung: {$req['misconception']}\n" : '')
    . "Aufgabe: {$req['task']}\nAntwort des Kindes: {$req['answer']}\nRichtig: {$req['correct']}";

$roh = aiJson([textTeil($eingabe)], [
    'type' => 'object',
    'properties' => [
        'speech' => ['type' => 'string'],
        'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['speech', 'steps'],
    'additionalProperties' => false,
], $anweisung);

$speech = clean($roh['speech'] ?? '', 400);
if ($speech === '') {
    fail('KI-Antwort unbrauchbar.', 502);
}
$steps = array_values(array_filter(array_map(fn ($s) => clean($s, 80), array_slice(is_array($roh['steps'] ?? null) ? $roh['steps'] : [], 0, 6))));
$antwort = ['speech' => $speech] + ($steps ? ['steps' => $steps] : []);

writeJson($datei, $antwort);
header('X-Cache: MISS');
send($antwort);
