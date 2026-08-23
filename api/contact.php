<?php
/**
 * İletişim formu kaydı - gönderimleri veritabanına yazar (panelde görüntülenir).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Yalnızca POST'], 405);
}

$in = body_json();
if (empty($in)) { $in = $_POST; }

$name = mb_substr(clean($in['name'] ?? ''), 0, 120);
$email = mb_substr(clean($in['email'] ?? ''), 0, 160);
$phone = mb_substr(clean($in['phone'] ?? ''), 0, 60);
$subject = mb_substr(clean($in['subject'] ?? ''), 0, 160);
$message = mb_substr(clean($in['message'] ?? ''), 0, 4000);

if ($name === '' && $email === '' && $phone === '') {
    json_out(['ok' => false, 'error' => 'Eksik bilgi'], 422);
}

try {
    $stmt = db()->prepare("INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $subject, $message]);
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Kaydedilemedi'], 500);
}
