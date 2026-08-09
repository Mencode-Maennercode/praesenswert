<?php
/**
 * Das Profil aus dem Onboarding - und alles, was daraus folgt.
 *
 *   speichern   Geschlecht, Größe, Gewicht, Alter, Aktivität, Ziel, Tempo
 *   prefs       Einstellungen (Tagesbeginn, Sichtbarkeit, Erinnerungen)
 *
 * Aus dem Profil werden Grundumsatz, Gesamtumsatz, Tagesziel und Eiweißziel
 * berechnet. Diese Zahlen sind der Bezugspunkt für praktisch alles andere:
 * die Tagesbilanz, die Coach-Hinweise, den Wochenplan und die prozentuale
 * Anzeige bei den Freunden.
 *
 * Gerechnet wird ausschließlich hier. Die Sofortanzeige im Onboarding
 * benutzt dieselbe Formel in lib/energy.ts, aber nur als Vorschau - was
 * gespeichert wird, entscheidet der Server.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);
[$uid, $user] = requireUser($body);

match (clean($body['action'] ?? '', 20)) {
    'speichern' => speichern($uid, $user, $body),
    'prefs' => prefs($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

function speichern(string $uid, array $user, array $body): never
{
    rateLimit('profile', 30, 600, $uid);

    $p = is_array($body['profile'] ?? null) ? $body['profile'] : [];

    $sex = (string) ($p['sex'] ?? '');
    if (!in_array($sex, ['m', 'w', 'd'], true)) {
        fail('Bitte Geschlecht wählen.', 400, 'sex');
    }

    $stufe = (string) ($p['activity'] ?? '');
    if (!in_array($stufe, ['sitzend', 'leicht', 'maessig', 'hoch', 'sehr_hoch'], true)) {
        fail('Bitte Aktivität wählen.', 400, 'activity');
    }

    $ziel = (string) ($p['goal'] ?? 'halten');
    if (!in_array($ziel, ['abnehmen', 'halten', 'zunehmen'], true)) {
        $ziel = 'halten';
    }

    /*
     * Die Spannen sind grosszügig, aber nicht offen - und sie werden
     * abgelehnt, nicht geklemmt. 17 statt 170 cm ist ein Tippfehler, und
     * daraus stillschweigend 120 cm zu machen, hiesse dem Nutzer ein
     * falsches Tagesziel unterzuschieben, ohne dass er es merkt.
     */
    $cm = requireFloat($p['heightCm'] ?? null, 120, 230, 'Größe prüfen.', 'groesse');
    $kg = requireFloat($p['weightKg'] ?? null, 35, 300, 'Gewicht prüfen.', 'gewicht');
    $jahr = requireIntRange(
        $p['birthYear'] ?? null,
        (int) date('Y') - 100,
        (int) date('Y') - 14,
        'Alter prüfen.',
        'alter',
    );

    // Tempo nur bei abnehmen/zunehmen sinnvoll. Obergrenze 0,75 kg/Woche -
    // darüber verliert man vor allem Muskelmasse.
    $tempo = $ziel === 'halten' ? 0.0 : asFloat($p['paceKgWeek'] ?? null, 0.25, 0.75, 0.5);

    $profil = [
        'sex' => $sex,
        'heightCm' => round($cm, 1),
        'weightKg' => round($kg, 1),
        'birthYear' => $jahr,
        'activity' => $stufe,
        'goal' => $ziel,
        'paceKgWeek' => $tempo,
        'targetKg' => asFloat($p['targetKg'] ?? null, 35, 300, 0) ?: null,
    ];

    $user['profile'] = $profil;
    $user['derived'] = deriveEnergy($profil);
    if (!isset($user['onboardedAt'])) {
        $user['onboardedAt'] = date('c');
    }
    saveUser($uid, $user);

    /*
     * Das Startgewicht ist der erste Punkt der Gewichtskurve. Ohne diesen
     * Eintrag stünde die Kurve bis zum ersten Freitag leer - und der neue
     * Nutzer sähe ein leeres Diagramm, obwohl er sein Gewicht gerade
     * eingegeben hat.
     */
    setzeStartgewicht($uid, (float) $profil['weightKg']);

    send(withFreshToken([
        'ok' => true,
        'profile' => $profil,
        'derived' => $user['derived'],
    ], $uid, $user, $body));
}

function setzeStartgewicht(string $uid, float $kg): void
{
    withLock('weight-' . $uid, static function () use ($uid, $kg): void {
        $datei = WEIGHT_DIR . '/' . $uid . '.json';
        $punkte = readJson($datei, ['points' => []]);
        $liste = is_array($punkte['points'] ?? null) ? $punkte['points'] : [];

        $heute = date('Y-m-d');
        foreach ($liste as $i => $punkt) {
            if (($punkt['d'] ?? '') === $heute) {
                $liste[$i] = ['d' => $heute, 'kg' => $kg, 'src' => 'profil'];
                writeJson($datei, ['points' => $liste]);
                return;
            }
        }

        $liste[] = ['d' => $heute, 'kg' => $kg, 'src' => 'profil'];
        writeJson($datei, ['points' => $liste]);
    });
}

function prefs(string $uid, array $user, array $body): never
{
    rateLimit('prefs', 40, 600, $uid);

    $ein = is_array($body['prefs'] ?? null) ? $body['prefs'] : [];
    $alt = is_array($user['prefs'] ?? null) ? $user['prefs'] : [];

    $sicht = (string) ($ein['feedVisibility'] ?? $alt['feedVisibility'] ?? 'prozent');
    if (!in_array($sicht, ['prozent', 'teilnahme', 'aus'], true)) {
        $sicht = 'prozent';
    }

    $user['prefs'] = [
        // Wann der Tag umschlägt. 4 Uhr ist der Standard: Was um 1 Uhr
        // gegessen wird, gehört gefühlt zum Vortag.
        'dayStartHour' => asInt($ein['dayStartHour'] ?? $alt['dayStartHour'] ?? 4, 0, 8, 4),
        'reminders' => (bool) ($ein['reminders'] ?? $alt['reminders'] ?? true),
        'reminderHours' => normalisiereStunden(
            $ein['reminderHours'] ?? $alt['reminderHours'] ?? [9, 13, 19],
        ),
        'weighDay' => asInt($ein['weighDay'] ?? $alt['weighDay'] ?? 5, 1, 7, 5),
        'feedVisibility' => $sicht,
    ];
    saveUser($uid, $user);

    send(withFreshToken(['ok' => true, 'prefs' => $user['prefs']], $uid, $user, $body));
}

/** Genau drei Erinnerungszeiten, sortiert, ohne Dubletten. */
function normalisiereStunden(mixed $roh): array
{
    if (!is_array($roh)) {
        return [9, 13, 19];
    }
    $stunden = [];
    foreach ($roh as $h) {
        if (is_numeric($h)) {
            $stunden[] = max(5, min(23, (int) $h));
        }
    }
    $stunden = array_values(array_unique($stunden));
    sort($stunden);
    return count($stunden) === 3 ? $stunden : [9, 13, 19];
}
