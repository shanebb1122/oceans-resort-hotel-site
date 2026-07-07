<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$firstName = trim($data['firstName'] ?? '');
$lastName  = trim($data['lastName']  ?? '');
$email     = trim($data['email']     ?? '');
$phone     = trim($data['phone']     ?? '');
$message   = trim($data['message']   ?? '');

// Honeypot — bots fill this in, humans leave it blank
if (!empty($data['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid email address and message.']);
    exit;
}

$name = htmlspecialchars($firstName . ' ' . $lastName);
$safeEmail   = htmlspecialchars($email);
$safePhone   = htmlspecialchars($phone);
$safeMessage = htmlspecialchars($message);

$to      = 'stay@oceanshotel.co.nz';
$subject = 'Website Enquiry from ' . $name;
$body    = implode("\n", [
    "Name:    $name",
    "Email:   $safeEmail",
    "Phone:   $safePhone",
    "",
    "Message:",
    $safeMessage,
]);

$headers = implode("\r\n", [
    'From: website@oceansresorthotel.co.nz',
    'Reply-To: ' . $safeEmail,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
]);

if (mail($to, $subject, $body, $headers)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Sorry, the message could not be sent. Please email us directly at stay@oceanshotel.co.nz']);
}
