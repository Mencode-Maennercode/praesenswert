<?php
/**
 * Rezeptbilder von Pexels.
 *
 *   fuer     Bild für ein Rezept holen (und im Rezept merken)
 *   naechstes  anderes Bild nehmen, wenn das erste nicht passt
 *
 * Warum über den eigenen Server: Die CSP der Hauptseite erlaubt
 * Verbindungen nur zur eigenen Domain - ein fetch auf die Pexels-API
 * würde stumm blockiert. Die Bilder selbst darf der Browser dann direkt
 * von Pexels laden, dafür ist img-src https: offen.
 *
 * Gesucht wird EINMAL je Rezept. Die Adresse landet im Rezept, und da
 * die Rezeptsammlung allen Nutzern gemeinsam gehört, sucht auch niemand
 * zweimal nach derselben Lasagne.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_plan.php';

cors();
requireMethod('POST');

$body = jsonBody(4 * 1024);
[$uid, $user] = requireUser($body);
rateLimit('bild', 80, 600, $uid);

$rezept = rezeptLaden(clean($body['rezept'] ?? '', 20));
if ($rezept === null) {
    fail('Rezept nicht gefunden.', 404, 'weg');
}

$aktion = clean($body['action'] ?? 'fuer', 20);

// Schon da und kein Wunsch nach einem anderen? Dann nichts tun.
if ($aktion === 'fuer' && ($rezept['bild'] ?? '') !== '') {
    send(withFreshToken(['ok' => true, 'bild' => $rezept['bild']], $uid, $user, $body));
}

$key = pexelsKey();
if ($key === '') {
    send(withFreshToken(['ok' => true, 'bild' => '', 'grund' => 'kein-schluessel'], $uid, $user, $body));
}

/*
 * Die Trefferliste wird im Rezept aufbewahrt, nicht nur der eine Treffer.
 * "Anderes Bild" kostet dadurch keine neue Suche - und die Auswahl gilt
 * für alle, weil die Rezepte gemeinsam sind.
 */
$kandidaten = is_array($rezept['bildAlt'] ?? null) ? $rezept['bildAlt'] : [];

if ($kandidaten === []) {
    $kandidaten = sucheBilder((string) $rezept['titel'], $key);
    $rezept['bildAlt'] = $kandidaten;
}

if ($kandidaten === []) {
    $rezept['bild'] = '';
    writeJson(RECIPES_DIR . '/' . $rezept['id'] . '.json', $rezept);
    send(withFreshToken(['ok' => true, 'bild' => '', 'grund' => 'nichts-gefunden'], $uid, $user, $body));
}

if ($aktion === 'naechstes') {
    // Im Kreis weiterblättern, damit man nie in einer Sackgasse landet.
    $jetzt = array_search($rezept['bild'] ?? '', $kandidaten, true);
    $index = $jetzt === false ? 0 : ($jetzt + 1) % count($kandidaten);
} else {
    $index = 0;
}

$rezept['bild'] = $kandidaten[$index];
writeJson(RECIPES_DIR . '/' . $rezept['id'] . '.json', $rezept);

send(withFreshToken(['ok' => true, 'bild' => $rezept['bild']], $uid, $user, $body));

/* ------------------------------------------------------------------ Suche */

/**
 * Sucht Fotos zu einem Gerichtsnamen.
 *
 * Der Suchbegriff bekommt "Essen" mit auf den Weg: "Bowl" allein liefert
 * bei Pexels Schüsseln aus dem Haushaltsregal, "Bowl Essen" das Gericht.
 *
 * @return string[] Adressen, beste zuerst
 */
function sucheBilder(string $titel, string $key): array
{
    // Klammern und Mengenangaben stören die Suche mehr als sie helfen.
    $frage = trim(preg_replace('/\(.*?\)|\d+\s*(g|ml|kg|l)\b/iu', '', $titel) ?? $titel);
    $frage = mb_substr($frage, 0, 60) . ' Essen';

    $url = 'https://api.pexels.com/v1/search?per_page=6&orientation=landscape&locale=de-DE&query='
        . rawurlencode($frage);

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $key],
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

    if ($raw === null) {
        return [];
    }

    $daten = json_decode($raw, true);
    $out = [];
    foreach ((array) ($daten['photos'] ?? []) as $foto) {
        // "large" ist bei Pexels rund 940 px breit - genug für eine
        // Kachel auf dem Handy und klein genug fürs Mobilfunknetz.
        $adr = (string) ($foto['src']['large'] ?? $foto['src']['medium'] ?? '');
        if (filter_var($adr, FILTER_VALIDATE_URL)) {
            $out[] = $adr;
        }
    }
    return $out;
}
