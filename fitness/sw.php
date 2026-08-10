<?php
/**
 * Liefert den Service Worker aus - mit Headern, die auch wirklich gelten.
 *
 * Warum nicht direkt sw.js? Gemessen: Cloudflare steht vor dieser Domain
 * und behandelt .js als statisches Gut. Es cacht die Datei am Rand und
 * ersetzt dabei das vom Server gesendete "no-cache" durch seine eigene
 * Browser-Cache-Zeit von vier Stunden (cf-cache-status: REVALIDATED).
 *
 * Beim Service Worker ist das genau die falsche Datei zum Einfrieren: Er
 * entscheidet, welche App-Fassung die Nutzer sehen. Eine Korrektur käme
 * bis zu vier Stunden lang bei niemandem an - und da der Worker selbst
 * die Auslieferung steuert, kann man sich auch nicht dagegen behelfen.
 *
 * .php-Adressen behandelt Cloudflare dagegen als dynamisch und reicht
 * sie unangetastet durch (cf-cache-status: DYNAMIC). Derselbe Inhalt,
 * nur unter einer Adresse, an der die Header überleben.
 */

declare(strict_types=1);

$datei = __DIR__ . '/sw.js';
if (!is_file($datei)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');
// Erlaubt dem Worker, den ganzen Unterordner zu steuern - unabhängig
// davon, wo das Skript selbst liegt.
header('Service-Worker-Allowed: /fitness/');

readfile($datei);
