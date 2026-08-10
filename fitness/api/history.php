<?php
/**
 * Verlauf: Woche, Monat, Jahr.
 *
 *   zeitraum   Tageswerte oder Wochenbuckets samt Zusammenfassung
 *
 * Gelesen wird direkt aus den Tagesdateien. Bei sieben oder dreissig
 * Dateien ist das schneller als jede Zwischenrechnung, und es kann nie
 * veralten - anders als ein Zwischenstand, den man pflegen müsste.
 *
 * Beim Jahr wird zu Wochen zusammengefasst: 365 Balken auf einem
 * Handybildschirm sind kein Diagramm, sondern eine Textur.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_day.php';

cors();
requireMethod('POST');

$body = jsonBody(4 * 1024);
[$uid, $user] = requireUser($body);
rateLimit('history', 60, 600, $uid);

$derived = is_array($user['derived'] ?? null) ? $user['derived'] : [];
if ($derived === []) {
    fail('Erst das Onboarding abschließen.', 409, 'kein-profil');
}

$startStunde = (int) ($user['prefs']['dayStartHour'] ?? 4);
$heute = dayKey($startStunde);
$bereich = clean($body['bereich'] ?? 'woche', 10);

$punkte = match ($bereich) {
    'monat' => taeglich($uid, $heute, 30, $derived),
    'jahr' => woechentlich($uid, $heute, 52, $derived),
    default => taeglich($uid, $heute, 7, $derived),
};

send(withFreshToken([
    'ok' => true,
    'bereich' => in_array($bereich, ['woche', 'monat', 'jahr'], true) ? $bereich : 'woche',
    'punkte' => $punkte,
    'summe' => zusammenfassung($punkte),
], $uid, $user, $body));

/* ------------------------------------------------------------------ Lesen */

/** Ein Punkt je Tag, ältester zuerst. */
function taeglich(string $uid, string $bis, int $tage, array $derived): array
{
    $ziel = (int) ($derived['goalKcal'] ?? 0);
    $out = [];

    for ($i = $tage - 1; $i >= 0; $i--) {
        $datum = date('Y-m-d', strtotime($bis . " -{$i} day"));
        [$ein, $aus, $eintraege] = tagesWerte($uid, $datum);

        $out[] = [
            'label' => $datum,
            'kurz' => strftimeDe($datum, $tage <= 7 ? 'wochentag' : 'tag'),
            'in' => $ein,
            'out' => $aus,
            'ziel' => $ziel,
            // Null heisst "nichts eingetragen" und ist etwas anderes als
            // eine Null: Ein leerer Tag darf keinen Balken zeichnen.
            'leer' => $eintraege === 0,
        ];
    }
    return $out;
}

/** Ein Punkt je Kalenderwoche - Mittelwert der Tage MIT Eintrag. */
function woechentlich(string $uid, string $bis, int $wochen, array $derived): array
{
    $ziel = (int) ($derived['goalKcal'] ?? 0);
    $out = [];

    for ($w = $wochen - 1; $w >= 0; $w--) {
        $ende = strtotime($bis . " -" . ($w * 7) . " day");
        $summeIn = $summeAus = $mitEintrag = 0;

        for ($t = 0; $t < 7; $t++) {
            $datum = date('Y-m-d', strtotime("-{$t} day", $ende));
            [$ein, $aus, $eintraege] = tagesWerte($uid, $datum);
            if ($eintraege === 0) {
                continue;
            }
            $summeIn += $ein;
            $summeAus += $aus;
            $mitEintrag++;
        }

        $out[] = [
            'label' => date('o-\WW', $ende),
            'kurz' => 'KW ' . date('W', $ende),
            // Der Schnitt der Tage MIT Eintrag - sonst zieht jeder
            // vergessene Tag die Woche künstlich nach unten.
            'in' => $mitEintrag > 0 ? (int) round($summeIn / $mitEintrag) : 0,
            'out' => $mitEintrag > 0 ? (int) round($summeAus / $mitEintrag) : 0,
            'ziel' => $ziel,
            'leer' => $mitEintrag === 0,
            'tage' => $mitEintrag,
        ];
    }
    return $out;
}

/** @return array{0:int,1:int,2:int} [aufgenommen, verbrannt, Anzahl Einträge] */
function tagesWerte(string $uid, string $datum): array
{
    $tag = loadDay($uid, $datum);

    $ein = 0;
    foreach ($tag['meals'] as $m) {
        $ein += (int) ($m['kcal'] ?? 0);
    }
    $aus = 0;
    foreach ($tag['sport'] as $s) {
        $aus += (int) ($s['kcal'] ?? 0);
    }
    return [$ein, $aus, count($tag['meals']) + count($tag['sport'])];
}

/**
 * Zusammenfassung über die Punkte MIT Eintrag.
 *
 * Leere Tage rauszulassen ist der ganze Punkt: Wer drei Tage vergisst
 * einzutragen, hat kein Defizit von 3 × 2300 kcal aufgebaut. Genau so
 * eine Rechnung würde eine Ernährungs-App gefährlich machen.
 */
function zusammenfassung(array $punkte): array
{
    $tage = 0;
    $summeIn = $summeAus = $summeZiel = 0;

    foreach ($punkte as $p) {
        if ($p['leer']) {
            continue;
        }
        $tage++;
        $summeIn += (int) $p['in'];
        $summeAus += (int) $p['out'];
        $summeZiel += (int) $p['ziel'];
    }

    if ($tage === 0) {
        return ['tage' => 0, 'schnittIn' => 0, 'schnittAus' => 0, 'bilanz' => 0, 'kgAequivalent' => 0];
    }

    // Ein Kilogramm Körperfett entspricht rund 7000 kcal.
    $bilanz = $summeIn - $summeAus - $summeZiel;

    return [
        'tage' => $tage,
        'schnittIn' => (int) round($summeIn / $tage),
        'schnittAus' => (int) round($summeAus / $tage),
        'bilanz' => (int) $bilanz,
        'kgAequivalent' => round($bilanz / 7000, 2),
    ];
}

/** Kurzbeschriftung auf Deutsch, ohne von der Serversprache abzuhängen. */
function strftimeDe(string $datum, string $art): string
{
    $ts = strtotime($datum);
    if ($art === 'wochentag') {
        return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', $ts)];
    }
    return date('j.', $ts);
}
