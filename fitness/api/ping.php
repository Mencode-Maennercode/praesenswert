<?php
/**
 * Betriebsprüfung. Beantwortet in einem Aufruf alle Fragen, die man sonst
 * erst merkt, wenn ein Feature auf dem Server nicht läuft:
 *
 *   - Ist PHP alt genug für declare(strict_types) und neu genug für hash_hkdf?
 *   - Sind openssl und curl da? (Ohne openssl kein Web Push, ohne curl kein Gemini)
 *   - Darf PHP in _data schreiben?
 *   - Wie groß darf ein POST sein? (Ein Essensfoto braucht mehr als die
 *     üblichen 2 MB Standardeinstellung nicht - aber eine Sprachaufnahme schon)
 *   - Und vor allem: Welche Header liefert Apache hier tatsächlich aus?
 *
 * Der letzte Punkt ist der eigentliche Grund für diese Datei. Die
 * Permissions-Policy der Hauptseite verbietet Kamera und Mikrofon und wird
 * in Unterverzeichnisse vererbt. Ob der Override in der .htaccess greift,
 * sieht man sonst nirgends - der Browser meldet keinen Fehler, die
 * Geräteliste ist einfach leer.
 *
 * Aufruf:  https://praesenzwert.de/fitness/api/ping.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDir = __DIR__ . '/../_data';

/** Schreibtest, der auch wirklich schreibt - is_writable() lügt bei manchen Setups. */
function writeTest(string $dir): array
{
    if (!is_dir($dir)) {
        return ['ok' => false, 'grund' => 'Verzeichnis fehlt'];
    }
    $probe = $dir . '/.probe-' . bin2hex(random_bytes(4));
    $written = @file_put_contents($probe, 'x');
    if ($written === false) {
        return ['ok' => false, 'grund' => 'kein Schreibrecht'];
    }
    @unlink($probe);
    return ['ok' => true];
}

/**
 * Die tatsächlich ausgelieferten Antwortheader.
 *
 * apache_response_headers() gibt es nur unter mod_php. Läuft PHP als
 * FPM/CGI - auf netcup der Normalfall - liefert headers_list() zumindest
 * das, was PHP selbst gesetzt hat. Die von Apache ergänzten Header
 * (Permissions-Policy, CSP) tauchen dann NICHT auf. Deshalb steht unten
 * der curl-Hinweis: das ist die verlässliche Prüfung.
 */
function responseHeaders(): array
{
    if (function_exists('apache_response_headers')) {
        return apache_response_headers();
    }
    return ['_hinweis' => 'PHP läuft nicht als Apache-Modul - siehe pruefung.befehl'];
}

$funktionen = [];
foreach (
    [
        'hash_hkdf',            // Web Push: Schlüsselableitung (PHP >= 7.1.2)
        'openssl_pkey_new',     // Web Push: VAPID-Schlüsselpaar
        'openssl_sign',         // Web Push: ES256-Signatur
        'openssl_pkey_derive',  // Web Push: ECDH, nur für verschlüsselte Payloads
        'password_hash',        // Konten
        'random_bytes',         // Token
        'curl_init',            // Gemini, Pexels, Open Food Facts
        'flock',                // gleichzeitige Schreibzugriffe
        'gzencode',             // tägliche Sicherung
    ] as $fn
) {
    $funktionen[$fn] = function_exists($fn);
}

// Kann openssl wirklich eine P-256-Kurve? Manche Builds sind beschnitten.
$p256 = false;
if (function_exists('openssl_get_curve_names')) {
    $curves = openssl_get_curve_names();
    $p256 = is_array($curves) && in_array('prime256v1', $curves, true);
}

$antwort = [
    'ok' => true,
    'app' => 'aura',
    'zeit' => date('c'),
    'php' => [
        'version' => PHP_VERSION,
        'ausreichend' => PHP_VERSION_ID >= 80100,
        'sapi' => PHP_SAPI,
        'zeitzone' => date_default_timezone_get(),
    ],
    'erweiterungen' => [
        'openssl' => extension_loaded('openssl'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'fileinfo' => extension_loaded('fileinfo'),
        'zlib' => extension_loaded('zlib'),
    ],
    'funktionen' => $funktionen,
    'kurve_prime256v1' => $p256,
    'grenzen' => [
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
    ],
    'speicher' => writeTest($dataDir),
    'antwortheader' => responseHeaders(),
    'pruefung' => [
        'hinweis' => 'Die beiden folgenden Prüfungen sind nur auf dem echten Server aussagekräftig.',
        'befehl' => 'curl -I https://praesenzwert.de/fitness/',
        'erwartet' => 'Permissions-Policy: camera=(self), microphone=(self), geolocation=()',
        'datenspeicher' => 'https://praesenzwert.de/fitness/_data/ muss 403 liefern',
    ],
];

/*
 * Ein einziger fehlender Baustein macht ein ganzes Feature unmöglich.
 * Deshalb hier eine klare Liste statt einer Sammlung von true/false, die
 * man selbst durchsehen müsste.
 */
$probleme = [];
if (PHP_VERSION_ID < 80100) {
    $probleme[] = 'PHP ist älter als 8.1 - im netcup-Panel höher stellen.';
}
if (!extension_loaded('openssl') || !$p256) {
    $probleme[] = 'openssl mit prime256v1 fehlt - ohne das gibt es kein Web Push.';
}
if (!extension_loaded('curl')) {
    $probleme[] = 'curl fehlt - die KI-Aufrufe fallen auf den langsameren Stream-Weg zurück.';
}
if (!$funktionen['hash_hkdf']) {
    $probleme[] = 'hash_hkdf fehlt - Web Push nicht möglich.';
}
if (!($antwort['speicher']['ok'] ?? false)) {
    $probleme[] = '_data ist nicht beschreibbar - keine Konten, keine Einträge.';
}
$postMax = (int) ini_get('post_max_size');
if ($postMax > 0 && $postMax < 8) {
    $probleme[] = "post_max_size ist {$postMax}M - Sprachaufnahmen brauchen 8M. "
        . 'Per .user.ini im Wurzelverzeichnis anheben.';
}

$antwort['probleme'] = $probleme;
$antwort['ok'] = $probleme === [];

echo json_encode($antwort, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
