<?php
/**
 * Prüft einen Eintrag, BEVOR er auf der Liste landet.
 *
 * Zwei Fragen stehen im Raum:
 *   1. Steht schon etwas Gleichwertiges drauf? (Brötchen / Baguette)
 *   2. Könnte das überhaupt jeder mitbringen? ("Aioli aus dem Thermomix")
 *
 * Die erste Runde läuft rein rechnerisch im PHP - Tippfehler über die
 * Levenshtein-Distanz, Gleichwertiges über Warengruppen. Das kostet nichts
 * und antwortet sofort. Liegt ein Google-AI-Schlüssel vor, entscheidet
 * anschließend Gemini, weil "Brötchen ~ Baguette" Sprachverständnis braucht
 * und keine Wortliste der Welt vollständig ist.
 *
 * Wichtig: Dieser Endpunkt blockiert nie. Fällt er aus, ist er langsam, ist
 * das Kontingent erschöpft (HTTP 429) oder fehlt der Schlüssel, kommt "alles
 * in Ordnung" zurück und der Gast trägt sich ganz normal ein. Eine Prüfung
 * darf niemals das Eintragen verhindern.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

/*
 * gemini-3.6-flash mit reduziertem "Denken": im Test 1-2 Sekunden statt 4-5,
 * bei gleichem Urteil. Für eine Rückfrage beim Absenden zählt Tempo mehr als
 * das letzte Quäntchen Sorgfalt - im Zweifel gilt ohnehin "in Ordnung".
 */
const AI_MODEL     = 'gemini-3.6-flash';
const AI_THINKING  = 'low';
const AI_TIMEOUT   = 12;   // Sekunden - so lange wartet der Gast höchstens
const AI_MAX_ITEMS = 60;   // mehr Einträge muss der Prompt nicht tragen

$body = jsonBody();
rateLimit('check', 40, 600);

$id = validEventId($body['eventId'] ?? '');
if ($id === '') {
    fail('Kein gültiger Party-Link.');
}

$item  = clean($body['item'] ?? '', MAX_ITEM);
$email = cleanEmail($body['email'] ?? '');

if ($item === '') {
    send(['ok' => true, 'status' => 'ok']);
}

$event = loadEvent($id);
if ($event['status'] !== 'open') {
    send(['ok' => true, 'status' => 'ok']);
}

/*
 * Der eigene Eintrag zählt nicht als Dublette - sonst meckert die Prüfung
 * bei jeder Korrektur über den eigenen alten Text.
 */
$others = [];
foreach ($event['participants'] as $p) {
    if (($p['item'] ?? '') === '') {
        continue;
    }
    if ($email !== '' && strcasecmp($p['email'] ?? '', $email) === 0) {
        continue;
    }
    $others[] = ['name' => $p['name'] ?? '', 'item' => $p['item']];
}

/* ------------------------------------------------------------ Runde 1: rechnen */

$match = null;
$score = 0.0;
foreach ($others as $other) {
    $similarity = itemSimilarity($item, $other['item']);
    if ($similarity > $score) {
        $score = $similarity;
        $match = $other;
    }
}

$equipment = specialEquipment($item);

// Praktisch derselbe Text - dafür braucht es kein Sprachmodell.
if ($match !== null && $score >= 0.95) {
    send(localDuplicateAnswer($match, $event, $others));
}

/* --------------------------------------------------------- Runde 2: nachfragen */

$key = aiKey();
if ($key !== '' && aiBudgetAvailable()) {
    $verdict = askGemini($key, $item, $others, $event);
    if ($verdict !== null) {
        send($verdict);
    }
}

// Kein Schlüssel, oder Gemini war nicht erreichbar: lokales Ergebnis zählt.
if ($match !== null && $score >= SIMILAR_ENOUGH) {
    send(localDuplicateAnswer($match, $event, $others));
}
if ($equipment !== null) {
    send(localEquipmentAnswer($item, $equipment, $event, $others));
}

send(['ok' => true, 'status' => 'ok']);

/* ------------------------------------------------------------------ Antworten */

/**
 * Vorschläge aus den Ideen des Gastgebers: alles, was noch niemand
 * eingetragen hat.
 *
 * @return string[]
 */
function freeSuggestions(array $event, array $others, int $limit = 3): array
{
    $free = [];
    foreach ($event['suggestions'] ?? [] as $suggestion) {
        $taken = false;
        foreach ($others as $other) {
            if (itemSimilarity($suggestion, $other['item']) >= SIMILAR_ENOUGH) {
                $taken = true;
                break;
            }
        }
        if (!$taken) {
            $free[] = $suggestion;
        }
        if (count($free) >= $limit) {
            break;
        }
    }
    return $free;
}

function localDuplicateAnswer(array $match, array $event, array $others): array
{
    $who = $match['name'] !== '' ? $match['name'] : 'Jemand';
    return [
        'ok' => true,
        'status' => 'warn',
        'kind' => 'duplicate',
        'message' => $who . ' bringt schon "' . $match['item'] . '" mit.',
        'suggestions' => freeSuggestions($event, $others),
        'source' => 'lokal',
    ];
}

function localEquipmentAnswer(string $item, array $equipment, array $event, array $others): array
{
    $suggestions = $equipment['without'] !== '' ? [$equipment['without']] : [];
    foreach (freeSuggestions($event, $others, 2) as $extra) {
        $suggestions[] = $extra;
    }
    return [
        'ok' => true,
        'status' => 'warn',
        'kind' => 'equipment',
        'message' => 'Dafür braucht man einen ' . $equipment['device']
            . ' - den hat nicht jeder. Wer das später zugelost bekommt, steht dann dumm da.',
        'suggestions' => array_slice($suggestions, 0, 3),
        'source' => 'lokal',
    ];
}

/* --------------------------------------------------------------------- Gemini */

/**
 * Fragt Gemini nach einem Urteil.
 *
 * Gibt null zurück, sobald irgendetwas nicht stimmt - Netzwerk, Schlüssel,
 * erschöpftes Kontingent, unerwartete Antwort. Der Aufrufer fällt dann auf
 * die lokale Prüfung zurück.
 */
function askGemini(string $key, string $item, array $others, array $event): ?array
{
    $list = [];
    foreach (array_slice($others, 0, AI_MAX_ITEMS) as $other) {
        $list[] = '- ' . ($other['name'] !== '' ? $other['name'] : 'jemand') . ': ' . $other['item'];
    }
    $listText = $list === [] ? '(noch nichts eingetragen)' : implode("\n", $list);

    $ideas = implode(', ', $event['suggestions'] ?? []);

    $system = <<<'PROMPT'
Du prüfst Einträge für die Mitbringliste einer privaten Grillparty mit rund
zehn Gästen. Jeder trägt eine Sache ein; am Ende werden alle Einträge unter
den Gästen verlost. Deshalb muss jeder Eintrag von JEDER Person besorgt oder
zubereitet werden können.

Prüfe genau zwei Dinge:

1. DOPPELT: Steht schon etwas auf der Liste, das denselben Zweck erfüllt?
   Brötchen und Baguette sind beides Brot. Kartoffelsalat und Nudelsalat sind
   dagegen zwei verschiedene Sachen und beide willkommen. Rechne mit
   Tippfehlern und Umgangssprache: "Ailo" ist Aioli, "Kartoffelsallat" ist
   Kartoffelsalat, "Weckle" sind Brötchen.

2. NICHT MACHBAR: Setzt der Eintrag ein Gerät, eine Fähigkeit oder einen
   Zeitaufwand voraus, den nicht jeder Gast aufbringen kann? "Aioli aus dem
   Thermomix" geht nicht, weil kaum jemand einen Thermomix hat - "Aioli"
   allein geht sehr wohl, die kann man auch kaufen oder von Hand rühren.
   Selbstgebackenes, Gekauftes und Einfaches sind in Ordnung.

Alles andere ist in Ordnung. Sei nicht pingelig: im Zweifel "ok". Es geht um
eine Grillparty, nicht um eine Prüfung.

Antworte auf Deutsch, per du, freundlich und in EINEM kurzen Satz.
- verdict "doppelt": Sag, wer schon was Ähnliches mitbringt.
- verdict "geraet": Sag, woran es hakt.
- verdict "ok": hinweis bleibt leer.
Bei "doppelt" und "geraet" nennst du ein bis drei konkrete Alternativen, die
noch fehlen und die wirklich jeder besorgen kann. Bei "ok" bleibt die Liste
der Vorschläge leer.
PROMPT;

    $user = "Das steht schon auf der Liste:\n{$listText}\n\n"
        . ($ideas !== '' ? "Ideen des Gastgebers: {$ideas}\n\n" : '')
        . "Neuer Eintrag, den ich prüfen soll: \"{$item}\"";

    /*
     * responseSchema zwingt die Antwort in genau diese Form - damit ist der
     * Text hinterher garantiert gültiges JSON und muss nicht aus Fließtext
     * herausgeklaubt werden.
     */
    $payload = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $user]]],
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'verdict' => ['type' => 'STRING', 'enum' => ['ok', 'doppelt', 'geraet']],
                    'hinweis' => ['type' => 'STRING'],
                    'vorschlaege' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                ],
                'required' => ['verdict', 'hinweis', 'vorschlaege'],
                'propertyOrdering' => ['verdict', 'hinweis', 'vorschlaege'],
            ],
            'thinkingConfig' => ['thinkingLevel' => AI_THINKING],
        ],
    ];

    $answer = postJson(
        'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent',
        $payload,
        [
            'content-type: application/json',
            'x-goog-api-key: ' . $key,
        ],
    );
    if (!is_array($answer)) {
        return null;
    }

    // Der Sicherheitsfilter hat die Anfrage abgelehnt: kein Ergebnis, also
    // durchwinken statt raten.
    if (isset($answer['promptFeedback']['blockReason'])) {
        return null;
    }

    $parts = $answer['candidates'][0]['content']['parts'] ?? null;
    $text = '';
    foreach (is_array($parts) ? $parts : [] as $part) {
        if (is_array($part) && is_string($part['text'] ?? null)) {
            $text .= $part['text'];
        }
    }
    $result = json_decode(trim($text), true);
    if (!is_array($result)) {
        return null;
    }

    $verdict = is_string($result['verdict'] ?? null) ? $result['verdict'] : 'ok';
    if ($verdict === 'ok') {
        return ['ok' => true, 'status' => 'ok'];
    }

    $hinweis = clean($result['hinweis'] ?? '', 240);
    if ($hinweis === '') {
        return null; // Urteil ohne Begründung hilft niemandem
    }

    $suggestions = [];
    $proposed = is_array($result['vorschlaege'] ?? null) ? $result['vorschlaege'] : [];
    foreach ($proposed as $suggestion) {
        $value = clean($suggestion, MAX_ITEM);
        if ($value !== '' && count($suggestions) < 3) {
            $suggestions[] = $value;
        }
    }

    return [
        'ok' => true,
        'status' => 'warn',
        'kind' => $verdict === 'geraet' ? 'equipment' : 'duplicate',
        'message' => $hinweis,
        'suggestions' => $suggestions,
        'source' => 'ki',
    ];
}

/**
 * POST mit JSON-Antwort. Bevorzugt cURL, weicht aber auf Streams aus -
 * nicht jeder Webspace hat die cURL-Erweiterung aktiv.
 */
function postJson(string $url, array $payload, array $headers): ?array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => AI_TIMEOUT,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $json,
        'timeout' => AI_TIMEOUT,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}
