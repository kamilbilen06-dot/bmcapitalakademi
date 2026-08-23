<?php
/**
 * PayTR sipariş tablosu + enrollment ödeme durumu
 */
function paytr_ensure_schema(PDO $pdo) {
    require_once __DIR__ . '/egitmen_schema.php';
    egitmen_ensure_schema($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        merchant_oid VARCHAR(64) NOT NULL,
        course_id INT NOT NULL,
        student_name VARCHAR(255) NOT NULL DEFAULT '',
        student_email VARCHAR(255) NOT NULL DEFAULT '',
        student_phone VARCHAR(60) NOT NULL DEFAULT '',
        amount_kurus INT UNSIGNED NOT NULL DEFAULT 0,
        currency VARCHAR(8) NOT NULL DEFAULT 'TL',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        paytr_total_amount VARCHAR(32) NOT NULL DEFAULT '',
        enrollment_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME NULL,
        UNIQUE KEY uq_merchant_oid (merchant_oid),
        INDEX idx_course (course_id),
        INDEX idx_email (student_email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing(
        $pdo,
        'course_enrollments',
        'payment_status',
        "VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER source"
    );
    egitmen_add_column_if_missing(
        $pdo,
        'course_enrollments',
        'merchant_oid',
        "VARCHAR(64) NOT NULL DEFAULT '' AFTER payment_status"
    );
}

function paytr_site_base() {
    if (defined('PAYTR_SITE_BASE') && PAYTR_SITE_BASE !== '') {
        return rtrim(PAYTR_SITE_BASE, '/');
    }
    if (!function_exists('site_public_url')) {
        require_once __DIR__ . '/site_brand.php';
    }
    if (function_exists('site_public_url')) {
        $u = site_public_url();
        if ($u !== '') {
            return $u;
        }
    }
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function paytr_parse_amount_kurus($priceRaw) {
    $s = trim((string)$priceRaw);
    if ($s === '') {
        return 0;
    }
    // "1.500,50 TL" / "1500 TL" / "1,500.50"
    $s = preg_replace('/[^\d.,]/u', '', $s);
    if ($s === '') {
        return 0;
    }
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    } elseif (strpos($s, ',') !== false) {
        $parts = explode(',', $s);
        if (count($parts) === 2 && strlen($parts[1]) <= 2) {
            $s = $parts[0] . '.' . $parts[1];
        } else {
            $s = str_replace(',', '', $s);
        }
    } elseif (substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    $f = (float)$s;
    return (int)round($f * 100);
}

function paytr_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    $ip = trim($ip);
    // Lokal test: dış IP gerekir — config'te PAYTR_FORCE_IP tanımlanabilir
    if (defined('PAYTR_FORCE_IP') && PAYTR_FORCE_IP !== '') {
        return PAYTR_FORCE_IP;
    }
    if ($ip === '::1' || $ip === '127.0.0.1' || strpos($ip, '192.168.') === 0) {
        // PayTR lokal IP kabul etmez; testte dış IP kullanın
        return $ip;
    }
    return $ip;
}

function paytr_credentials_ready() {
    return defined('PAYTR_MERCHANT_ID') && PAYTR_MERCHANT_ID !== ''
        && defined('PAYTR_MERCHANT_KEY') && PAYTR_MERCHANT_KEY !== ''
        && defined('PAYTR_MERCHANT_SALT') && PAYTR_MERCHANT_SALT !== ''
        && PAYTR_MERCHANT_ID !== 'XXXXXX';
}
