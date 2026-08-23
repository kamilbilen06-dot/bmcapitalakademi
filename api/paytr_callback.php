<?php
/**
 * PayTR Bildirim URL — 2. ADIM (dev.paytr.com/iframe-api)
 * Mağaza Paneli > Destek & Kurulum > Ayarlar > Bildirim URL:
 *   https://www.bmcapitalakademi.com/api/paytr_callback.php
 *
 * Yanıt: yalnızca düz metin OK (başka çıktı olmamalı).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/paytr_config.php';
require_once __DIR__ . '/paytr_schema.php';

header('Content-Type: text/plain; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'METHOD';
    exit;
}

try {
    if (!paytr_credentials_ready()) {
        http_response_code(500);
        echo 'CONFIG';
        exit;
    }

    $post = $_POST;
    $merchantOid = (string)($post['merchant_oid'] ?? '');
    $status = (string)($post['status'] ?? '');
    $totalAmount = (string)($post['total_amount'] ?? '');
    $hash = (string)($post['hash'] ?? '');

    if ($merchantOid === '' || $status === '' || $totalAmount === '' || $hash === '') {
        echo 'OK';
        exit;
    }

    $expected = base64_encode(hash_hmac(
        'sha256',
        $merchantOid . PAYTR_MERCHANT_SALT . $status . $totalAmount,
        PAYTR_MERCHANT_KEY,
        true
    ));

    if (!hash_equals($expected, $hash)) {
        http_response_code(400);
        echo 'BAD HASH';
        exit;
    }

    $pdo = db();
    paytr_ensure_schema($pdo);

    $st = $pdo->prepare("SELECT * FROM payment_orders WHERE merchant_oid = ? LIMIT 1");
    $st->execute([$merchantOid]);
    $order = $st->fetch();
    if (!$order) {
        echo 'OK';
        exit;
    }

    // Idempotent: zaten işlendiyse tekrar etme
    if (in_array($order['status'], ['paid', 'failed'], true)) {
        echo 'OK';
        exit;
    }

    if ($status === 'success') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE payment_orders SET status='paid', paytr_total_amount=?, paid_at=NOW() WHERE id=?"
            )->execute([$totalAmount, $order['id']]);

            $dup = $pdo->prepare(
                "SELECT id FROM course_enrollments WHERE course_id=? AND student_email=? LIMIT 1"
            );
            $dup->execute([(int)$order['course_id'], $order['student_email']]);
            $existing = $dup->fetch();

            if ($existing) {
                $enrollId = (int)$existing['id'];
                $pdo->prepare(
                    "UPDATE course_enrollments
                     SET payment_status='paid', merchant_oid=?, student_name=?, student_phone=?, source='paytr'
                     WHERE id=?"
                )->execute([
                    $merchantOid,
                    $order['student_name'],
                    $order['student_phone'],
                    $enrollId,
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO course_enrollments
                     (course_id, student_name, student_email, student_phone, progress_pct, source, payment_status, merchant_oid, enrolled_at)
                     VALUES (?,?,?,?,0,'paytr','paid',?,NOW())"
                )->execute([
                    (int)$order['course_id'],
                    $order['student_name'],
                    $order['student_email'],
                    $order['student_phone'],
                    $merchantOid,
                ]);
                $enrollId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE payment_orders SET enrollment_id=? WHERE id=?")
                ->execute([$enrollId, $order['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo 'ERR';
            exit;
        }
    } else {
        $pdo->prepare(
            "UPDATE payment_orders SET status='failed', paytr_total_amount=? WHERE id=?"
        )->execute([$totalAmount, $order['id']]);
    }

    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERR';
}
