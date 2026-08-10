<?php
/**
 * Produktsuche über Open Food Facts.
 *
 * Frei, ohne Schlüssel, ohne Anmeldung, mit einer sehr guten deutschen
 * Abdeckung. Für verpackte Lebensmittel ist das jeder KI-Schätzung
 * überlegen: Die Nährwerte stehen auf der Packung, sie müssen nicht
 * geraten werden.
 *
 * Warum über den eigenen Server statt direkt aus dem Browser: Die CSP der
 * Hauptseite erlaubt Verbindungen nur zur eigenen Domain. Ein fetch() auf
 * openfoodfacts.org würde stumm blockiert.
 *
 * Nebenwirkung, die den Umweg lohnt: Die Antworten lassen sich hier
 * zwischenspeichern. Wer dieselbe Milch dreimal die Woche scannt, fragt
 * nur einmal nach.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody(2 * 1024);
[$uid, $user] = requireUser($body);
rateLimit('barcode', 60, 600, $uid);

$code = clean($body['code'] ?? '', 20);

/*
 * EAN-13, EAN-8, UPC-A und ein paar Sonderformen - alles zwischen 8 und
 * 14 Ziffern. Buchstaben gibt es dort nicht, und ohne diese Prüfung
 * landete jede Eingabe ungefiltert in einer URL.
 */
if (preg_match('/^\d{8,14}$/', $code) !== 1) {
    fail('Kein gültiger Barcode.', 400, 'code');
}

$treffer = ausCache($code);
if ($treffer === null) {
    $treffer = vonOpenFoodFacts($code);
    if ($treffer !== null) {
        inCache($code, $treffer);
    }
}

if ($treffer === null) {
    send(withFreshToken(['ok' => true, 'gefunden' => false], $uid, $user, $body));
}

send(withFreshToken(['ok' => true, 'gefunden' => true, 'produkt' => $treffer], $uid, $user, $body));

/* ------------------------------------------------------------ Zwischenlager */

function cachePfad(string $code): string
{
    return DATA_DIR . '/barcodes/' . $code . '.json';
}

function ausCache(string $code): ?array
{
    $datei = cachePfad($code);
    if (!is_file($datei)) {
        return null;
    }
    // Dreissig Tage. Nährwerte ändern sich selten, und wenn, ist die
    // Abweichung kleiner als die Portionsschätzung sowieso.
    if (filemtime($datei) < time() - 30 * 86400) {
        return null;
    }
    $d = readJson($datei);
    return $d === [] ? null : $d;
}

function inCache(string $code, array $produkt): void
{
    $dir = DATA_DIR . '/barcodes';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    writeJson(cachePfad($code), $produkt);
}

/* ------------------------------------------------------------------ Abruf */

function vonOpenFoodFacts(string $code): ?array
{
    $felder = 'product_name,product_name_de,brands,quantity,serving_size,nutriments,image_front_small_url';
    $url = "https://world.openfoodfacts.org/api/v2/product/{$code}.json?fields={$felder}";

    /*
     * Open Food Facts verlangt einen aussagekräftigen User-Agent und
     * sperrt anonyme Massenabrufe. Der Hinweis ist ausdrücklich in ihrer
     * Nutzungsordnung - ohne ihn kommen irgendwann 403er.
     */
    $header = ['User-Agent: AURA/1.0 (privates Ernaehrungstagebuch; praesenzwert.de)'];

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
            ]);
            $antwort = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($antwort) && $status >= 200 && $status < 300) {
                $raw = $antwort;
            }
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'header' => implode("\r\n", $header),
            'timeout' => 12,
            'ignore_errors' => true,
        ]]);
        $antwort = @file_get_contents($url, false, $ctx);
        $raw = is_string($antwort) ? $antwort : null;
    }

    if ($raw === null) {
        return null;
    }

    $daten = json_decode($raw, true);
    if (!is_array($daten) || (int) ($daten['status'] ?? 0) !== 1) {
        return null;
    }

    $p = is_array($daten['product'] ?? null) ? $daten['product'] : [];
    $n = is_array($p['nutriments'] ?? null) ? $p['nutriments'] : [];

    $name = clean($p['product_name_de'] ?? '', 80);
    if ($name === '') {
        $name = clean($p['product_name'] ?? '', 80);
    }
    if ($name === '') {
        return null;
    }

    $kcal = naehrwert($n, 'energy-kcal_100g');
    if ($kcal === null) {
        // Manche Einträge führen nur Kilojoule. 1 kcal = 4,184 kJ.
        $kj = naehrwert($n, 'energy-kj_100g') ?? naehrwert($n, 'energy_100g');
        $kcal = $kj !== null ? $kj / 4.184 : null;
    }
    if ($kcal === null || $kcal <= 0) {
        // Ohne Kalorien ist der Eintrag für uns wertlos - dann lieber
        // "nicht gefunden" melden als eine Null anzubieten.
        return null;
    }

    return [
        'code' => (string) ($daten['code'] ?? ''),
        'name' => $name,
        'marke' => clean($p['brands'] ?? '', 60),
        'menge' => clean($p['quantity'] ?? '', 40),
        'portion' => portionGramm($p['serving_size'] ?? ''),
        'bild' => filter_var($p['image_front_small_url'] ?? '', FILTER_VALIDATE_URL) ?: '',
        // Alles je 100 g - die Umrechnung auf die Portion macht die Oberfläche.
        'kcal100' => (int) round(min(900, max(1, $kcal))),
        'p100' => round(min(100, max(0, naehrwert($n, 'proteins_100g') ?? 0)), 1),
        'c100' => round(min(100, max(0, naehrwert($n, 'carbohydrates_100g') ?? 0)), 1),
        'f100' => round(min(100, max(0, naehrwert($n, 'fat_100g') ?? 0)), 1),
    ];
}

function naehrwert(array $n, string $feld): ?float
{
    return isset($n[$feld]) && is_numeric($n[$feld]) ? (float) $n[$feld] : null;
}

/**
 * "30 g", "1 Riegel (21,5 g)", "250ml" - die Portionsangabe ist Freitext.
 * Gesucht ist die erste Zahl mit g oder ml dahinter.
 */
function portionGramm(mixed $roh): int
{
    if (!is_string($roh) || $roh === '') {
        return 0;
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|ml)\b/i', $roh, $t) !== 1) {
        return 0;
    }
    $wert = (float) str_replace(',', '.', $t[1]);
    return $wert > 0 && $wert <= 2000 ? (int) round($wert) : 0;
}
