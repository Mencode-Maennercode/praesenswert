<?php
/**
 * Schlüsselablage und Notbremsen. Nur für den Eigentümer.
 *
 *   status     Was ist eingerichtet, was fehlt?
 *   setKey     Gemini- oder Pexels-Schlüssel ablegen
 *   delKey     Schlüssel entfernen
 *   invite     Einladungscode setzen oder abschalten
 *
 * Warum es das gibt: Die API-Schlüssel liegen in _data/ und sind vom
 * Deploy ausgenommen - sonst stünden sie im Git-Repo. Sie müssen also auf
 * dem Server abgelegt werden, ohne über das Repo zu gehen. Diese Datei ist
 * dieser Weg.
 *
 * Der Wert selbst wird nie zurückgegeben, nur ob und seit wann er da ist.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);
[$uid, $user] = requireOwner($body);
rateLimit('admin', 40, 600, $uid);

match (clean($body['action'] ?? '', 20)) {
    'status' => status($uid, $user, $body),
    'setKey' => setzeSchluessel($uid, $user, $body),
    'delKey' => loescheSchluessel($uid, $user, $body),
    'invite' => einladung($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

/** Welche Datei gehört zu welchem Namen. Nichts anderes ist erlaubt. */
function dateiFuer(string $art): string
{
    return match ($art) {
        'gemini' => AI_KEY_FILE,
        'pexels' => PEXELS_FILE,
        default => fail('Unbekannter Schlüssel.', 400, 'art'),
    };
}

function status(string $uid, array $user, array $body): never
{
    /*
     * Gemeldet wird, ob ein Schlüssel WIRKT - nicht, ob eine bestimmte
     * Datei existiert. Der Gemini-Schlüssel darf auch von der Grillparty
     * auf demselben Webspace kommen; die Anzeige "fehlt", während die
     * Fotoerkennung längst läuft, wäre schlicht falsch.
     */
    $daten = [
        'gemini' => [
            'da' => aiKey() !== '',
            'quelle' => is_file(AI_KEY_FILE) ? 'eigen' : (aiKey() !== '' ? 'grillparty' : ''),
            'seit' => is_file(AI_KEY_FILE) ? date('c', (int) filemtime(AI_KEY_FILE)) : null,
        ],
        'pexels' => [
            'da' => pexelsKey() !== '',
            'quelle' => pexelsKey() !== '' ? 'eigen' : '',
            'seit' => is_file(PEXELS_FILE) ? date('c', (int) filemtime(PEXELS_FILE)) : null,
        ],
    ];

    $nutzung = readJson(DATA_DIR . '/ai-usage.json', []);
    $index = userIndex();

    send(withFreshToken([
        'ok' => true,
        'schluessel' => $daten,
        'einladungAktiv' => inviteCode() !== '',
        'nutzer' => count($index),
        'kiHeute' => ($nutzung['day'] ?? '') === date('Y-m-d') ? (int) ($nutzung['count'] ?? 0) : 0,
        'kiMax' => AI_DAILY_MAX,
    ], $uid, $user, $body));
}

function setzeSchluessel(string $uid, array $user, array $body): never
{
    $datei = dateiFuer(clean($body['art'] ?? '', 20));
    $wert = is_string($body['wert'] ?? null) ? trim($body['wert']) : '';

    /*
     * Grob prüfen, statt blind zu speichern. Ein versehentlich mitkopiertes
     * Leerzeichen oder ein halber Schlüssel fällt sonst erst auf, wenn die
     * erste Fotoanalyse scheitert - und dort sieht man den Grund nicht.
     *
     * Der Punkt gehört ausdrücklich dazu: Google gibt inzwischen Schlüssel
     * der Form "AQ.Ab8..." aus, nicht mehr nur die alten "AIza..."-Ketten.
     * Ohne ihn hätte diese Prüfung genau den Schlüssel abgelehnt, für den
     * sie gedacht ist.
     */
    if (preg_match('/^[A-Za-z0-9._\-]{20,200}$/', $wert) !== 1) {
        fail('Das sieht nicht nach einem Schlüssel aus.', 400, 'format');
    }

    ensureDirs();
    if (file_put_contents($datei, $wert, LOCK_EX) === false) {
        fail('Konnte nicht speichern.', 500);
    }
    @chmod($datei, 0600);

    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

function loescheSchluessel(string $uid, array $user, array $body): never
{
    $datei = dateiFuer(clean($body['art'] ?? '', 20));
    if (is_file($datei)) {
        @unlink($datei);
    }
    send(withFreshToken(['ok' => true], $uid, $user, $body));
}

/**
 * Der Einladungscode ist die Notbremse gegen Massenanmeldung.
 *
 * Standardmässig aus - die Registrierung soll offen sein. Ein leerer Wert
 * schaltet ihn wieder ab.
 */
function einladung(string $uid, array $user, array $body): never
{
    $code = clean($body['code'] ?? '', 60);

    if ($code === '') {
        if (is_file(INVITE_FILE)) {
            @unlink(INVITE_FILE);
        }
    } else {
        ensureDirs();
        file_put_contents(INVITE_FILE, $code, LOCK_EX);
        @chmod(INVITE_FILE, 0600);
    }

    send(withFreshToken(['ok' => true, 'aktiv' => $code !== ''], $uid, $user, $body));
}
