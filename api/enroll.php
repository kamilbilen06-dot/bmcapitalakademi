<?php
/**
 * Kurs kayıt formu — siteden gelen başvurular eğitmen panelinde Öğrenciler listesine düşer.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/egitmen_schema.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST gerekli'], 405);
}

try {
    $pdo = db();
    egitmen_ensure_schema($pdo);
    $in = body_json();
    if (!$in) {
        $in = $_POST;
    }

    $name = clean($in['name'] ?? $in['student_name'] ?? '');
    $email = clean($in['email'] ?? $in['student_email'] ?? '');
    $phone = clean($in['phone'] ?? $in['student_phone'] ?? '');
    $rawCourse = $in['course_id'] ?? $in['course'] ?? '';

    if ($name === '') json_out(['ok' => false, 'error' => 'Ad soyad gerekli'], 422);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'error' => 'Geçerli e-posta gerekli'], 422);
    }

    $courseId = 0;
    if (is_numeric($rawCourse)) {
        $courseId = (int)$rawCourse;
    } elseif (preg_match('/^course-(\d+)$/', (string)$rawCourse, $m)) {
        $courseId = (int)$m[1];
    }
    if ($courseId <= 0) json_out(['ok' => false, 'error' => 'Kurs seçimi gerekli'], 422);

    $st = $pdo->prepare("SELECT id, title, status FROM courses WHERE id = ?");
    $st->execute([$courseId]);
    $course = $st->fetch();
    if (!$course || $course['status'] !== 'published') {
        json_out(['ok' => false, 'error' => 'Kurs bulunamadı veya yayında değil'], 404);
    }

    // Aynı e-posta + kurs tekrarını engelle
    $dup = $pdo->prepare(
        "SELECT id FROM course_enrollments WHERE course_id = ? AND student_email = ? LIMIT 1"
    );
    $dup->execute([$courseId, $email]);
    if ($dup->fetch()) {
        json_out(['ok' => false, 'error' => 'Bu e-posta ile bu kursa zaten kayıt yapılmış'], 422);
    }

    $pdo->prepare(
        "INSERT INTO course_enrollments
         (course_id, student_name, student_email, student_phone, progress_pct, source, enrolled_at)
         VALUES (?,?,?,?,0,'site',NOW())"
    )->execute([$courseId, $name, $email, $phone]);

    $eid = (int)$pdo->lastInsertId();
    try {
        $pdo->prepare("UPDATE course_enrollments SET payment_status='pending' WHERE id=?")->execute([$eid]);
    } catch (Throwable $ignore) {
    }

    json_out([
        'ok' => true,
        'id' => $eid,
        'course_title' => $course['title'],
        'message' => 'Kaydınız alındı. Ödeme dekontunuzu WhatsApp ile iletin.',
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Kayıt alınamadı'], 500);
}
