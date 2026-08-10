<?php
/**
 * Die Einkaufsliste als Rezeptseite - für den Import in Bring!.
 *
 * Bring holt sich Zutaten nicht über eine Schnittstelle, sondern indem es
 * eine Webseite abruft und darin schema.org-Auszeichnung sucht
 * (Recipe / recipeIngredient). Man übergibt die Adresse an
 *   https://api.getbring.com/rest/bringrecipes/deeplink?url=…&source=web
 * und Bring öffnet die App mit der Frage, ob die Zutaten auf die
 * bestehende Liste sollen. Genau das ist gewünscht: ergänzen, nicht
 * ersetzen.
 *
 * Warum das eine PHP-Seite sein MUSS und keine Seite der App: Bring ruft
 * die Adresse mit seinem eigenen Server ab und führt kein JavaScript aus.
 * Die App holt ihre Daten aber erst im Browser nach - Bring sähe eine
 * leere Seite. Hier steht alles schon im ausgelieferten HTML.
 *
 * Sichtbar ist ausschliesslich die Einkaufsliste. Kein Name, keine
 * Kalorien, kein Zugang zum Konto.
 */

declare(strict_types=1);
require __DIR__ . '/api/_lib.php';
require __DIR__ . '/api/_plan.php';

$token = is_string($_GET['k'] ?? null) ? $_GET['k'] : '';

if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
    http_response_code(404);
    exit('Liste nicht gefunden.');
}

$liste = readJson(LISTS_DIR . '/' . $token . '.json', []);
if ($liste === [] || strtotime((string) ($liste['erzeugtAm'] ?? '')) < time() - 14 * 86400) {
    http_response_code(404);
    exit('Liste nicht gefunden.');
}

/*
 * Nur was noch offen ist. Was im Laden schon im Wagen liegt, gehört
 * nicht noch einmal auf die Bring-Liste.
 */
$zutaten = [];
foreach ($liste['posten'] as $p) {
    if ($p['ab'] ?? false) {
        continue;
    }
    $text = trim((string) ($p['text'] ?? ''));
    $zutaten[] = $text !== '' ? $text . ' ' . $p['name'] : (string) $p['name'];
}

if ($zutaten === []) {
    $zutaten[] = 'Alles erledigt';
}

$rezept = [
    '@context' => 'https://schema.org',
    '@type' => 'Recipe',
    'name' => 'Einkauf ' . date('d.m.', strtotime((string) $liste['erzeugtAm'])),
    'description' => 'Einkaufsliste aus dem Wochenplan.',
    'recipeYield' => '1',
    'recipeIngredient' => $zutaten,
    // Bring erwartet mindestens einen Zubereitungsschritt, sonst gilt die
    // Seite bei manchen Fassungen nicht als Rezept.
    'recipeInstructions' => [
        ['@type' => 'HowToStep', 'text' => 'Einkaufen.'],
    ],
];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
// Diese Seite gehört in keinen Suchindex - sie ist nur für Bring da.
header('X-Robots-Tag: noindex, nofollow');

$json = json_encode($rezept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$anzahl = count($zutaten);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Einkauf</title>
<script type="application/ld+json"><?= $json ?></script>
<style>
  body { margin:0; padding:2rem 1.5rem; background:#0d1020; color:#f4f2f8;
         font-family: system-ui, -apple-system, sans-serif; }
  h1 { font-size:1.3rem; font-weight:600; margin:0 0 .25rem; }
  p  { color:#a9aec4; margin:0 0 1.5rem; font-size:.95rem; }
  ul { list-style:none; padding:0; margin:0; max-width:32rem; }
  li { padding:.6rem 0; border-bottom:1px solid rgba(255,255,255,.08); }
</style>
</head>
<body>
  <h1>Einkauf</h1>
  <p><?= $anzahl ?> Posten &middot; für den Import in Bring!</p>
  <ul>
    <?php foreach ($zutaten as $z): ?>
      <li><?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
