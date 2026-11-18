<?php
/**
 * Lernwelt-KI-Proxy – gemeinsame Bausteine.
 *
 * HTTP (CORS, JSON, Fehler), Firebase-Token-Prüfung, Budget, Ablage.
 * Wird nur eingebunden; direkte Aufrufe sperrt die .htaccess (^_).
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

const FIREBASE_PROJECT = 'seo-automation-6398';
const DATA_DIR = __DIR__ . '/_data';
const ALLOWED_ORIGINS = ['https://lernwelt-app.web.app', 'http://localhost:5173'];
const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

/** Gemini-Aufrufe je Nutzer (= Familie) und Tag, je Endpunkt. Cache-Treffer zählen nicht. */
const USER_DAY_MAX = ['analyze' => 4, 'handwriting' => 30, 'explain' => 20, 'tts' => 60, 'ingest' => 30];
/** Gemini-Aufrufe je Nutzer und Minute (alle Endpunkte zusammen). */
const USER_MINUTE_MAX = 12;
/** Globale Tagesbremse über alle Nutzer. */
const GLOBAL_DAY_MAX = 500;

/* ------------------------------------------------------------------ HTTP */

function send(array $daten, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Fehler im Vertragsformat ApiError. */
function fail(string $fehler, int $status = 400, ?int $retryAfter = null): never
{
    $daten = ['error' => $fehler];
    if ($retryAfter !== null) {
        $retryAfter = max(1, $retryAfter);
        $daten['retryAfter'] = $retryAfter;
        header('Retry-After: ' . $retryAfter);
    }
    send($daten, $status);
}

/**
 * CORS nur für die App. Ohne Origin-Header (curl, Server) gibt es keine
 * CORS-Freigabe – das Token ist trotzdem Pflicht.
 */
function cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        if (!in_array($origin, ALLOWED_ORIGINS, true)) {
            fail('Herkunft nicht erlaubt.', 403);
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Admin-Key');
        header('Access-Control-Expose-Headers: X-Cache, Retry-After');
        header('Access-Control-Max-Age: 86400');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST, OPTIONS');
        fail('Nur POST.', 405);
    }
}

function jsonBody(int $maxBytes): array
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > $maxBytes) {
        fail('Anfrage zu groß.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (!is_string($raw) || strlen($raw) > $maxBytes) {
        fail('Anfrage zu groß.', 413);
    }
    $daten = json_decode($raw, true);
    if (!is_array($daten)) {
        fail('Ungültiges JSON.');
    }
    return $daten;
}

/** Text säubern: Steuerzeichen raus, Länge begrenzen. */
function clean(mixed $wert, int $max): string
{
    if (!is_string($wert) && !is_int($wert) && !is_float($wert)) {
        return '';
    }
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $wert) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function grade(mixed $wert): int
{
    $g = is_int($wert) ? $wert : (int) $wert;
    if ($g < 1 || $g > 4) {
        fail('Klasse muss 1–4 sein.');
    }
    return $g;
}

function subject(mixed $wert): string
{
    return in_array($wert, ['mathe', 'deutsch'], true) ? $wert : fail('Fach muss mathe oder deutsch sein.');
}

function isSkillId(mixed $s): bool
{
    return is_string($s) && preg_match('/^(mathe|deutsch)\.[a-z0-9_.-]{1,60}$/', $s) === 1;
}

function isMisconceptionId(mixed $s): bool
{
    return is_string($s) && preg_match('/^(mathe|deutsch|lesen)\.[a-z0-9_.-]{1,60}$/', $s) === 1;
}

/** Base64-Bild prüfen (PNG und/oder JPEG). @return array{0:string,1:string} [base64, mime] */
function pruefeBild(mixed $roh, array $erlaubt = ['image/png', 'image/jpeg'], int $max = 2_500_000): array
{
    if (!is_string($roh) || $roh === '') {
        fail('Bild fehlt.');
    }
    if (strlen($roh) > $max) {
        fail('Bild zu groß.', 413);
    }
    $bin = base64_decode($roh, true);
    if ($bin === false || strlen($bin) < 64) {
        fail('Bild unlesbar.');
    }
    $mime = match (true) {
        str_starts_with($bin, "\x89PNG\r\n\x1a\n") => 'image/png',
        str_starts_with($bin, "\xFF\xD8\xFF") => 'image/jpeg',
        default => '',
    };
    if (!in_array($mime, $erlaubt, true)) {
        fail('Bildformat nicht erlaubt.');
    }
    return [$roh, $mime];
}

/* ----------------------------------------------------------------- Ablage */

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
}

function readJson(string $datei, mixed $standard = []): mixed
{
    if (!is_file($datei)) {
        return $standard;
    }
    $d = json_decode((string) @file_get_contents($datei), true);
    return $d ?? $standard;
}

function writeJson(string $datei, mixed $daten): void
{
    ensureDir(dirname($datei));
    $tmp = $datei . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @rename($tmp, $datei);
}

/** Ruft $fn mit dem gelesenen Inhalt unter exklusiver Sperre auf und schreibt das Ergebnis zurück. */
function mitSperre(string $datei, callable $fn): mixed
{
    ensureDir(dirname($datei));
    $h = fopen($datei, 'c+');
    if ($h === false) {
        return $fn([])[1];
    }
    flock($h, LOCK_EX);
    $inhalt = stream_get_contents($h);
    $daten = json_decode($inhalt === false ? '' : $inhalt, true);
    [$neu, $rueckgabe] = $fn(is_array($daten) ? $daten : []);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($neu));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $rueckgabe;
}

/* ------------------------------------------------------------ HTTP-Client */

/** @return array{status:int, body:string, headers:array<string,string>} */
function httpRequest(string $methode, string $url, array $header = [], ?string $body = null, int $timeout = 25): array
{
    $ch = curl_init($url);
    $kopf = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HEADERFUNCTION => function ($ch, string $zeile) use (&$kopf): int {
            $teile = explode(':', $zeile, 2);
            if (count($teile) === 2) {
                $kopf[strtolower(trim($teile[0]))] = trim($teile[1]);
            }
            return strlen($zeile);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => is_string($raw) ? $raw : '', 'headers' => $kopf];
}

/* ------------------------------------------------------- Firebase-Token */

function bearerToken(): string
{
    $kopf = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($kopf === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $kopf = $v;
            }
        }
    }
    return preg_match('/^Bearer\s+([A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+)$/', trim($kopf), $t) === 1 ? $t[1] : '';
}

function b64url(string $s): string|false
{
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

/**
 * Google-Zertifikate (kid → PEM), gecacht nach Cache-Control max-age.
 * Unbekannte kid → höchstens alle 5 Minuten neu laden (Schlüsselrotation).
 */
function googleCerts(bool $neuLaden = false): array
{
    $datei = DATA_DIR . '/certs.json';
    $cache = readJson($datei, []);
    $frisch = ($cache['expires'] ?? 0) > time();
    $kurzVorher = ($cache['fetched'] ?? 0) > time() - 300;

    if (!$frisch || ($neuLaden && !$kurzVorher)) {
        $r = httpRequest('GET', CERTS_URL, [], null, 8);
        $certs = json_decode($r['body'], true);
        if ($r['status'] === 200 && is_array($certs) && $certs !== []) {
            $maxAge = preg_match('/max-age=(\d+)/', $r['headers']['cache-control'] ?? '', $m) === 1 ? (int) $m[1] : 3600;
            $cache = ['certs' => $certs, 'fetched' => time(), 'expires' => time() + $maxAge];
            writeJson($datei, $cache);
        }
    }
    $certs = is_array($cache['certs'] ?? null) ? $cache['certs'] : [];

    // Nur lokal (php -S): Test-Zertifikate zusätzlich, damit sich die
    // Prüfung ohne echtes Firebase-Konto durchtesten lässt.
    $test = getenv('LERNWELT_TEST_CERTS');
    if (PHP_SAPI === 'cli-server' && is_string($test) && is_file($test)) {
        $certs += readJson($test, []);
    }
    return $certs;
}

/** Prüft das Firebase-ID-Token und gibt die UID zurück – sonst 401. */
function requireUser(): string
{
    $token = bearerToken();
    if ($token === '') {
        fail('Anmeldung fehlt.', 401);
    }
    [$h64, $p64, $s64] = explode('.', $token);
    $kopf = json_decode((string) b64url($h64), true);
    $claims = json_decode((string) b64url($p64), true);
    $sig = b64url($s64);
    if (!is_array($kopf) || !is_array($claims) || $sig === false || ($kopf['alg'] ?? '') !== 'RS256') {
        fail('Token ungültig.', 401);
    }

    $kid = (string) ($kopf['kid'] ?? '');
    $certs = googleCerts();
    if (!isset($certs[$kid])) {
        $certs = googleCerts(true);
    }
    if (!isset($certs[$kid])) {
        fail('Token ungültig.', 401);
    }
    $key = openssl_pkey_get_public($certs[$kid]);
    if ($key === false || openssl_verify("$h64.$p64", $sig, $key, OPENSSL_ALGO_SHA256) !== 1) {
        fail('Token ungültig.', 401);
    }

    $jetzt = time();
    $sub = $claims['sub'] ?? '';
    $ok = ($claims['aud'] ?? '') === FIREBASE_PROJECT
        && ($claims['iss'] ?? '') === 'https://securetoken.google.com/' . FIREBASE_PROJECT
        && is_int($claims['exp'] ?? null) && $claims['exp'] > $jetzt
        && is_int($claims['iat'] ?? null) && $claims['iat'] <= $jetzt + 60
        && (!isset($claims['auth_time']) || $claims['auth_time'] <= $jetzt + 60)
        && is_string($sub) && $sub !== '' && strlen($sub) <= 128;
    if (!$ok) {
        fail('Token ungültig.', 401);
    }
    return $sub;
}

/* ----------------------------------------------------------------- Budget */

function sekundenBisMitternacht(): int
{
    return (int) (strtotime('tomorrow') - time());
}

/**
 * Bucht einen Gemini-Aufruf für $uid auf $endpunkt – oder antwortet 429.
 * Nur aufrufen, wenn wirklich Gemini gefragt wird (kein Cache-Treffer).
 */
function budgetBuchen(string $uid, string $endpunkt): void
{
    $tag = date('Y-m-d');
    $dir = DATA_DIR . "/usage/$tag";
    $env = getenv('LERNWELT_USER_MAX');
    $maxTag = PHP_SAPI === 'cli-server' && is_string($env) && $env !== '' ? (int) $env : USER_DAY_MAX[$endpunkt];
    $jetzt = time();

    $fehler = mitSperre("$dir/" . hash('sha256', $uid) . '.json', function (array $d) use ($endpunkt, $maxTag, $jetzt): array {
        $minute = array_values(array_filter($d['minute'] ?? [], fn ($t) => is_int($t) && $t > $jetzt - 60));
        if (count($minute) >= USER_MINUTE_MAX) {
            return [$d, ['Zu viele Anfragen.', 60 - ($jetzt - min($minute))]];
        }
        $zahl = (int) ($d['count'][$endpunkt] ?? 0);
        if ($zahl >= $maxTag) {
            return [$d, ['Tageskontingent erschöpft.', sekundenBisMitternacht()]];
        }
        $d['count'][$endpunkt] = $zahl + 1;
        $minute[] = $jetzt;
        $d['minute'] = $minute;
        return [$d, null];
    });
    if ($fehler !== null) {
        fail($fehler[0], 429, $fehler[1]);
    }

    $global = mitSperre("$dir/_global.json", function (array $d): array {
        $zahl = (int) ($d['count'] ?? 0);
        if ($zahl >= GLOBAL_DAY_MAX) {
            return [$d, false];
        }
        $d['count'] = $zahl + 1;
        return [$d, true];
    });
    if (!$global) {
        fail('Tageskontingent erschöpft.', 429, sekundenBisMitternacht());
    }

    aufraeumen();
}

/** Gelegentlich alte Zähltage löschen. */
function aufraeumen(): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }
    foreach (glob(DATA_DIR . '/usage/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (basename($dir) < date('Y-m-d', strtotime('-7 days'))) {
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
        }
    }
}
