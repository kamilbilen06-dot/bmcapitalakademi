<?php
/**
 * Sipariş durumu (başarı/başarısız sayfası için)
 * GET ?oid=BM...
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/paytr_schema.php';

header('Access-Control-Allow-Origin: *');

$oid = clean($_GET['oid'] ?? '');
if ($oid === '') {
    json_out(['ok' => false, 'error' => 'oid gerekli'], 422);
}

try {
    $pdo = db();
    paytr_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT o.merchant_oid, o.status, o.amount_kurus, o.student_name, o.student_email, o.paid_at,
                o.course_id, c.title AS course_title
         FROM payment_orders o
         LEFT JOIN courses c ON c.id = o.course_id
         WHERE o.merchant_oid = ? LIMIT 1"
    );
    $st->execute([$oid]);
    $row = $st->fetch();
    if (!$row) {
        json_out(['ok' => false, 'error' => 'Sipariş bulunamadı'], 404);
    }
    json_out([
        'ok' => true,
        'item' => [
            'merchant_oid' => $row['merchant_oid'],
            'status' => $row['status'],
            'amount_label' => number_format(((int)$row['amount_kurus']) / 100, 2, ',', '.') . ' TL',
            'student_name' => $row['student_name'],
            'student_email' => $row['student_email'],
            'course_title' => $row['course_title'],
            'course_id' => (int)($row['course_id'] ?? 0),
            'paid_at' => $row['paid_at'],
        ],
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Sorgulanamadı'], 500);
}
