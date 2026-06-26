<?php
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'contact.php') {
    http_response_code(403);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact.html');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$email || !$phone || !$message) {
    echo '<script>alert("Please fill in all required fields.");history.back();</script>';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<script>alert("Please enter a valid email address.");history.back();</script>';
    exit;
}

$to = 'contact@switdeveloper.top';
$subject = "New Contact Form Submission from $name";
$headers = "From: $email\r\nReply-To: $email\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n";

$body = "Name: $name\nEmail: $email\nPhone: $phone\nService: $service\n\nMessage:\n$message\n";

if (mail($to, $subject, $body, $headers)) {
    echo '<script>alert("Thank you! Your message has been sent. We will get back to you within 24 hours.");window.location.href="/contact.html";</script>';
} else {
    echo '<script>alert("Sorry, there was an error sending your message. Please try again later.");history.back();</script>';
}
exit;