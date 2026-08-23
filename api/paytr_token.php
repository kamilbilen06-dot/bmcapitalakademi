<?php
/**
 * PayTR iFrame token — 1. ADIM
 * POST JSON: name, email, phone, course_id
 * Dönüş: { ok, token, merchant_oid, amount_label, test_mode }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/paytr_config.php';
require_once __DIR__ . '/paytr_schema.php';

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
    if (!paytr_credentials_ready()) {
        json_out([
            'ok' => false,
            'error' => 'PayTR anahtarları eksik. Mağaza Paneli > Entegrasyon Bilgileri değerlerini api/paytr_config.php veya paytr_config.local.php dosyasına girin.',
            'code' => 'config',
        ], 422);
    }

    $pdo = db();
    paytr_ensure_schema($pdo);

    $in = body_json();
    if (!$in) {
        $in = $_POST;
    }

    $name = clean($in['name'] ?? $in['student_name'] ?? '');
    $email = clean($in['email'] ?? $in['student_email'] ?? '');
    $phone = clean($in['phone'] ?? $in['student_phone'] ?? '');
    $rawCourse = $in['course_id'] ?? $in['course'] ?? '';

    if ($name === '') {
        json_out(['ok' => false, 'error' => 'Ad soyad gerekli'], 422);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'error' => 'Geçerli e-posta gerekli'], 422);
    }
    // PayTR: e-postada Türkçe karakter olmamalı
    if (preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $email)) {
        json_out(['ok' => false, 'error' => 'E-posta adresinde Türkçe karakter olmamalı'], 422);
    }

    $courseId = 0;
    if (is_numeric($rawCourse)) {
        $courseId = (int)$rawCourse;
    } elseif (preg_match('/^course-(\d+)$/', (string)$rawCourse, $m)) {
        $courseId = (int)$m[1];
    }
    if ($courseId <= 0) {
        json_out(['ok' => false, 'error' => 'Kurs seçimi gerekli'], 422);
    }

    $st = $pdo->prepare("SELECT id, title, price, status FROM courses WHERE id = ?");
    $st->execute([$courseId]);
    $course = $st->fetch();
    if (!$course || $course['status'] !== 'published') {
        json_out(['ok' => false, 'error' => 'Kurs bulunamadı veya yayında değil'], 404);
    }

    $amountKurus = paytr_parse_amount_kurus($course['price'] ?? '');
    if ($amountKurus < 100) {
        json_out([
            'ok' => false,
            'error' => 'Kurs fiyatı geçersiz veya 1 TL altında. Eğitmen panelinden fiyat girin (örn. 1500 veya 1.500 TL).',
        ], 422);
    }

    $merchantOid = 'BM' . date('YmdHis') . sprintf('%04d', random_int(0, 9999));
    $merchantOid = preg_replace('/[^A-Za-z0-9]/', '', $merchantOid);

    $pdo->prepare(
        "INSERT INTO payment_orders
         (merchant_oid, course_id, student_name, student_email, student_phone, amount_kurus, currency, status)
         VALUES (?,?,?,?,?,?, 'TL', 'pending')"
    )->execute([$merchantOid, $courseId, $name, $email, $phone, $amountKurus]);

    $base = paytr_site_base();
    $merchantOkUrl = $base . '/odeme-basarili.html?oid=' . urlencode($merchantOid);
    $merchantFailUrl = $base . '/odeme-basarisiz.html?oid=' . urlencode($merchantOid);

    $unitPrice = number_format($amountKurus / 100, 2, '.', '');
    $userBasket = base64_encode(json_encode([
        [mb_substr($course['title'] ?: ('Kurs #' . $courseId), 0, 100), $unitPrice, 1],
    ], JSON_UNESCAPED_UNICODE));

    $userIp = paytr_client_ip();
    $testMode = (int)(defined('PAYTR_TEST_MODE') ? PAYTR_TEST_MODE : 1);
    $debugOn = (int)(defined('PAYTR_DEBUG_ON') ? PAYTR_DEBUG_ON : 1);
    $noInstallment = (int)(defined('PAYTR_NO_INSTALLMENT') ? PAYTR_NO_INSTALLMENT : 0);
    $maxInstallment = (int)(defined('PAYTR_MAX_INSTALLMENT') ? PAYTR_MAX_INSTALLMENT : 0);
    $currency = 'TL';
    $paymentAmount = (string)$amountKurus;

    $hashStr = PAYTR_MERCHANT_ID . $userIp . $merchantOid . $email . $paymentAmount
        . $userBasket . $noInstallment . $maxInstallment . $currency . $testMode;
    $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . PAYTR_MERCHANT_SALT, PAYTR_MERCHANT_KEY, true));

    $postVals = [
        'merchant_id' => PAYTR_MERCHANT_ID,
        'user_ip' => $userIp,
        'merchant_oid' => $merchantOid,
        'email' => $email,
        'payment_amount' => $paymentAmount,
        'paytr_token' => $paytrToken,
        'user_basket' => $userBasket,
        'debug_on' => $debugOn,
        'no_installment' => $noInstallment,
        'max_installment' => $maxInstallment,
        'user_name' => mb_substr($name, 0, 60),
        'user_address' => 'Turkiye',
        'user_phone' => mb_substr($phone !== '' ? $phone : '05000000000', 0, 20),
        'merchant_ok_url' => $merchantOkUrl,
        'merchant_fail_url' => $merchantFailUrl,
        'timeout_limit' => '30',
        'currency' => $currency,
        'test_mode' => $testMode,
        'lang' => 'tr',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.paytr.com/odeme/api/get-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postVals);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    // Lokal Windows SSL sorununda geçici: CURLOPT_SSL_VERIFYPEER 0 (canlıda açık kalsın)
    if (defined('PAYTR_SSL_VERIFY') && !PAYTR_SSL_VERIFY) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    $result = curl_exec($ch);
    $cerr = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    if ($result === false || $result === '') {
        json_out(['ok' => false, 'error' => 'PayTR bağlantı hatası: ' . ($cerr ?: 'boş yanıt')], 502);
    }

    $res = json_decode($result, true);
    if (!is_array($res) || ($res['status'] ?? '') !== 'success' || empty($res['token'])) {
        $reason = is_array($res) ? ($res['reason'] ?? json_encode($res, JSON_UNESCAPED_UNICODE)) : $result;
        json_out(['ok' => false, 'error' => 'PayTR token alınamadı: ' . $reason], 502);
    }

    json_out([
        'ok' => true,
        'token' => $res['token'],
        'merchant_oid' => $merchantOid,
        'amount_kurus' => $amountKurus,
        'amount_label' => number_format($amountKurus / 100, 2, ',', '.') . ' TL',
        'course_title' => $course['title'],
        'test_mode' => $testMode === 1,
        'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . $res['token'],
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Ödeme başlatılamadı'], 500);
}
