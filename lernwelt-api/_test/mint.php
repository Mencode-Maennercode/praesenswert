<?php
/**
 * Testhilfe (nur CLI): erzeugt ein Test-Schlüsselpaar + Zertifikat und signiert
 * Firebase-artige ID-Tokens. Der lokale Server (php -S) akzeptiert das Zertifikat
 * nur über LERNWELT_TEST_CERTS – auf dem Webserver wirkt das nie.
 * Ausgabe: Shell-Zuweisungen.
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$tmp = __DIR__ . '/tmp';
@mkdir($tmp, 0700, true);

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$csr = openssl_csr_new(['commonName' => 'lernwelt-test'], $key);
$cert = openssl_csr_sign($csr, null, $key, 1);
openssl_x509_export($cert, $pem);
file_put_contents("$tmp/certs.json", json_encode(['test-kid' => $pem]));

$b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$sign = function (array $claims, $k = null) use ($b64, $key): string {
    $h = $b64(json_encode(['alg' => 'RS256', 'kid' => 'test-kid', 'typ' => 'JWT']));
    $p = $b64(json_encode($claims));
    openssl_sign("$h.$p", $sig, $k ?? $key, OPENSSL_ALGO_SHA256);
    return "$h.$p." . $b64($sig);
};
$t = time();
$basis = ['iss' => 'https://securetoken.google.com/seo-automation-6398', 'aud' => 'seo-automation-6398',
    'auth_time' => $t - 10, 'iat' => $t - 10, 'exp' => $t + 3600, 'sub' => 'test-user-1', 'user_id' => 'test-user-1'];

$fremd = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$tokens = [
    'TOK_OK' => $sign($basis),
    'TOK_ADMIN' => $sign(['sub' => 'test-admin'] + $basis),
    'TOK_EXPIRED' => $sign(['exp' => $t - 5] + $basis),
    'TOK_AUD' => $sign(['aud' => 'anderes-projekt'] + $basis),
    'TOK_SIG' => $sign($basis, $fremd),
];
foreach ($tokens as $n => $v) {
    echo "$n=$v\n";
}
echo 'CERTS=' . str_replace('\\', '/', realpath("$tmp/certs.json")) . "\n";
