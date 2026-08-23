<?php
/**
 * Öğrenci oturum durumu — site header'ı (assets/js/main.js) bu ucu kullanır.
 *
 * GET  ?action=me      -> { ok, loggedIn, student?, counts? }
 * POST ?action=logout  -> { ok }
 *
 * Kayıt / giriş / şifre sıfırlama akışları ogrenci/*.php sayfalarında
 * sunucu tarafında işlenir (JS olmadan da çalışır).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/student_account.php';

header('Cache-Control: no-store');

$action = $_GET['action'] ?? $_POST['action'] ?? 'me';

try {
    if ($action === 'logout') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_out(['ok' => false, 'error' => 'POST gerekli'], 405);
        }
        student_logout();
        json_out(['ok' => true]);
    }

    if ($action !== 'me') {
        json_out(['ok' => false, 'error' => 'Bilinmeyen işlem'], 400);
    }

    $student = current_student();
    if (!$student) {
        json_out(['ok' => true, 'loggedIn' => false]);
    }

    $pdo = db();
    students_ensure_schema($pdo);

    // Oturum açık ama hesap silinmiş/askıya alınmışsa oturumu düşür
    $row = student_find_by_id($pdo, $student['id']);
    if (!$row || ($row['status'] ?? 'active') !== 'active') {
        student_logout();
        json_out(['ok' => true, 'loggedIn' => false]);
    }

    $st = $pdo->prepare(
        "SELECT
            SUM(payment_status = 'paid') AS paid_count,
            SUM(payment_status <> 'paid') AS pending_count
         FROM course_enrollments WHERE student_id = ?"
    );
    $st->execute([(int)$row['id']]);
    $counts = $st->fetch() ?: [];

    json_out([
        'ok' => true,
        'loggedIn' => true,
        'student' => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
        ],
        'counts' => [
            'paid' => (int)($counts['paid_count'] ?? 0),
            'pending' => (int)($counts['pending_count'] ?? 0),
        ],
        'ownedCourseIds' => student_paid_course_ids($pdo, (int)$row['id']),
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Sunucu hatası'], 500);
}
