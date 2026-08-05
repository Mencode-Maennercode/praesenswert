<?php
/**
 * Gemeinsame Basis der Grillparty-API.
 *
 * Speicher sind JSON-Dateien unter ../_data/. Das reicht für Partygrößen
 * vollkommen aus: pro Gast ein Schreibvorgang, abgesichert über flock() und
 * atomares rename(). Eine Datenbank wäre hier reiner Overhead.
 */

declare(strict_types=1);

/*
 * mbstring ist auf netcup vorhanden, aber ein fehlendes Modul würde hier
 * sonst einen weißen Bildschirm erzeugen. Die Ersatzfunktionen arbeiten
 * zeichenweise über preg //u - langsamer, aber UTF-8-sicher. Ohne das
 * könnte substr() einen Umlaut halbieren und json_encode danach scheitern.
 */
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null): string
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($chars, $start, $length));
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return (int) preg_match_all('/./us', $s);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s): string
    {
        return strtolower($s);
    }
}

const DATA_DIR   = __DIR__ . '/../_data';
const EVENTS_DIR = DATA_DIR . '/events';
const RATE_DIR   = DATA_DIR . '/ratelimit';
const SECRET_FILE = DATA_DIR . '/secret.key';
const AI_KEY_FILE = DATA_DIR . '/ai.key';

const MAX_NAME    = 60;
const MAX_ITEM    = 90;
const MAX_EMAIL   = 120;
const MAX_TEXT    = 200;
const MAX_GUESTS  = 300;
const MAX_SUGGEST = 40;
const WHEEL_MAX   = 12;
const WHEEL_MIN   = 6;
const TOKEN_TTL   = 3600; // Admin-Sitzung: 1 Stunde
const EDIT_TTL    = 1800; // Eigener Eintrag: 30 Minuten zum Ändern

/* ------------------------------------------------------------------ Ausgabe */

function send(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400): never
{
    send(['error' => $message], $status);
}

/**
 * Same-Origin braucht keine CORS-Header. Für die lokale Entwicklung
 * (Next auf :3000, PHP auf :8080) werden localhost-Origins durchgelassen.
 * Unbedenklich, weil die Authentifizierung über Tokens im Body läuft
 * und nicht über Cookies - es gibt also nichts, was ein fremder Origin
 * "mitschicken" könnte.
 */
function cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        fail('Methode nicht erlaubt.', 405);
    }
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        fail('Leere Anfrage.');
    }
    if (strlen($raw) > 64 * 1024) {
        fail('Anfrage zu groß.', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail('Ungültige Anfrage.');
    }
    return $data;
}

/* ----------------------------------------------------------------- Eingaben */

/** Kürzt, entfernt Tags und normalisiert Whitespace. */
function clean(mixed $value, int $max = MAX_TEXT): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    $value = trim($value);
    return mb_substr($value, 0, $max);
}

function cleanEmail(mixed $value): string
{
    $email = clean($value, MAX_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/** Erlaubt sind nur Zeichen, die sicher als Dateiname taugen. */
function validEventId(mixed $id): string
{
    $id = is_string($id) ? strtolower(trim($id)) : '';
    return preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $id) === 1 ? $id : '';
}

function slugify(string $text): string
{
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'];
    $text = strtr($text, $map);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return mb_substr($text, 0, 32);
}

/* ------------------------------------------------------------------ Storage */

function ensureDirs(): void
{
    foreach ([DATA_DIR, EVENTS_DIR, RATE_DIR] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            fail('Speicher nicht beschreibbar.', 500);
        }
    }
    // Zweiter Riegel neben der .htaccess im Auslieferungsverzeichnis.
    $guard = DATA_DIR . '/.htaccess';
    if (!file_exists($guard)) {
        @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}

function eventPath(string $id): string
{
    return EVENTS_DIR . '/' . $id . '.json';
}

function eventExists(string $id): bool
{
    return is_file(eventPath($id));
}

function loadEvent(string $id): array
{
    $path = eventPath($id);
    if (!is_file($path)) {
        fail('Diese Party gibt es nicht (mehr).', 404);
    }
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        fail('Party-Daten beschädigt.', 500);
    }
    return $data;
}

/** Atomar schreiben: erst in eine temporäre Datei, dann umbenennen. */
function saveEvent(array $event): void
{
    ensureDirs();
    $path = eventPath($event['id']);
    $tmp  = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        fail('Konnte nicht speichern.', 500);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fail('Konnte nicht speichern.', 500);
    }
    @chmod($path, 0660);
}

/**
 * Lesen, ändern, schreiben - unter exklusiver Sperre, damit zwei
 * gleichzeitige Anmeldungen sich nicht gegenseitig überschreiben.
 */
function withEventLock(string $id, callable $mutator): array
{
    ensureDirs();
    $lockFile = EVENTS_DIR . '/' . $id . '.lock';
    $handle = fopen($lockFile, 'c');
    if ($handle === false) {
        fail('Speicher gesperrt.', 500);
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            fail('Speicher gesperrt.', 500);
        }
        $event = loadEvent($id);
        $event = $mutator($event);
        saveEvent($event);
        return $event;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/* -------------------------------------------------------------------- Token */

function secret(): string
{
    ensureDirs();
    if (!is_file(SECRET_FILE)) {
        file_put_contents(SECRET_FILE, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod(SECRET_FILE, 0600);
    }
    return trim((string) file_get_contents(SECRET_FILE));
}

function makeAdminToken(string $eventId): string
{
    $expires = time() + TOKEN_TTL;
    $payload = $eventId . '.' . $expires;
    return $payload . '.' . hash_hmac('sha256', $payload, secret());
}

function verifyAdminToken(string $token, string $eventId): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    [$id, $expires, $sig] = $parts;
    if ($id !== $eventId || !ctype_digit($expires) || (int) $expires < time()) {
        return false;
    }
    $expected = hash_hmac('sha256', $id . '.' . $expires, secret());
    return hash_equals($expected, $sig);
}

/**
 * Ausweis für genau einen Eintrag, ausgestellt gegen die E-Mail-Adresse.
 *
 * Das Präfix "edit" trennt diese Tokens sauber von den Admin-Tokens: selbst
 * wenn beide einmal gleich viele Teile hätten, ergäbe das eine nie eine
 * gültige Unterschrift für das andere.
 */
function makeEditToken(string $eventId, string $participantId): string
{
    $expires = time() + EDIT_TTL;
    $payload = $eventId . '.' . $participantId . '.' . $expires;
    return $payload . '.' . hash_hmac('sha256', 'edit.' . $payload, secret());
}

function verifyEditToken(string $token, string $eventId, string $participantId): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 4) {
        return false;
    }
    [$id, $pid, $expires, $sig] = $parts;
    if ($id !== $eventId || $pid !== $participantId) {
        return false;
    }
    if (!ctype_digit($expires) || (int) $expires < time()) {
        return false;
    }
    $expected = hash_hmac('sha256', 'edit.' . $id . '.' . $pid . '.' . $expires, secret());
    return hash_equals($expected, $sig);
}

/* --------------------------------------------------------------- Rate-Limit */

function clientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/** Einfaches Zeitfenster-Limit gegen Spam-Bots. */
function rateLimit(string $bucket, int $max, int $windowSeconds): void
{
    ensureDirs();
    $file = RATE_DIR . '/' . $bucket . '_' . hash('sha256', clientIp()) . '.json';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = array_values(array_filter($decoded, fn($t) => is_int($t) && $t > $now - $windowSeconds));
        }
    }
    if (count($hits) >= $max) {
        fail('Zu viele Versuche. Bitte warte einen Moment.', 429);
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);

    // Gelegentlich alte Dateien wegräumen, damit das Verzeichnis nicht wächst.
    if (random_int(1, 50) === 1) {
        foreach (glob(RATE_DIR . '/*.json') ?: [] as $old) {
            if (filemtime($old) < $now - 86400) {
                @unlink($old);
            }
        }
    }
}

/** Honeypot: ein für Menschen unsichtbares Feld. Ist es gefüllt, war es ein Bot. */
function checkHoneypot(array $body): void
{
    if (!empty($body['website'])) {
        // Bewusst wie ein Erfolg aussehen lassen, damit der Bot nichts lernt.
        send(['ok' => true, 'participantId' => 'p_' . bin2hex(random_bytes(4))]);
    }
}

/* -------------------------------------------------------------- Eintragsprüfung */

/**
 * Vergleichsform eines Eintrags: klein, ohne Umlaute, ohne Satzzeichen.
 *
 * Die Umlaute werden VOR dem Kleinschreiben ersetzt - sonst käme die
 * Ersatzfunktion für mb_strtolower() (siehe oben) mit "Ä" nicht klar.
 */
function normalizeItem(string $value): string
{
    $map = [
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'É' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'á' => 'a', 'â' => 'a',
        'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ];
    $value = strtr($value, $map);
    $value = mb_strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

/** Füllwörter, die für den Vergleich nichts hergeben. */
function stopWords(): array
{
    return [
        'mit', 'und', 'oder', 'der', 'die', 'das', 'dem', 'den', 'ein', 'eine', 'einen',
        'von', 'aus', 'im', 'in', 'am', 'zum', 'zur', 'fuer', 'etwas', 'was', 'paar', 'ca',
    ];
}

/** @return string[] */
function itemTokens(string $normalized): array
{
    $stop = stopWords();
    return array_values(array_filter(
        explode(' ', $normalized),
        fn($t) => $t !== '' && !in_array($t, $stop, true),
    ));
}

/**
 * Grobe Grundform. Reicht, um "Brötchen" und "Brötchens" zusammenzubringen -
 * eine echte Stammformerkennung wäre hier maßlos überzogen.
 */
function stemWord(string $word): string
{
    foreach (['chen', 'lein', 'ern', 'en', 'er', 'es', 'e', 'n', 's'] as $suffix) {
        if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 4) {
            return substr($word, 0, strlen($word) - strlen($suffix));
        }
    }
    return $word;
}

/** 0.0 bis 1.0, gemessen an der Tippfehler-Distanz. */
function stringSimilarity(string $a, string $b): float
{
    if ($a === $b) {
        return 1.0;
    }
    $len = max(strlen($a), strlen($b));
    // levenshtein() arbeitet nur bis 255 Byte - bei so langen Einträgen
    // trägt der Wortvergleich weiter unten die Entscheidung.
    if ($len === 0 || $len > 120) {
        return 0.0;
    }
    return 1.0 - levenshtein($a, $b) / $len;
}

/**
 * Sachen, die dasselbe Loch auf dem Tisch füllen. Wer Brötchen mitbringt,
 * muss nicht auch noch Baguette mitbringen.
 *
 * Bewusst eng gefasst: Steak und Bratwurst gehören beide auf den Grill und
 * sind nebeneinander völlig in Ordnung - dafür gibt es keine gemeinsame
 * Gruppe. Zusammen steht nur, was einander wirklich ersetzt.
 *
 * Einträge ab 6 Zeichen dürfen mitten im Wort stecken (deutsche Komposita:
 * "Kräuterbaguette"). Kürzere nur als ganzes Wort - sonst fände "eis" auch
 * noch das "eis" in "Fleisch".
 */
function itemGroups(): array
{
    return [
        'brot' => ['brot', 'broetchen', 'broetli', 'semmel', 'weckle', 'weck', 'schrippe',
            'baguette', 'ciabatta', 'fladenbrot', 'focaccia', 'stangenbrot', 'knoblauchbrot',
            'burgerbroetchen', 'buns', 'toastbrot', 'pita'],
        'knoblauchdip' => ['aioli', 'knoblauchsauce', 'knoblauchdip', 'knoblauchcreme',
            'mayo', 'mayonnaise', 'remoulade'],
        'grillsauce' => ['grillsauce', 'grillsossen', 'grillsaucen', 'bbqsauce', 'barbecuesauce',
            'ketchup', 'currysauce'],
        'blattsalat' => ['krautsalat', 'coleslaw', 'blattsalat', 'beilagensalat',
            'gruener salat', 'gemischter salat', 'gruen salat'],
        'kartoffelsalat' => ['kartoffelsalat', 'erdaepfelsalat'],
        'nudelsalat' => ['nudelsalat', 'pastasalat', 'tortellinisalat'],
        'wurst' => ['bratwurst', 'rostbratwurst', 'wuerstchen', 'grillwurst', 'kraeuterwurst'],
        'grillveggie' => ['halloumi', 'grillkaese', 'grilltofu'],
        'bier' => ['bier', 'radler', 'pils', 'helles'],
        'softdrink' => ['cola', 'limo', 'limonade', 'spezi', 'eistee'],
        'wasser' => ['sprudel', 'mineralwasser', 'stilles wasser', 'wasser'],
        'kuchen' => ['kuchen', 'muffins', 'cupcakes', 'brownies'],
        'geschirr' => ['teller', 'becher', 'servietten', 'besteck', 'pappteller'],
        'kohle' => ['kohle', 'grillkohle', 'briketts', 'anzuender', 'grillanzuender'],
    ];
}

/**
 * Welche Gruppen berührt dieser Eintrag?
 *
 * @return string[]
 */
function groupsOf(string $normalized): array
{
    $found = [];
    $padded = ' ' . $normalized . ' ';
    foreach (itemGroups() as $group => $words) {
        foreach ($words as $word) {
            $hit = strlen($word) >= 6
                ? str_contains($normalized, $word)
                : str_contains($padded, ' ' . $word . ' ');
            if ($hit) {
                $found[] = $group;
                break;
            }
        }
    }
    return $found;
}

/**
 * Wie sehr überschneiden sich zwei Einträge? 0.0 bis 1.0.
 *
 * Vier Wege führen zu einem Treffer: fast gleiche Schreibweise (Tippfehler),
 * das eine steckt komplett im anderen, dieselbe Warengruppe, oder ein
 * gemeinsames tragendes Wort.
 *
 * Die bekannte Grenze: bei kurzen Wörtern mit vertauschten Buchstaben
 * ("Ailo" statt "Aioli") ist die Distanz zu groß. Solche Fälle erkennt nur
 * die Prüfung durch Gemini in check.php.
 */
function itemSimilarity(string $a, string $b): float
{
    $na = normalizeItem($a);
    $nb = normalizeItem($b);
    if ($na === '' || $nb === '') {
        return 0.0;
    }
    if ($na === $nb) {
        return 1.0;
    }

    $best = stringSimilarity($na, $nb);

    // Steckt das eine komplett im anderen? "Melone" in "Wassermelone".
    if (
        (strlen($na) >= 5 && str_contains($nb, $na))
        || (strlen($nb) >= 5 && str_contains($na, $nb))
    ) {
        $best = max($best, 0.9);
    }

    $shared = array_intersect(groupsOf($na), groupsOf($nb));
    if (count($shared) > 0) {
        $best = max($best, 0.9);
    }

    // Gemeinsames tragendes Wort: "Kartoffel Salat" und "Nudel Salat".
    $stemsB = array_map('stemWord', itemTokens($nb));
    foreach (itemTokens($na) as $token) {
        $stem = stemWord($token);
        if (strlen($stem) >= 5 && in_array($stem, $stemsB, true)) {
            $best = max($best, 0.88);
        }
    }

    return $best;
}

/**
 * Ab hier gelten zwei Einträge als dasselbe. 0.8 heißt: ein Tippfehler auf
 * fünf Zeichen ("Ailo" / "Aioli") zählt noch als Treffer, zwei verschiedene
 * kurze Wörter ("Sekt" / "Senf") nicht mehr.
 */
const SIMILAR_ENOUGH = 0.8;

/**
 * Geräte und Spezialwerkzeug, das nicht jeder zu Hause hat. Ein Eintrag,
 * den nur zwei von zehn Leuten umsetzen können, taugt nicht zum Verlosen.
 *
 * Schlüssel ist die Vergleichsform, Wert die Schreibweise für den Hinweis.
 */
function specialEquipmentWords(): array
{
    return [
        'thermomix' => 'Thermomix',
        'tm5' => 'Thermomix',
        'tm6' => 'Thermomix',
        'monsieur cuisine' => 'Monsieur Cuisine',
        'airfryer' => 'Airfryer',
        'air fryer' => 'Airfryer',
        'heissluftfritteuse' => 'Heißluftfritteuse',
        'sous vide' => 'Sous-vide-Garer',
        'sousvide' => 'Sous-vide-Garer',
        'pizzaofen' => 'Pizzaofen',
        'pizzastein' => 'Pizzastein',
        'eismaschine' => 'Eismaschine',
        'doerrautomat' => 'Dörrautomat',
        'smoker' => 'Smoker',
        'raeucherofen' => 'Räucherofen',
        'brotbackautomat' => 'Brotbackautomat',
        'dampfgarer' => 'Dampfgarer',
        'waffeleisen' => 'Waffeleisen',
        'raclette' => 'Raclette-Grill',
        'fondue' => 'Fondue-Set',
        'nudelmaschine' => 'Nudelmaschine',
        'entsafter' => 'Entsafter',
        'siebtraeger' => 'Siebträgermaschine',
        'sodastream' => 'Sodastream',
        'zuckerwattemaschine' => 'Zuckerwattemaschine',
        'popcornmaschine' => 'Popcornmaschine',
        'schokobrunnen' => 'Schokobrunnen',
    ];
}

/**
 * Steckt in dem Eintrag ein Gerät, das nicht jeder hat?
 *
 * @return array{device: string, without: string}|null
 */
function specialEquipment(string $item): ?array
{
    // Alle Suchwörter sind lang und eindeutig genug, um auch mitten im Wort
    // gefunden zu werden ("Thermomixbrot").
    $normalized = normalizeItem($item);
    foreach (specialEquipmentWords() as $needle => $label) {
        if (str_contains($normalized, $needle)) {
            return ['device' => $label, 'without' => itemWithoutEquipment($item, $needle)];
        }
    }
    return null;
}

/**
 * "Aioli aus dem Thermomix" -> "Aioli".
 *
 * Gearbeitet wird auf dem Original, damit der Vorschlag lesbar bleibt. Bricht
 * nichts Sinnvolles heraus, kommt ein leerer String zurück und der Aufrufer
 * verzichtet auf den Vorschlag.
 */
function itemWithoutEquipment(string $item, string $needle): string
{
    // Aus der Vergleichsform eine Suche bauen, die auch Umlaute wieder trifft.
    $pattern = preg_quote($needle, '/');
    $pattern = str_replace(
        ['ae', 'oe', 'ue', 'ss', ' '],
        ['(?:ä|ae)', '(?:ö|oe)', '(?:ü|ue)', '(?:ß|ss)', '[\s\-]*'],
        $pattern,
    );
    $parts = preg_split('/[\s\-]*' . $pattern . '/iu', $item, 2);
    $head = is_array($parts) ? trim($parts[0]) : '';

    // Verbindungswörter am Ende abräumen: "Aioli aus dem" -> "Aioli".
    $head = preg_replace('/[\s,]+(aus|mit|vom|von|im|in)(\s+(dem|der|den))?$/iu', '', $head) ?? $head;
    // Satzzeichen am Rand mit preg statt trim() abräumen - trim() arbeitet
    // bytweise und würde an einem Gedankenstrich UTF-8 zerlegen.
    $head = preg_replace('/^[\s\-–,&+]+|[\s\-–,&+]+$/u', '', $head) ?? $head;

    return mb_strlen($head) >= 3 ? $head : '';
}

/* ------------------------------------------------------------- Schlüsselablage */

/**
 * Der API-Schlüssel liegt in _data/ - dort, wo auch die Party-Daten liegen.
 * Das Verzeichnis ist per .htaccess dicht, und der Deploy fasst _data/ nicht
 * an. Der Schlüssel überlebt also jedes Update, ohne je im Repo zu landen.
 */
function aiKey(): string
{
    $env = getenv('GEMINI_API_KEY');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    if (is_file(AI_KEY_FILE)) {
        return trim((string) file_get_contents(AI_KEY_FILE));
    }
    return '';
}

function storeAiKey(string $key): void
{
    ensureDirs();
    if (file_put_contents(AI_KEY_FILE, $key, LOCK_EX) === false) {
        fail('Der Schlüssel ließ sich nicht speichern.', 500);
    }
    @chmod(AI_KEY_FILE, 0600);
}

function deleteAiKey(): void
{
    if (is_file(AI_KEY_FILE)) {
        @unlink(AI_KEY_FILE);
    }
}

const AI_DAILY_MAX = 300;

/**
 * Tagesbremse. Das Rate-Limit oben schützt vor einem einzelnen Störer, diese
 * Zählung schützt die Rechnung: mehr als AI_DAILY_MAX Anfragen pro Tag kann
 * eine Party nicht plausibel brauchen. Danach prüft nur noch die Wortliste.
 *
 * Zwei gleichzeitige Aufrufe können sich hier gegenseitig überschreiben - bei
 * einer Obergrenze, die niemand ernsthaft erreicht, ist das kein Problem.
 */
function aiBudgetAvailable(): bool
{
    ensureDirs();
    $file = DATA_DIR . '/ai-usage.json';
    $today = date('Y-m-d');
    $count = 0;

    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data) && ($data['day'] ?? '') === $today) {
            $count = (int) ($data['count'] ?? 0);
        }
    }
    if ($count >= AI_DAILY_MAX) {
        return false;
    }

    @file_put_contents($file, json_encode(['day' => $today, 'count' => $count + 1]), LOCK_EX);
    return true;
}

/* ----------------------------------------------------------------- Domänen */

function defaultSuggestions(): array
{
    return [
        'Nackensteaks',
        'Bratwurst',
        'Halloumi & Gemüsespieße',
        'Kartoffelsalat',
        'Nudelsalat',
        'Krautsalat',
        'Baguette & Kräuterbutter',
        'Grillsaucen-Trio',
        'Kaltes Bier',
        'Wassermelone',
    ];
}

/** Fisher-Yates mit CSPRNG - shuffle() wäre für eine Verlosung zu schwach. */
function secureShuffle(array $list): array
{
    for ($i = count($list) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    }
    return array_values($list);
}

/**
 * Verlost die eingetragenen Sachen auf die Teilnehmer.
 *
 * Bewusst eine reine Zufallspermutation ohne Derangement-Korrektur: es ist
 * ausdrücklich erlaubt, das eigene Ding zu ziehen. Alles andere wäre nicht
 * mehr "wirklich alles Zufall".
 */
function drawAssignments(array $event): array
{
    $participants = $event['participants'];
    $count = count($participants);
    if ($count === 0) {
        fail('Es hat sich noch niemand eingetragen.', 409);
    }

    $pool = [];
    foreach ($participants as $p) {
        if (($p['item'] ?? '') !== '') {
            $pool[] = $p['item'];
        }
    }
    if (count($pool) === 0) {
        fail('Es steht nichts in der Liste, was verlost werden könnte.', 409);
    }

    // Weniger Sachen als Leute (weil der Admin welche gelöscht hat)?
    // Dann wird die gemischte Liste zyklisch weiterverwendet.
    $draw = secureShuffle($pool);
    while (count($draw) < $count) {
        $draw = array_merge($draw, secureShuffle($pool));
    }
    $draw = secureShuffle(array_slice($draw, 0, $count));

    // Für die Räder: alle Sachen einmalig, als Auswahlreservoir.
    $unique = array_values(array_unique($pool));
    $padding = array_values(array_diff($event['suggestions'] ?? [], $unique));

    foreach ($participants as $i => $p) {
        $assigned = $draw[$i];
        $participants[$i]['assignedItem'] = $assigned;
        $participants[$i]['wheelItems'] = buildWheel($assigned, $unique, $padding);
        $participants[$i]['spun'] = false;
    }

    $event['participants'] = $participants;
    $event['status'] = 'drawn';
    $event['drawnAt'] = date('c');
    return $event;
}

/**
 * Baut die Felder für ein Rad: das zugeloste Ding plus zufällige andere.
 * Mehr als 12 Segmente werden unleserlich, weniger als 6 wirken manipuliert -
 * deshalb wird notfalls mit Vorschlägen aufgefüllt.
 */
function buildWheel(string $assigned, array $unique, array $padding): array
{
    $others = array_values(array_filter($unique, fn($v) => $v !== $assigned));
    $others = secureShuffle($others);
    $wheel  = array_slice($others, 0, WHEEL_MAX - 1);

    if (count($wheel) + 1 < WHEEL_MIN) {
        $extra = secureShuffle(array_values(array_filter(
            $padding,
            fn($v) => $v !== $assigned && !in_array($v, $wheel, true),
        )));
        $needed = WHEEL_MIN - 1 - count($wheel);
        $wheel = array_merge($wheel, array_slice($extra, 0, max(0, $needed)));
    }

    $wheel[] = $assigned;
    return secureShuffle($wheel);
}

/**
 * Öffentliche Sicht: mit Namen und Eintragszeit, aber ohne E-Mail-Adressen.
 *
 * Der Name steht bewusst dabei - in der Liste soll erkennbar sein, wer schon
 * dran war. Die E-Mail bleibt draußen: sie dient nur dem Versand des
 * persönlichen Rad-Links und geht niemanden sonst etwas an.
 *
 * `createdAt` fehlt bei sehr alten Einträgen möglicherweise - dann wird
 * einfach keine Zeit angezeigt, statt dass hier etwas erfunden wird.
 *
 * `editedByAdmin` sagt, dass nicht der Gast selbst den Eintrag zuletzt
 * angefasst hat, sondern der Gastgeber. Das gehört offen in die Liste -
 * sonst wundert sich jemand, warum da plötzlich etwas anderes steht.
 */
function publicView(array $event): array
{
    $items = [];
    foreach ($event['participants'] as $p) {
        if (($p['item'] ?? '') !== '') {
            $items[] = [
                'id' => $p['id'],
                'item' => $p['item'],
                'name' => $p['name'] ?? '',
                'createdAt' => $p['createdAt'] ?? '',
                'updatedAt' => $p['updatedAt'] ?? null,
                'editedByAdmin' => ($p['editedBy'] ?? '') === 'admin',
            ];
        }
    }
    return [
        'id' => $event['id'],
        'meta' => $event['meta'],
        'suggestions' => array_values($event['suggestions']),
        'items' => $items,
        'participantCount' => count($event['participants']),
        'status' => $event['status'],
    ];
}

function baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * Unterverzeichnis, in dem die App liegt - aus dem eigenen Pfad abgeleitet.
 *
 * Diese Datei liegt immer unter <base>/api/xxx.php. Zwei Ebenen hoch ergibt
 * also die Basis: "/grillparty/api/result.php" -> "/grillparty", und bei einer
 * eigenen Subdomain "/api/result.php" -> "". Damit stimmen die verschickten
 * Rad-Links in beiden Fällen, ohne dass irgendwo ein Pfad hinterlegt wird.
 */
function appBase(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = str_replace('\\', '/', dirname(dirname($script)));
    return ($base === '/' || $base === '.') ? '' : rtrim($base, '/');
}

function wheelLink(string $eventId, string $token): string
{
    return baseUrl() . appBase() . '/rad/?e=' . rawurlencode($eventId)
        . '&t=' . rawurlencode($token);
}

/** Admin-Sicht: mit Namen, E-Mails und - nach der Auslosung - den Links. */
function adminView(array $event): array
{
    $participants = [];
    foreach ($event['participants'] as $p) {
        $participants[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'email' => $p['email'],
            'item' => $p['item'],
            'assignedItem' => $p['assignedItem'] ?? null,
            'spun' => (bool) ($p['spun'] ?? false),
            'link' => $event['status'] === 'drawn' ? wheelLink($event['id'], $p['token']) : null,
        ];
    }
    return [
        'id' => $event['id'],
        'meta' => $event['meta'],
        'suggestions' => array_values($event['suggestions']),
        'participants' => $participants,
        'status' => $event['status'],
        'drawnAt' => $event['drawnAt'],
        // Nur ob ein Schlüssel hinterlegt ist - der Schlüssel selbst
        // verlässt den Server nie wieder.
        'aiEnabled' => aiKey() !== '',
    ];
}
