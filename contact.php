<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$firstName      = trim($data['firstName']      ?? '');
$lastName       = trim($data['lastName']       ?? '');
$email          = trim($data['email']          ?? '');
$phone          = trim($data['phone']          ?? '');
$message        = trim($data['message']        ?? '');
$enquiryType    = trim($data['enquiryType']    ?? 'general');
$recaptchaToken = trim($data['recaptchaToken'] ?? '');

// Enquiry type → subject label + recipient email.
// All currently route to the same inbox; update the email values here
// when separate mailboxes are ready for each department.
$enquiryTypes = [
    'general'       => ['label' => 'General Enquiry',       'email' => 'stay@oceansresorthotel.co.nz'],
    'accommodation' => ['label' => 'Accommodation Enquiry',  'email' => 'stay@oceansresorthotel.co.nz'],
    'restaurant'    => ['label' => 'Restaurant Enquiry/Booking', 'email' => 'stay@oceansresorthotel.co.nz'],
];
$enquiry = $enquiryTypes[$enquiryType] ?? $enquiryTypes['general'];

// Honeypot — bots fill this in, humans leave it blank
if (!empty($data['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Basic validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid email address and message.']);
    exit;
}

// Verify reCAPTCHA v3 token
if (empty($recaptchaToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'Security check failed. Please refresh the page and try again.']);
    exit;
}

$secretKey = '6LePW0gtAAAAAGZQstPzvy9txXTgBzG_isTCKNLY';
$verifyResponse = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) .
    '&response=' . urlencode($recaptchaToken)
);
$verifyData = json_decode($verifyResponse, true);

if (!$verifyData['success'] || $verifyData['score'] < 0.5) {
    http_response_code(400);
    echo json_encode(['error' => 'Security check failed. Please try again.']);
    exit;
}

// Send the email
$name        = htmlspecialchars($firstName . ' ' . $lastName);
$safeEmail   = htmlspecialchars($email);
$safePhone   = htmlspecialchars($phone);
$safeMessage = htmlspecialchars($message);

$to      = $enquiry['email'];
$subject = 'Website Enquiry - ' . $enquiry['label'] . ' from ' . $name;
$body    = implode("\n", [
    "Enquiry Type: " . $enquiry['label'],
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
    // Auto-reply to the enquirer confirming receipt.
    $replySubject = 'We\'ve received your enquiry - Quality Hotel Oceans Tūtūkaka';
    $replyBody    = implode("\n", [
        "Hi $name,",
        "",
        "Thank you for contacting Quality Hotel Oceans Tūtūkaka. We've received your " . strtolower($enquiry['label']) . " and will be in touch shortly.",
        "",
        "For your reference, here's a copy of your message:",
        "",
        $safeMessage,
        "",
        "If your enquiry is urgent, please call us on 09 470 2280.",
        "",
        "Kind regards,",
        "Quality Hotel Oceans Tūtūkaka",
        "11 Marina Road, Tūtūkaka 0173, New Zealand",
    ]);
    $replyHeaders = implode("\r\n", [
        'From: ' . $enquiry['email'],
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ]);
    mail($email, $replySubject, $replyBody, $replyHeaders);

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Sorry, the message could not be sent. Please email us directly at stay@oceansresorthotel.co.nz']);
}
