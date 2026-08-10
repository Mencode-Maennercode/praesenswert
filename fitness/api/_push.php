<?php
/**
 * Web Push mit VAPID - in reinem PHP, ohne Composer.
 *
 * Bewusst OHNE verschlüsselte Nutzlast. Das ist die wichtigste
 * Entscheidung dieser Datei, deshalb der Grund:
 *
 * Die App braucht genau vier Sätze ("Frühstück?", "Mittag?", "Abend?",
 * "Wiegetag"). Der Service Worker kennt Uhrzeit und Wochentag und leitet
 * sie selbst ab. Damit entfällt der gesamte AES-GCM/HKDF-Zweig nach
 * RFC 8291 - rund vierzig Zeilen, in denen ein falsch gefülltes Nonce
 * zu einer Nachricht führt, die auf genau einem von drei Browsern
 * ankommt und sonst nirgends. Ohne lokales HTTPS ist so etwas kaum zu
 * finden. Nachrüstbar bleibt es: pushSend() nimmt den Parameter schon.
 *
 * Was NICHT wegfällt, ist die ES256-Signatur. Sie ist der wahrscheinlichste
 * Fehlerort und deshalb unten ausführlich kommentiert.
 */

declare(strict_types=1);

const VAPID_FILE = DATA_DIR . '/vapid.json';
const VAPID_SUBJECT = 'https://praesenzwert.de/fitness/';

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64urlDecode(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/'), true);
}

/**
 * Das Schlüsselpaar. Wird beim ersten Aufruf erzeugt und bleibt dann.
 *
 * ACHTUNG: Ändert sich dieser Schlüssel, sind ALLE bestehenden Abos
 * wertlos - die Browser haben den öffentlichen Teil gespeichert. Deshalb
 * liegt die Datei in _data/ und ist vom Deploy ausgenommen.
 */
function vapidKeys(): array
{
    ensureDirs();
    $vorhanden = readJson(VAPID_FILE, []);
    if (($vorhanden['privatePem'] ?? '') !== '' && ($vorhanden['publicRaw'] ?? '') !== '') {
        return $vorhanden;
    }

    $res = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($res === false) {
        fail('Schlüssel konnten nicht erzeugt werden.', 500, 'openssl');
    }

    openssl_pkey_export($res, $pem);
    $d = openssl_pkey_get_details($res);

    /*
     * str_pad ist hier kein Schönheitsfehler-Fix, sondern Pflicht.
     *
     * openssl liefert x und y ohne führende Nullbytes. Ist eine der
     * Koordinaten zufällig kleiner als 2^248 - das passiert bei etwa
     * jedem 128. Schlüssel -, fehlt ein Byte, der öffentliche Schlüssel
     * ist 64 statt 65 Byte lang, und der Browser lehnt ihn mit
     * "Invalid raw ECDSA P-256 public key" ab. Sporadisch, nicht
     * reproduzierbar, und man sucht tagelang an der falschen Stelle.
     */
    $x = str_pad((string) $d['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad((string) $d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $pub = "\x04" . $x . $y; // unkomprimierter Punkt, 65 Byte

    $keys = ['privatePem' => $pem, 'publicRaw' => b64url($pub), 'createdAt' => date('c')];
    writeJson(VAPID_FILE, $keys);
    @chmod(VAPID_FILE, 0600);
    return $keys;
}

/**
 * DER-Signatur in das rohe Format von JWS überführen.
 *
 * openssl_sign liefert ASN.1: SEQUENCE { INTEGER r, INTEGER s }.
 * JWS/RFC 7518 will schlicht 64 Byte: r || s, je 32 Byte, mit führenden
 * Nullen aufgefüllt. Zwei Fallstricke stecken darin:
 *   - ASN.1 stellt einer Zahl mit gesetztem obersten Bit ein 0x00 voran,
 *     damit sie nicht als negativ gilt. Das muss weg.
 *   - Kurze Zahlen müssen links auf 32 Byte aufgefüllt werden.
 */
function derToRaw(string $der): string
{
    $off = 0;
    if (($der[$off++] ?? '') !== "\x30") {
        throw new RuntimeException('DER: kein SEQUENCE');
    }

    $len = ord($der[$off++]);
    if ($len & 0x80) {
        $off += ($len & 0x7f); // lange Längenform überspringen
    }

    $int = static function () use ($der, &$off): string {
        if (($der[$off++] ?? '') !== "\x02") {
            throw new RuntimeException('DER: kein INTEGER');
        }
        $l = ord($der[$off++]);
        $v = substr($der, $off, $l);
        $off += $l;
        $v = ltrim($v, "\x00");
        return str_pad($v, 32, "\x00", STR_PAD_LEFT);
    };

    return $int() . $int();
}

/**
 * Der VAPID-Token für einen Push-Dienst.
 *
 * 'aud' ist NUR Schema und Host des Endpunkts - ohne Pfad, ohne Port.
 * Mit Pfad antwortet FCM mit 401 und Apple mit 403, und die Meldung sagt
 * nicht, woran es liegt.
 */
function vapidJwt(string $endpoint, string $privatePem): string
{
    $p = parse_url($endpoint);
    $aud = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');

    $head = b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $body = b64url((string) json_encode([
        'aud' => $aud,
        // Zwölf Stunden. Apple und FCM lehnen alles über 24 Stunden ab.
        'exp' => time() + 12 * 3600,
        'sub' => VAPID_SUBJECT,
    ], JSON_UNESCAPED_SLASHES));

    $der = '';
    if (!openssl_sign($head . '.' . $body, $der, $privatePem, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Signatur fehlgeschlagen');
    }

    return $head . '.' . $body . '.' . b64url(derToRaw($der));
}

/**
 * Schickt eine Benachrichtigung an ein Abo.
 *
 * @param string|null $payload Für später - siehe Kopf der Datei.
 * @return int HTTP-Status. 201 heisst angenommen, 404 und 410 heissen
 *             "Abo ist tot und gehört gelöscht".
 */
function pushSend(array $abo, ?string $payload = null, string $topic = 'aura'): int
{
    $endpoint = (string) ($abo['endpoint'] ?? '');
    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return 0;
    }

    $keys = vapidKeys();
    try {
        $jwt = vapidJwt($endpoint, (string) $keys['privatePem']);
    } catch (RuntimeException) {
        return 0;
    }

    $header = [
        'TTL: 86400',
        'Urgency: normal',
        // Ersetzt eine noch nicht zugestellte gleichartige Erinnerung,
        // statt sie zu stapeln.
        'Topic: ' . substr($topic, 0, 32),
        'Authorization: vapid t=' . $jwt . ', k=' . $keys['publicRaw'],
        'Content-Length: 0',
    ];

    if ($payload !== null) {
        // Reserviert - siehe Kopf der Datei. Ohne Verschlüsselung nach
        // RFC 8291 würde jeder Dienst die Nachricht ablehnen.
        return 0;
    }

    if (!function_exists('curl_init')) {
        return 0;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '',
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status;
}

/**
 * Verschickt an alle Abos eines Nutzers und räumt tote Abos weg.
 *
 * Das Aufräumen ist kein Beiwerk: Abos sterben ständig - neu installiert,
 * Browserdaten gelöscht, iOS hat lange nichts von der App gesehen. Ohne
 * Aufräumen wächst die Liste, und jeder Versand dauert länger für nichts.
 *
 * @return array{0: int, 1: array} [erfolgreich, bereinigter Nutzer]
 */
function pushAnNutzer(array $user, string $topic = 'aura'): array
{
    $abos = is_array($user['push'] ?? null) ? $user['push'] : [];
    if ($abos === []) {
        return [0, $user];
    }

    $bleiben = [];
    $erfolge = 0;

    foreach ($abos as $abo) {
        $status = pushSend($abo, null, $topic);

        if ($status >= 200 && $status < 300) {
            $abo['lastOk'] = date('c');
            $abo['fails'] = 0;
            $bleiben[] = $abo;
            $erfolge++;
            continue;
        }

        // Endgültig weg - der Dienst kennt das Abo nicht mehr.
        if ($status === 404 || $status === 410) {
            continue;
        }

        // Alles andere kann vorübergehend sein. Nach fünf Fehlversuchen
        // in Folge ist es das aber nicht mehr.
        $fails = (int) ($abo['fails'] ?? 0) + 1;
        if ($fails < 5) {
            $abo['fails'] = $fails;
            $bleiben[] = $abo;
        }
    }

    $user['push'] = array_values($bleiben);
    return [$erfolge, $user];
}
