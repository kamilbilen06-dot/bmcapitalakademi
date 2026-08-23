<?php
/**
 * Ödeme şeması — sağlayıcıdan bağımsız sipariş kaydı.
 *
 * payment_orders tablosu PayTR için oluşturulmuştu (paytr_schema.php). Burada
 * tabloyu iyzico ve ileride başka sağlayıcılar da kullanabilsin diye
 * genişletiyoruz. Mevcut PayTR akışı aynen çalışmaya devam eder: eklenen
 * kolonların hepsi varsayılan değerli ve eski satırlar 'paytr' sayılır.
 */
require_once __DIR__ . '/paytr_schema.php';

function payments_ensure_schema(PDO $pdo) {
    // Temel tabloyu ve enrollment ödeme kolonlarını kuran mevcut şema
    paytr_ensure_schema($pdo);

    // Hangi sağlayıcı ile ödendi (eski kayıtlar PayTR)
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'provider',
        "VARCHAR(20) NOT NULL DEFAULT 'paytr' AFTER merchant_oid"
    );

    // Ödemeyi yapan öğrenci hesabı — PayTR akışı bunu doldurmuyordu
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'student_id',
        'INT NULL AFTER course_id'
    );

    // iyzico korelasyon kimliği (conversationId)
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'conversation_id',
        "VARCHAR(64) NOT NULL DEFAULT '' AFTER provider"
    );

    // iyzico Checkout Form jetonu — sonucu sorgulamak için
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'provider_token',
        "VARCHAR(255) NOT NULL DEFAULT '' AFTER conversation_id"
    );

    // Sağlayıcıdaki ödeme kimliği (iyzico paymentId) — iade/mutabakat için
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'provider_payment_id',
        "VARCHAR(64) NOT NULL DEFAULT '' AFTER provider_token"
    );

    // Sağlayıcının tahsil ettiğini bildirdiği tutar
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'paid_price',
        "VARCHAR(32) NOT NULL DEFAULT '' AFTER amount_kurus"
    );

    // Başarısız ödemede sağlayıcının döndürdüğü hata (teşhis için)
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'error_message',
        "VARCHAR(255) NOT NULL DEFAULT '' AFTER status"
    );

    payments_add_index_if_missing($pdo, 'payment_orders', 'idx_provider', 'provider');
    payments_add_index_if_missing($pdo, 'payment_orders', 'idx_order_student', 'student_id');
    payments_add_index_if_missing($pdo, 'payment_orders', 'idx_provider_token', 'provider_token');

    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'refunded_at',
        'DATETIME NULL AFTER paid_at'
    );
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'provider_transaction_id',
        "VARCHAR(64) NOT NULL DEFAULT '' AFTER provider_payment_id"
    );
    egitmen_add_column_if_missing(
        $pdo,
        'payment_orders',
        'mail_sent_at',
        'DATETIME NULL AFTER refunded_at'
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        provider VARCHAR(20) NOT NULL DEFAULT 'iyzico',
        direction VARCHAR(16) NOT NULL DEFAULT '',
        path VARCHAR(160) NOT NULL DEFAULT '',
        payload TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pl_order (order_id),
        INDEX idx_pl_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Dizin yoksa ekle. egitmen_add_column_if_missing kolon için çalışır;
 * dizinler information_schema üzerinden kontrol edilir.
 */
function payments_add_index_if_missing(PDO $pdo, $table, $indexName, $columns) {
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
        );
        $st->execute([$table, $indexName]);
        if ((int)$st->fetch()['c'] > 0) {
            return;
        }
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)");
    } catch (Throwable $e) {
        // Dizin kritik değil; yokluğunda sorgular yine çalışır
    }
}

/**
 * Yeni sipariş referansı (merchant_oid). Yalnızca harf+rakam üretir;
 * ödeme sağlayıcıları genelde özel karakter kabul etmiyor.
 */
function payments_new_reference(string $prefix = 'BM'): string {
    return strtoupper($prefix) . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Fiyat metnini kuruşa çevir. PayTR tarafındaki ayrıştırıcıyı yeniden kullanır
 * ki iki sağlayıcı aynı fiyatı aynı şekilde okusun.
 */
function payments_amount_kurus($priceRaw): int {
    return paytr_parse_amount_kurus($priceRaw);
}

/** Kuruşu iyzico'nun beklediği ondalık metne çevir: 150000 -> "1500.00" */
function payments_kurus_to_decimal(int $kurus): string {
    return number_format($kurus / 100, 2, '.', '');
}

/**
 * Ödeme onaylandığında erişimi aç.
 *
 * Aynı kurs + e-posta için kayıt varsa güncellenir, yoksa açılır. Sipariş
 * satırıyla ilişkilendirilir. PayTR callback'indeki mantıkla aynı davranır,
 * ek olarak student_id de doldurulur.
 *
 * @return int enrollment id
 */
function payments_grant_enrollment(PDO $pdo, array $order, string $source): int {
    $courseId = (int)$order['course_id'];
    $email = (string)$order['student_email'];
    $studentId = !empty($order['student_id']) ? (int)$order['student_id'] : null;

    $st = $pdo->prepare(
        "SELECT id FROM course_enrollments WHERE course_id = ? AND student_email = ? LIMIT 1"
    );
    $st->execute([$courseId, $email]);
    $existing = $st->fetch();

    if ($existing) {
        $enrollmentId = (int)$existing['id'];
        $pdo->prepare(
            "UPDATE course_enrollments
             SET payment_status = 'paid', merchant_oid = ?, student_name = ?, student_phone = ?,
                 source = ?, student_id = COALESCE(student_id, ?)
             WHERE id = ?"
        )->execute([
            (string)$order['merchant_oid'],
            (string)$order['student_name'],
            (string)$order['student_phone'],
            $source,
            $studentId,
            $enrollmentId,
        ]);
    } else {
        $pdo->prepare(
            "INSERT INTO course_enrollments
             (course_id, student_id, student_name, student_email, student_phone,
              progress_pct, source, payment_status, merchant_oid, enrolled_at)
             VALUES (?,?,?,?,?,0,?,'paid',?,NOW())"
        )->execute([
            $courseId,
            $studentId,
            (string)$order['student_name'],
            $email,
            (string)$order['student_phone'],
            $source,
            (string)$order['merchant_oid'],
        ]);
        $enrollmentId = (int)$pdo->lastInsertId();
    }

    if ((int)($order['id'] ?? 0) > 0) {
        $pdo->prepare("UPDATE payment_orders SET enrollment_id = ? WHERE id = ?")
            ->execute([$enrollmentId, (int)$order['id']]);
    }

    try {
        require_once __DIR__ . '/mailer.php';
        mailer_notify_order_paid($pdo, $order);
    } catch (Throwable $e) {
        error_log('odeme maili: ' . $e->getMessage());
    }

    return $enrollmentId;
}

/**
 * İade sonrası erişimi kapat.
 *
 * Aynı kurs için başka ödenmiş sipariş varsa kayıt açık kalır (yeniden satın alma).
 */
function payments_revoke_enrollment(PDO $pdo, array $order, string $reason = 'Iade'): void {
    $orderId = (int)$order['id'];
    $courseId = (int)$order['course_id'];
    $studentId = !empty($order['student_id']) ? (int)$order['student_id'] : 0;
    $email = (string)$order['student_email'];
    $oid = (string)$order['merchant_oid'];

    $pdo->prepare(
        "UPDATE payment_orders
         SET status = 'refunded', error_message = ?, refunded_at = NOW()
         WHERE id = ?"
    )->execute([mb_substr($reason, 0, 255), $orderId]);

    $stillPaid = 0;
    if ($studentId > 0) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) c FROM payment_orders
             WHERE course_id = ? AND student_id = ? AND status = 'paid' AND id <> ?"
        );
        $st->execute([$courseId, $studentId, $orderId]);
        $stillPaid = (int)$st->fetch()['c'];
    }
    if ($stillPaid === 0 && $email !== '') {
        $st = $pdo->prepare(
            "SELECT COUNT(*) c FROM payment_orders
             WHERE course_id = ? AND student_email = ? AND status = 'paid' AND id <> ?"
        );
        $st->execute([$courseId, $email, $orderId]);
        $stillPaid = (int)$st->fetch()['c'];
    }
    if ($stillPaid > 0) {
        return;
    }

        $pdo->prepare(
            "UPDATE course_enrollments
             SET payment_status = 'refunded'
             WHERE course_id = ? AND (merchant_oid = ? OR (student_email = ? AND ? <> '') OR (student_id = ? AND ? > 0))"
        )->execute([$courseId, $oid, $email, $email, $studentId, $studentId]);
}

/** Kart, CVV, anahtar ve benzeri alanları logdan çıkarır. */
function payments_log_mask($value, string $key = '') {
    $lk = strtolower($key);
    $keep = preg_match('/conversation|basketid|merchant|paymentid|payment_id|token_tail|http|_error|_ok/i', $lk);
    $hide = !$keep && $lk !== '' && preg_match(
        '/card|pan|cvv|cvc|password|secret|apikey|api_key|authorization|expire|identity/',
        $lk
    );
    if ($hide) {
        return '[gizli]';
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = payments_log_mask($v, (string)$k);
        }
        return $out;
    }
    return $value;
}

function payments_log_resolve_order_id(array $payload): int {
    $token = trim((string)($payload['token'] ?? $payload['provider_token'] ?? ''));
    $conv = trim((string)($payload['conversationId'] ?? $payload['paymentConversationId'] ?? ''));
    $basket = trim((string)($payload['basketId'] ?? $payload['merchant_oid'] ?? ''));
    $payId = trim((string)($payload['paymentId'] ?? $payload['iyziPaymentId'] ?? $payload['provider_payment_id'] ?? ''));
    if ($token === '' && $conv === '' && $basket === '' && $payId === '') {
        return 0;
    }
    try {
        if (!function_exists('db')) {
            return 0;
        }
        $pdo = db();
        if ($token !== '') {
            $st = $pdo->prepare('SELECT id FROM payment_orders WHERE provider_token = ? LIMIT 1');
            $st->execute([$token]);
            $id = (int)$st->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        if ($basket !== '') {
            $st = $pdo->prepare('SELECT id FROM payment_orders WHERE merchant_oid = ? LIMIT 1');
            $st->execute([$basket]);
            $id = (int)$st->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        if ($conv !== '') {
            $st = $pdo->prepare('SELECT id FROM payment_orders WHERE conversation_id = ? OR merchant_oid = ? LIMIT 1');
            $st->execute([$conv, $conv]);
            $id = (int)$st->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        if ($payId !== '') {
            $st = $pdo->prepare('SELECT id FROM payment_orders WHERE provider_payment_id = ? LIMIT 1');
            $st->execute([$payId]);
            $id = (int)$st->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
    } catch (Throwable $e) {
        // Log kaydı ödeme akışını durdurmasın
    }
    return 0;
}

function payments_log_write(?int $orderId, string $provider, string $direction, string $path, $payload): void {
    try {
        if (!function_exists('db')) {
            return;
        }
        $pdo = db();
        payments_ensure_schema($pdo);
        $masked = payments_log_mask(is_array($payload) ? $payload : ['value' => $payload]);
        $json = json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '{}';
        }
        if (strlen($json) > 16000) {
            $json = substr($json, 0, 16000) . '…';
        }
        $oid = $orderId && $orderId > 0 ? $orderId : null;
        $st = $pdo->prepare(
            'INSERT INTO payment_logs (order_id, provider, direction, path, payload) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $oid,
            mb_substr($provider !== '' ? $provider : 'iyzico', 0, 20),
            mb_substr($direction, 0, 16),
            mb_substr($path, 0, 160),
            $json,
        ]);
    } catch (Throwable $e) {
        error_log('payment_logs: ' . $e->getMessage());
    }
}

function payments_notify_review(array $order): void {
    try {
        require_once __DIR__ . '/mailer.php';
        mailer_notify_review_order($order);
    } catch (Throwable $e) {
        error_log('inceleme maili: ' . $e->getMessage());
    }
}
