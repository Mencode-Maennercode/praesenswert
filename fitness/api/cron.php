<?php
/**
 * Der Wecker. Wird von aussen aufgerufen, idealerweise alle 15 Minuten.
 *
 *   https://praesenzwert.de/fitness/api/cron.php?key=<cron.key>
 *
 * Aufgaben:
 *   - Erinnerungen verschicken (dreimal täglich, freitags Gewicht)
 *   - alte Sitzungen und Rate-Limit-Reste wegräumen
 *   - täglich eine gepackte Sicherung anlegen
 *
 * Streng idempotent: Jeder Nutzer merkt sich, welche Erinnerung er zuletzt
 * bekommen hat. Läuft der Wecker doppelt, passiert nichts; fällt er
 * zweimal aus, wird die Erinnerung beim nächsten Lauf nachgeholt, solange
 * das Fenster noch offen ist. Ein Wecker, der bei Doppellauf doppelt
 * benachrichtigt, wird abgeschaltet - und dann kommt gar nichts mehr.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';
require __DIR__ . '/_push.php';

// Kein cors(), kein requireMethod: Das hier ruft kein Browser auf.
header('Content-Type: text/plain; charset=utf-8');

/* ------------------------------------------------------------ Zugangswort */

$datei = DATA_DIR . '/cron.key';
if (!is_file($datei)) {
    // Beim ersten Aufruf erzeugen und anzeigen. Danach nie wieder -
    // ab dann ist der Aufruf ohne das Wort wertlos.
    ensureDirs();
    $neu = bin2hex(random_bytes(16));
    file_put_contents($datei, $neu, LOCK_EX);
    @chmod($datei, 0600);
    echo "Cron-Schluessel angelegt. Diese Adresse im netcup-Panel eintragen:\n\n";
    echo "https://praesenzwert.de/fitness/api/cron.php?key={$neu}\n\n";
    echo "Wird nur dieses eine Mal angezeigt.\n";
    exit;
}

$erwartet = trim((string) file_get_contents($datei));
$gegeben = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';

if ($erwartet === '' || !hash_equals($erwartet, $gegeben)) {
    http_response_code(403);
    echo "nein\n";
    exit;
}

/* ---------------------------------------------------------------- Ablauf */

$jetzt = time();
$bericht = [];

$index = userIndex();
$gesendet = 0;
$geprueft = 0;

foreach ($index as $uid) {
    if (!is_string($uid)) {
        continue;
    }
    $user = loadUser($uid);
    if ($user === null || !is_array($user['push'] ?? null) || $user['push'] === []) {
        continue;
    }
    $geprueft++;

    $prefs = is_array($user['prefs'] ?? null) ? $user['prefs'] : [];
    if (($prefs['reminders'] ?? true) !== true) {
        continue;
    }

    $fenster = faelligesFenster($prefs, $jetzt);
    if ($fenster === null) {
        continue;
    }

    // Schon geschickt? Dann nichts tun. Das ist die ganze Idempotenz.
    $zuletzt = (string) ($user['pushLast'] ?? '');
    if ($zuletzt === $fenster) {
        continue;
    }

    [$erfolge, $user] = pushAnNutzer($user, $fenster);
    $user['pushLast'] = $fenster;
    saveUser($uid, $user);
    $gesendet += $erfolge;
}

$bericht[] = "Nutzer mit Abo: {$geprueft}, Nachrichten: {$gesendet}";

/* ------------------------------------------------------------- Aufraeumen */

$weg = 0;
foreach (glob(AI_DIR . '/*.json') ?: [] as $f) {
    if (filemtime($f) < $jetzt - 900) {
        @unlink($f);
        $weg++;
    }
}
foreach (glob(RATE_DIR . '/*.json') ?: [] as $f) {
    if (filemtime($f) < $jetzt - 86400) {
        @unlink($f);
        $weg++;
    }
}
foreach (glob(LISTS_DIR . '/*.json') ?: [] as $f) {
    if (filemtime($f) < $jetzt - 14 * 86400) {
        @unlink($f);
        $weg++;
    }
}
$bericht[] = "Aufgeraeumt: {$weg} Dateien";

/* -------------------------------------------------------------- Sicherung */

$bericht[] = sicherung($jetzt);

echo implode("\n", $bericht) . "\n";

/* ------------------------------------------------------------- Funktionen */

/**
 * Welches Erinnerungsfenster ist gerade offen?
 *
 * Ein Fenster ist eine Stunde lang offen. Der Wecker läuft alle 15
 * Minuten - so wird eine Erinnerung auch dann noch zugestellt, wenn der
 * Aufruf zur vollen Stunde einmal ausfällt.
 *
 * @return string|null Kennung wie "2026-08-09-13" oder "2026-08-09-wiegen"
 */
function faelligesFenster(array $prefs, int $jetzt): ?string
{
    $stunde = (int) date('G', $jetzt);
    $tag = date('Y-m-d', $jetzt);
    $wochentag = (int) date('N', $jetzt); // 1 = Montag

    // Der Wiegetag hat Vorrang - er kommt nur einmal die Woche.
    $wiegeTag = (int) ($prefs['weighDay'] ?? 5);
    if ($wochentag === $wiegeTag && $stunde >= 17 && $stunde < 18) {
        return $tag . '-wiegen';
    }

    $stunden = is_array($prefs['reminderHours'] ?? null) ? $prefs['reminderHours'] : [9, 13, 19];
    foreach ($stunden as $h) {
        if ((int) $h === $stunde) {
            return $tag . '-' . $stunde;
        }
    }
    return null;
}

/**
 * Eine gepackte Sicherung pro Tag, vierzehn Tage lang.
 *
 * Es gibt keine Datenbank mit eingebautem Backup. Ein unachtsamer Deploy
 * oder ein kaputtes Schreiben wäre sonst endgültig - und niemand merkt
 * es, bis er in den Verlauf schaut.
 */
function sicherung(int $jetzt): string
{
    $dir = DATA_DIR . '/_backup';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    $ziel = $dir . '/' . date('Y-m-d', $jetzt) . '.json.gz';
    if (is_file($ziel)) {
        return 'Sicherung: heute schon vorhanden';
    }

    $alles = ['erzeugt' => date('c'), 'users' => [], 'days' => [], 'weight' => [], 'social' => readJson(DATA_DIR . '/social.json', [])];

    foreach (userIndex() as $uid) {
        if (!is_string($uid)) {
            continue;
        }
        $u = loadUser($uid);
        if ($u === null) {
            continue;
        }
        // Ohne Zugangsdaten - eine Sicherung ist kein Passwortspeicher.
        unset($u['hash'], $u['recoveryHash'], $u['push']);
        $alles['users'][$uid] = $u;
        $alles['weight'][$uid] = readJson(WEIGHT_DIR . '/' . $uid . '.json', []);

        foreach (glob(DAYS_DIR . '/' . $uid . '/*.json') ?: [] as $f) {
            $alles['days'][$uid][basename($f, '.json')] = readJson($f, []);
        }
    }

    $roh = json_encode($alles, JSON_UNESCAPED_UNICODE);
    if ($roh === false) {
        return 'Sicherung: fehlgeschlagen';
    }
    @file_put_contents($ziel, gzencode($roh, 6));
    @chmod($ziel, 0600);

    foreach (glob($dir . '/*.json.gz') ?: [] as $f) {
        if (filemtime($f) < $jetzt - 14 * 86400) {
            @unlink($f);
        }
    }

    return 'Sicherung: ' . round(strlen($roh) / 1024) . ' KB gepackt';
}
