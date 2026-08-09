<?php
/**
 * Gemeinsame Basis der AURA-API.
 *
 * Speicher sind JSON-Dateien unter ../_data/. Für eine Handvoll Nutzer mit
 * ein paar Einträgen am Tag reicht das vollkommen: pro Eintrag ein
 * Schreibvorgang, abgesichert über flock() und atomares rename(). Eine
 * Datenbank wäre hier reiner Overhead - und auf dem Webspace ohnehin
 * zusätzliche Abhängigkeit.
 *
 * Abgeleitet vom bewährten _lib.php der Grillparty, mit den Ergänzungen,
 * die eine App mit Konten braucht: Sitzungen, Nutzerablage, Energierechnung.
 */

declare(strict_types=1);

/*
 * Zeitzone hart setzen. Ohne das richtet sich date() nach der
 * Servereinstellung - und dann landet das Abendessen um 22 Uhr im falschen
 * Tag, die Push-Erinnerung kommt zur falschen Stunde und der Wochenwechsel
 * passiert am falschen Tag.
 */
date_default_timezone_set('Europe/Berlin');

const DATA_DIR    = __DIR__ . '/../_data';
const USERS_DIR   = DATA_DIR . '/users';
const DAYS_DIR    = DATA_DIR . '/days';
const WEIGHT_DIR  = DATA_DIR . '/weight';
const FEED_DIR    = DATA_DIR . '/feed';
const PLANS_DIR   = DATA_DIR . '/plans';
const RECIPES_DIR = DATA_DIR . '/recipes';
const LISTS_DIR   = DATA_DIR . '/lists';
const AI_DIR      = DATA_DIR . '/aisessions';
const RATE_DIR    = DATA_DIR . '/ratelimit';
const LOCK_DIR    = DATA_DIR . '/locks';

const SECRET_FILE  = DATA_DIR . '/secret.key';
const AI_KEY_FILE  = DATA_DIR . '/ai.key';
const PEXELS_FILE  = DATA_DIR . '/pexels.key';
const INVITE_FILE  = DATA_DIR . '/invite.key';
const USER_INDEX   = USERS_DIR . '/index.json';

const MAX_NAME     = 24;
const MIN_NAME     = 3;
const MAX_PASS     = 200;
const MIN_PASS     = 8;
const MAX_USERS    = 100;              // Notbremse gegen Massenanmeldung
const SESSION_TTL  = 60 * 86400;       // 60 Tage - eine App meldet nicht ab
const SESSION_RENEW = 20 * 86400;      // ab hier neuen Token mitschicken

/* ------------------------------------------------------------------ Ausgabe */

function send(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400, ?string $code = null): never
{
    send($code === null ? ['error' => $message] : ['error' => $message, 'code' => $code], $status);
}

/**
 * Same-Origin braucht keine CORS-Header. Für die lokale Entwicklung
 * (Next auf :3000, PHP auf :8080) werden localhost-Origins durchgelassen.
 * Unbedenklich, weil die Authentifizierung über Tokens im Body läuft und
 * nicht über Cookies - es gibt also nichts, was ein fremder Origin
 * automatisch "mitschicken" könnte.
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

/**
 * Liest den JSON-Rumpf.
 *
 * Die Obergrenze ist ein Parameter, weil sie je Endpunkt sehr verschieden
 * ist: eine Anmeldung braucht 200 Byte, ein Essensfoto 200 KB und eine
 * Sprachaufnahme über 1 MB. Eine einheitliche große Grenze wäre eine
 * offene Tür für Speicher-Angriffe.
 */
function jsonBody(int $maxBytes = 64 * 1024): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        fail('Leere Anfrage.');
    }
    if (strlen($raw) > $maxBytes) {
        fail('Anfrage zu groß.', 413, 'zu-gross');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail('Ungültige Anfrage.');
    }
    return $data;
}

/* ----------------------------------------------------------------- Eingaben */

/** Kürzt, entfernt Tags und normalisiert Whitespace. */
function clean(mixed $value, int $max = 200): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

function asInt(mixed $v, int $min, int $max, int $default = 0): int
{
    if (!is_numeric($v)) {
        return $default;
    }
    return max($min, min($max, (int) round((float) $v)));
}

function asFloat(mixed $v, float $min, float $max, float $default = 0.0): float
{
    if (!is_numeric($v)) {
        return $default;
    }
    return max($min, min($max, (float) $v));
}

/**
 * Wie asFloat, aber lehnt ab statt stillschweigend zu klemmen.
 *
 * Der Unterschied ist wichtig: Klemmen ist richtig für Werte, die aus einer
 * Rechnung kommen. Für eine Eingabe ist es falsch. Wer versehentlich 17
 * statt 170 cm eintippt, bekäme sonst kommentarlos 120 cm zugewiesen - und
 * damit ein Tagesziel, das mit ihm nichts zu tun hat. Bei Gesundheitsdaten
 * ist eine stille Korrektur die schlechtere Antwort als eine Rückfrage.
 */
function requireFloat(mixed $v, float $min, float $max, string $meldung, string $code): float
{
    if (!is_numeric($v)) {
        fail($meldung, 400, $code);
    }
    $f = (float) $v;
    if ($f < $min || $f > $max) {
        fail($meldung, 400, $code);
    }
    return $f;
}

function requireIntRange(mixed $v, int $min, int $max, string $meldung, string $code): int
{
    if (!is_numeric($v)) {
        fail($meldung, 400, $code);
    }
    $i = (int) round((float) $v);
    if ($i < $min || $i > $max) {
        fail($meldung, 400, $code);
    }
    return $i;
}

/* ------------------------------------------------------------ Dateisystem */

function ensureDirs(): void
{
    foreach (
        [DATA_DIR, USERS_DIR, DAYS_DIR, WEIGHT_DIR, FEED_DIR, PLANS_DIR,
            RECIPES_DIR, LISTS_DIR, AI_DIR, RATE_DIR, LOCK_DIR] as $dir
    ) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
    }

    /*
     * Der Schutz des Datenverzeichnisses hängt an einer Datei, die im
     * Deploy mitkommen muss. Falls sie je fehlt - vergessener Filter,
     * verunglückter Upload -, wird sie hier zur Laufzeit nachgelegt.
     * Der eigentliche Riegel steht in der .htaccess des Elternordners.
     */
    $guard = DATA_DIR . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\n");
    }
}

/**
 * Schreibt atomar: erst in eine Nebendatei, dann umbenennen.
 *
 * rename() innerhalb desselben Dateisystems ist unteilbar. Damit kann ein
 * Leser nie eine halb geschriebene Datei sehen - selbst dann nicht, wenn
 * PHP mitten im Schreiben abgebrochen wird. Ein direktes file_put_contents
 * hätte genau diese Lücke.
 */
function writeJson(string $path, array $data): void
{
    ensureDirs();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmp, $json) === false) {
        @unlink($tmp);
        fail('Speichern fehlgeschlagen.', 500);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fail('Speichern fehlgeschlagen.', 500);
    }
    @chmod($path, 0660);
}

function readJson(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}

/**
 * Führt $fn unter einer exklusiven Sperre aus.
 *
 * Die Sperre liegt bewusst auf einer eigenen .lock-Datei und nicht auf der
 * Nutzdatei selbst: Die Nutzdatei wird beim Schreiben durch rename()
 * ersetzt, und eine Sperre auf einem ersetzten Inode schützt nichts mehr.
 *
 * Anwendung immer nach dem Muster "lesen, ändern, schreiben" INNERHALB des
 * Rückrufs. Sperren nie ineinander verschachteln - genau daraus entstehen
 * Verklemmungen.
 */
function withLock(string $name, callable $fn): mixed
{
    ensureDirs();
    $safe = preg_replace('/[^a-z0-9_.-]/i', '_', $name) ?? 'lock';
    $file = LOCK_DIR . '/' . $safe . '.lock';

    $fh = fopen($file, 'c');
    if ($fh === false) {
        fail('Speicher gesperrt.', 503);
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        fail('Speicher gesperrt.', 503);
    }

    try {
        return $fn();
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/* ------------------------------------------------------------ Geheimnisse */

/** Der HMAC-Schlüssel für Sitzungstoken. Wird beim ersten Aufruf erzeugt. */
function secret(): string
{
    ensureDirs();
    if (is_file(SECRET_FILE)) {
        $key = trim((string) file_get_contents(SECRET_FILE));
        if ($key !== '') {
            return $key;
        }
    }
    $key = bin2hex(random_bytes(32));
    file_put_contents(SECRET_FILE, $key, LOCK_EX);
    @chmod(SECRET_FILE, 0600);
    return $key;
}

function keyFromFile(string $file, string $env = ''): string
{
    if ($env !== '') {
        $v = getenv($env);
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
    }
    return is_file($file) ? trim((string) file_get_contents($file)) : '';
}

function aiKey(): string
{
    return keyFromFile(AI_KEY_FILE, 'GEMINI_API_KEY');
}

function pexelsKey(): string
{
    return keyFromFile(PEXELS_FILE, 'PEXELS_API_KEY');
}

/**
 * Einladungscode - standardmäßig aus.
 *
 * Die Registrierung ist bewusst offen. Falls die App je geflutet wird,
 * genügt es, _data/invite.key mit einem Wort anzulegen: ab dann braucht
 * jede Anmeldung diesen Code. Kein neuer Deploy nötig.
 */
function inviteCode(): string
{
    return keyFromFile(INVITE_FILE);
}

/* ---------------------------------------------------------------- Bremsen */

function clientIp(): string
{
    // Cloudflare steht davor, deshalb zuerst dessen Header.
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $v = $_SERVER[$key] ?? '';
        if (is_string($v) && $v !== '') {
            $first = trim(explode(',', $v)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Einfaches Zählfenster pro Kennung.
 *
 * $key ist der Vorgang ("login", "ai"), $who die Kennung. Ohne $who wird
 * die IP genommen. Für angemeldete Nutzer ist die Nutzer-ID die bessere
 * Kennung: eine Familie hinter einem Anschluss teilt sich sonst das
 * Kontingent.
 */
function rateLimit(string $key, int $max, int $seconds, ?string $who = null): void
{
    ensureDirs();
    $id = hash('sha256', ($who ?? clientIp()) . '|' . $key);
    $file = RATE_DIR . '/' . $id . '.json';
    $now = time();

    $bucket = readJson($file, []);
    $hits = array_values(array_filter(
        is_array($bucket['hits'] ?? null) ? $bucket['hits'] : [],
        static fn($t) => is_int($t) && $t > $now - $seconds,
    ));

    if (count($hits) >= $max) {
        header('Retry-After: ' . (string) max(1, $seconds - ($now - (int) $hits[0])));
        fail('Zu viele Versuche. Bitte kurz warten.', 429, 'gebremst');
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode(['hits' => $hits]), LOCK_EX);

    // Gelegentlich aufräumen, damit das Verzeichnis nicht unbegrenzt wächst.
    if (random_int(1, 60) === 1) {
        foreach (glob(RATE_DIR . '/*.json') ?: [] as $old) {
            if (filemtime($old) < $now - 86400) {
                @unlink($old);
            }
        }
    }
}

/* --------------------------------------------------------------- Sitzungen */

/**
 * Sitzungstoken: uid.version.ablauf.signatur
 *
 * Kein Cookie, sondern ein Wert im Anfragerumpf. Das erspart die gesamte
 * CSRF-Frage - ein fremder Origin kann nichts mitschicken, was er nicht hat.
 *
 * Die "version" kommt aus dem Profil. Wird sie erhöht (Passwortwechsel,
 * "überall abmelden"), sind alle alten Token sofort wertlos, ohne dass
 * irgendwo eine Liste gültiger Sitzungen gepflegt werden müsste.
 */
function makeToken(string $uid, int $version): string
{
    $payload = $uid . '.' . $version . '.' . (time() + SESSION_TTL);
    return $payload . '.' . hash_hmac('sha256', 'sess.' . $payload, secret());
}

/**
 * Prüft den Token und liefert das Nutzerprofil.
 *
 * @return array{0: string, 1: array} [uid, profil]
 */
function requireUser(array $body): array
{
    $token = is_string($body['token'] ?? null) ? $body['token'] : '';
    $parts = explode('.', $token);
    if (count($parts) !== 4) {
        fail('Bitte neu anmelden.', 401, 'abgemeldet');
    }

    [$uid, $version, $exp, $sig] = $parts;
    $payload = $uid . '.' . $version . '.' . $exp;

    // hash_equals statt === : vergleicht in konstanter Zeit und verrät
    // damit nicht über die Laufzeit, wie weit die Signatur passte.
    if (!hash_equals(hash_hmac('sha256', 'sess.' . $payload, secret()), $sig)) {
        fail('Bitte neu anmelden.', 401, 'abgemeldet');
    }
    if ((int) $exp < time()) {
        fail('Sitzung abgelaufen.', 401, 'abgemeldet');
    }
    if (!validUserId($uid)) {
        fail('Bitte neu anmelden.', 401, 'abgemeldet');
    }

    $user = loadUser($uid);
    if ($user === null || (int) ($user['tokenVersion'] ?? 1) !== (int) $version) {
        fail('Bitte neu anmelden.', 401, 'abgemeldet');
    }

    return [$uid, $user];
}

/**
 * Hängt bei Bedarf einen frischen Token an die Antwort.
 *
 * Gleitendes Fenster: Solange die App benutzt wird, läuft die Sitzung nie
 * ab. Ein eigener Erneuerungs-Endpunkt ist dadurch überflüssig.
 */
function withFreshToken(array $payload, string $uid, array $user, array $body): array
{
    $token = is_string($body['token'] ?? null) ? $body['token'] : '';
    $exp = (int) (explode('.', $token)[2] ?? 0);
    if ($exp - time() < SESSION_RENEW) {
        $payload['token'] = makeToken($uid, (int) ($user['tokenVersion'] ?? 1));
    }
    return $payload;
}

/* ------------------------------------------------------------ Nutzerablage */

function validUserId(mixed $id): bool
{
    return is_string($id) && preg_match('/^u_[a-f0-9]{16}$/', $id) === 1;
}

function userPath(string $uid): string
{
    return USERS_DIR . '/' . $uid . '.json';
}

function loadUser(string $uid): ?array
{
    if (!validUserId($uid)) {
        return null;
    }
    $data = readJson(userPath($uid));
    return $data === [] ? null : $data;
}

function saveUser(string $uid, array $user): void
{
    $user['updatedAt'] = date('c');
    writeJson(userPath($uid), $user);
}

/**
 * Nutzername -> ID.
 *
 * Eine einzige Datei mit einer einzigen Sperre. Das ist der Ort, an dem
 * über die Eindeutigkeit eines Namens entschieden wird - deshalb darf es
 * ihn nur einmal geben.
 */
function userIndex(): array
{
    return readJson(USER_INDEX, []);
}

function normalizeName(string $name): string
{
    return mb_strtolower(trim($name));
}

/** Was als Nutzername durchgeht. Bewusst eng - der Name taucht bei Freunden auf. */
function validName(string $name): bool
{
    return preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9 _.-]{1,22}[a-zA-Z0-9])$/u', $name) === 1;
}

/* --------------------------------------------------------- Energierechnung */

/**
 * Grundumsatz nach Mifflin-St Jeor.
 *
 * Die Formel gilt seit 1990 als die genaueste für die Allgemeinbevölkerung
 * und schlägt Harris-Benedict deutlich. Für "divers" gibt es keine
 * belastbare Studienlage, deshalb der Mittelwert beider Varianten - das
 * ist ehrlicher als eine der beiden zu behaupten.
 *
 * ACHTUNG: Diese Funktion hat ein Gegenstück in lib/energy.ts. Beide
 * müssen dasselbe rechnen, sonst zeigt die App etwas anderes an, als der
 * Server speichert. `npm run check` vergleicht sie.
 */
function bmr(string $sex, float $kg, float $cm, int $alter): float
{
    $basis = 10.0 * $kg + 6.25 * $cm - 5.0 * $alter;
    return match ($sex) {
        'm' => $basis + 5.0,
        'w' => $basis - 161.0,
        default => $basis - 78.0, // Mittel aus +5 und -161
    };
}

/** Aktivitätsfaktoren (PAL). Die Stufen sind bewusst grob - feiner wäre Schein. */
function palFactor(string $stufe): float
{
    return match ($stufe) {
        'sitzend' => 1.2,
        'leicht' => 1.375,
        'maessig' => 1.55,
        'hoch' => 1.725,
        'sehr_hoch' => 1.9,
        default => 1.375,
    };
}

function tdee(string $sex, float $kg, float $cm, int $alter, string $stufe): float
{
    return bmr($sex, $kg, $cm, $alter) * palFactor($stufe);
}

/**
 * Tagesziel aus Grundumsatz, Gesamtumsatz und Wunschtempo.
 *
 * 1 kg Körperfett entspricht rund 7000 kcal. 0,5 kg pro Woche sind also
 * 3500 kcal auf sieben Tage - 500 kcal Defizit am Tag.
 *
 * Zwei Sicherungen, die nicht verhandelbar sind:
 *   - höchstens 25 % unter dem Gesamtumsatz
 *   - niemals unter den Grundumsatz
 * Wer schneller abnehmen will, verliert vor allem Muskeln. Die App bietet
 * das deshalb gar nicht erst an.
 */
function dailyGoal(float $tdee, float $bmr, string $ziel, float $tempoKgWoche): int
{
    $delta = ($tempoKgWoche * 7000.0) / 7.0;
    $wert = match ($ziel) {
        'abnehmen' => $tdee - $delta,
        'zunehmen' => $tdee + $delta,
        default => $tdee,
    };

    if ($ziel === 'abnehmen') {
        $wert = max($wert, $tdee * 0.75, $bmr);
    }
    return (int) round($wert);
}

/**
 * Kalorienverbrauch beim Sport.
 *
 * MET x 3,5 x kg / 200 x Minuten ist die Standardformel. Abgezogen wird
 * der Ruheumsatz derselben Zeitspanne (MET 1) - denn diese Kalorien hätte
 * der Körper ohnehin verbraucht. Ohne den Abzug wird jede Aktivität um
 * 10-30 % zu hoch angesetzt, und das summiert sich über eine Woche zu
 * einem gefühlten Defizit, das es nie gab.
 */
function sportKcal(float $met, float $kg, int $minuten): int
{
    $brutto = $met * 3.5 * $kg / 200.0 * $minuten;
    $ruhe = 1.0 * 3.5 * $kg / 200.0 * $minuten;
    return (int) round(max(0.0, $brutto - $ruhe));
}

/** Alter aus dem Geburtsjahr - reicht völlig, ein Geburtsdatum wäre zu viel Datum. */
function alterAus(int $geburtsjahr): int
{
    return max(14, min(100, (int) date('Y') - $geburtsjahr));
}

/**
 * Rechnet das Profil in Zahlen um und legt sie im Nutzerdatensatz ab.
 *
 * Läuft bei jeder Profil- und Gewichtsänderung neu. Der Server ist die
 * einzige Wahrheit; die Anzeige im Onboarding ist nur eine Vorschau.
 */
function deriveEnergy(array $profil): array
{
    $sex = (string) ($profil['sex'] ?? 'd');
    $kg = (float) ($profil['weightKg'] ?? 75);
    $cm = (float) ($profil['heightCm'] ?? 175);
    $alter = alterAus((int) ($profil['birthYear'] ?? 1990));
    $stufe = (string) ($profil['activity'] ?? 'leicht');
    $ziel = (string) ($profil['goal'] ?? 'halten');
    $tempo = (float) ($profil['paceKgWeek'] ?? 0.5);

    $b = bmr($sex, $kg, $cm, $alter);
    $t = $b * palFactor($stufe);

    return [
        'bmr' => (int) round($b),
        'tdee' => (int) round($t),
        'goalKcal' => dailyGoal($t, $b, $ziel, $tempo),
        // Eiweissziel: 1,6 g/kg ist der in Studien wiederkehrende Wert, bei
        // dem Muskelerhalt im Defizit zuverlaessig funktioniert.
        'proteinG' => (int) round($kg * 1.6),
        'calculatedAt' => date('c'),
    ];
}
