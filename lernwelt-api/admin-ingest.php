<?php
/**
 * admin-ingest.php – Buch-/Arbeitsblattfotos → Thema, Skill-IDs, Wörter, Beispiele.
 * Nur Admin: UID in Firestore config/admins.uids UND Admin-Passwort im Header X-Admin-Key.
 * Fotos werden nicht gespeichert.
 *
 * Passwort einrichten: Der erste Aufruf eines Admins, solange _data/admin.hash fehlt,
 * legt den Hash aus seinem X-Admin-Key an (mind. 12 Zeichen). Zurücksetzen: Datei löschen.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ai.php';

cors();
$uid = requireUser();

/** Admin-Liste per Firestore-REST mit dem Token des Aufrufers (Rules: lesbar für Angemeldete), 5 min gecacht. */
function adminUids(): array
{
    $test = getenv('LERNWELT_TEST_ADMINS');
    if (PHP_SAPI === 'cli-server' && is_string($test) && $test !== '') {
        return explode(',', $test);
    }
    $datei = DATA_DIR . '/admins.json';
    $cache = readJson($datei, []);
    if (($cache['until'] ?? 0) > time()) {
        return $cache['uids'] ?? [];
    }
    $r = httpRequest('GET', 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT . '/databases/(default)/documents/config/admins',
        ['Authorization: Bearer ' . bearerToken()], null, 8);
    if ($r['status'] === 404) {
        $uids = [];
    } elseif ($r['status'] === 200) {
        $d = json_decode($r['body'], true);
        $uids = array_values(array_filter(array_map(fn ($v) => $v['stringValue'] ?? null, $d['fields']['uids']['arrayValue']['values'] ?? [])));
    } else {
        fail('Admin-Prüfung nicht möglich.', 503);
    }
    writeJson($datei, ['until' => time() + 300, 'uids' => $uids]);
    return $uids;
}

if (!in_array($uid, adminUids(), true)) {
    fail('Kein Admin.', 403);
}
$pw = (string) ($_SERVER['HTTP_X_ADMIN_KEY'] ?? '');
$hashDatei = DATA_DIR . '/admin.hash';
if (!is_file($hashDatei)) {
    if (strlen($pw) < 12) {
        fail('Admin-Passwort fehlt (mind. 12 Zeichen).', 403);
    }
    ensureDir(DATA_DIR);
    file_put_contents($hashDatei, password_hash($pw, PASSWORD_DEFAULT), LOCK_EX);
    @chmod($hashDatei, 0600);
} elseif ($pw === '' || !password_verify($pw, trim((string) file_get_contents($hashDatei)))) {
    usleep(random_int(300_000, 600_000));
    fail('Admin-Passwort falsch.', 403);
}

$b = jsonBody(9_000_000);
$bilder = is_array($b['images'] ?? null) ? array_values($b['images']) : [];
if ($bilder === [] || count($bilder) > 4) {
    fail('1 bis 4 Bilder.');
}
$teile = [];
foreach ($bilder as $roh) {
    [$daten, $mime] = pruefeBild($roh);
    $teile[] = bildTeil($daten, $mime);
}
$grade = grade($b['grade'] ?? 0);
$fach = isset($b['subject']) ? subject($b['subject']) : null;
$notiz = clean($b['note'] ?? '', 500);

budgetBuchen($uid, 'ingest');

$anweisung = <<<TEXT
    Du analysierst Fotos aus einem Schulbuch oder Arbeitsblatt der Grundschule (Klasse {$grade}).
    Erkenne Thema und Lerninhalte für eine Lern-App. Antworte auf Deutsch.
    - 'title': kurzer Kapitel-/Thementitel.
    - 'subject': "mathe" oder "deutsch".
    - 'skills': passende Skill-IDs im Format "<fach>.<thema>" in Kleinbuchstaben mit Unterstrichen,
      z. B. mathe.zehneruebergang_add, mathe.einmaleins_7, deutsch.doppelkonsonant, deutsch.silben.
    - 'words': Lern-/Übungswörter von der Seite (max. 40), korrekt geschrieben.
    - 'examples': bis zu 10 Beispielaufgaben als Klartext, wie sie auf der Seite stehen.
    - 'uncertain': alles, was du nicht sicher zuordnen kannst (kurz), damit der Admin es prüft.
    Nimm NUR, was tatsächlich auf den Bildern steht – nichts erfinden oder ergänzen. Ist kein Schulbuch
    oder Arbeitsblatt zu sehen, lass 'skills', 'words' und 'examples' leer und schreibe das in 'uncertain'.
    TEXT;
if ($fach !== null) {
    $teile[] = textTeil("Fach laut Admin: $fach");
}
if ($notiz !== '') {
    $teile[] = textTeil("Hinweis des Admins: $notiz");
}
$teile[] = textTeil('Werte die Seite(n) aus.');

$liste = ['type' => 'array', 'items' => ['type' => 'string']];
$roh = aiJson($teile, [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string'],
        'subject' => ['type' => 'string', 'enum' => ['mathe', 'deutsch']],
        'skills' => $liste, 'words' => $liste, 'examples' => $liste, 'uncertain' => $liste,
    ],
    'required' => ['title', 'subject', 'skills', 'words', 'examples', 'uncertain'],
    'additionalProperties' => false,
], $anweisung, KETTE_BILD, 0.2);

$strings = fn (string $feld, int $n, int $len) => array_values(array_unique(array_filter(array_map(
    fn ($s) => clean($s, $len), array_slice(is_array($roh[$feld] ?? null) ? $roh[$feld] : [], 0, $n)))));

$skills = [];
$unsicher = $strings('uncertain', 20, 200);
foreach ($strings('skills', 20, 64) as $s) {
    if (isSkillId($s)) {
        $skills[] = $s;
    } else {
        $unsicher[] = "Skill-ID ungültig: $s";
    }
}

send([
    'title' => clean($roh['title'] ?? '', 120),
    'subject' => $fach ?? (in_array($roh['subject'] ?? '', ['mathe', 'deutsch'], true) ? $roh['subject'] : 'deutsch'),
    'skills' => $skills,
    'words' => $strings('words', 40, 40),
    'examples' => $strings('examples', 10, 200),
    'uncertain' => $unsicher,
]);
