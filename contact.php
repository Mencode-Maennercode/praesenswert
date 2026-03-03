<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.praesenzwert.de');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Anfrage.']);
    exit;
}

$name    = isset($data['name'])    ? trim(strip_tags($data['name']))    : '';
$email   = isset($data['email'])   ? trim(strip_tags($data['email']))   : '';
$company = isset($data['company']) ? trim(strip_tags($data['company'])) : '';
$phone   = isset($data['phone'])   ? trim(strip_tags($data['phone']))   : '';
$message = isset($data['message']) ? trim(strip_tags($data['message'])) : '';

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, E-Mail und Nachricht sind Pflichtfelder.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige E-Mail-Adresse.']);
    exit;
}

$to      = 'info@praesenzwert.de';
$subject = '=?UTF-8?B?' . base64_encode('Neue Anfrage von ' . $name) . '?=';

$body  = "Neue Kontaktanfrage von der PräsenzWert Website\n";
$body .= str_repeat('-', 50) . "\n\n";
$body .= "Name:      $name\n";
$body .= "E-Mail:    $email\n";
if (!empty($company)) $body .= "Unternehmen: $company\n";
if (!empty($phone))   $body .= "Telefon:   $phone\n";
$body .= "\nNachricht:\n$message\n\n";
$body .= str_repeat('-', 50) . "\n";
$body .= "Gesendet am: " . date('d.m.Y H:i') . " Uhr\n";

$headers  = "From: noreply@praesenzwert.de\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Anfrage erfolgreich gesendet.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Senden. Bitte versuchen Sie es später erneut.']);
}
