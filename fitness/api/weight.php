<?php
/**
 * Gewicht: eintragen und abrufen.
 *
 *   add    Gewicht für einen Tag setzen (überschreibt den Tag)
 *   list   alle Punkte
 *
 * Ein Punkt pro Tag, nicht mehr. Wer sich dreimal wiegt, will nicht drei
 * Werte im Diagramm - er will den von heute.
 *
 * Die Glättung passiert bewusst NICHT hier: Der Server speichert, was
 * auf der Waage stand. Der gleitende Schnitt ist Darstellung, und
 * Darstellung gehört nicht in den Speicher - sonst kann man sie nie
 * wieder ändern.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);
[$uid, $user] = requireUser($body);

match (clean($body['action'] ?? '', 20)) {
    'add' => hinzufuegen($uid, $user, $body),
    'list' => auflisten($uid, $user, $body),
    default => fail('Unbekannte Aktion.'),
};

function pfad(string $uid): string
{
    return WEIGHT_DIR . '/' . $uid . '.json';
}

function punkte(string $uid): array
{
    $d = readJson(pfad($uid), ['points' => []]);
    $liste = is_array($d['points'] ?? null) ? $d['points'] : [];
    usort($liste, static fn($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
    return $liste;
}

function hinzufuegen(string $uid, array $user, array $body): never
{
    rateLimit('weight', 40, 600, $uid);

    $kg = requireFloat($body['kg'] ?? null, 30, 350, 'Gewicht prüfen.', 'kg');
    $startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
    $datum = validDate($body['datum'] ?? null) ?: dayKey($startStunde);

    $liste = withLock('weight-' . $uid, static function () use ($uid, $kg, $datum): array {
        $liste = punkte($uid);
        $gefunden = false;

        foreach ($liste as $i => $p) {
            if (($p['d'] ?? '') === $datum) {
                $liste[$i] = ['d' => $datum, 'kg' => $kg, 'src' => 'manuell'];
                $gefunden = true;
                break;
            }
        }
        if (!$gefunden) {
            $liste[] = ['d' => $datum, 'kg' => $kg, 'src' => 'manuell'];
        }

        usort($liste, static fn($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
        writeJson(pfad($uid), ['points' => $liste]);
        return $liste;
    });

    /*
     * Das Profilgewicht mitziehen - aber nur beim heutigen Eintrag.
     *
     * Daran hängt der Grundumsatz und damit das Tagesziel. Wer ein altes
     * Gewicht nachträgt, soll nicht sein aktuelles Ziel verstellen.
     */
    if ($datum === dayKey($startStunde) && is_array($user['profile'] ?? null)) {
        $user['profile']['weightKg'] = round($kg, 1);
        $user['derived'] = deriveEnergy($user['profile']);
        saveUser($uid, $user);
    }

    send(withFreshToken([
        'ok' => true,
        'punkte' => $liste,
        'derived' => $user['derived'] ?? null,
    ], $uid, $user, $body));
}

function auflisten(string $uid, array $user, array $body): never
{
    send(withFreshToken([
        'ok' => true,
        'punkte' => punkte($uid),
        'zielKg' => $user['profile']['targetKg'] ?? null,
    ], $uid, $user, $body));
}
