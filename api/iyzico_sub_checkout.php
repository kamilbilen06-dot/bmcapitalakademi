<?php
/**
 * WhatsApp grubu aboneliği — iyzico Subscription Checkout Form başlatır.
 *
 * POST /api/iyzico_sub_checkout.php  (öğrenci oturumu şart)
 * Tutar her zaman ayarlardaki fiyattan okunur.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/student_account.php';
require_once __DIR__ . '/subscriptions.php';

start_student_session();

function sub_checkout_fail(string $message): void {
    header('Location: /ogrenci/abonelik.php?' . http_build_query(['err' => $message]), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sub_checkout_fail('Geçersiz istek.');
}
if (!is_student()) {
    header('Location: /ogrenci/giris.php?next=' . rawurlencode('abonelik.php'), true, 302);
    exit;
}
if (!student_csrf_valid($_POST['csrf'] ?? '')) {
    sub_checkout_fail('Oturum süresi doldu. Lütfen tekrar deneyin.');
}
if (empty($_POST['terms'])) {
    sub_checkout_fail('Devam etmek için sözleşmeyi onaylamanız gerekir.');
}
if (!iyzico_ready()) {
    sub_checkout_fail('Kart ile ödeme henüz yapılandırılmadı.');
}

$phone = clean($_POST['phone'] ?? '');
if (preg_replace('/\D/', '', $phone) === '') {
    sub_checkout_fail('Abonelik için telefon numarası gerekli.');
}

try {
    $pdo = db();
    students_ensure_schema($pdo);
    subscriptions_ensure_schema($pdo);

    if (!subscription_enabled($pdo)) {
        sub_checkout_fail('Abonelik şu an kapalı.');
    }

    $session = current_student();
    $student = student_find_by_id($pdo, $session['id']);
    if (!$student || ($student['status'] ?? 'active') !== 'active') {
        student_logout();
        header('Location: /ogrenci/giris.php', true, 302);
        exit;
    }

    $block = subscription_blocking_row($pdo, (int)$student['id']);
    if ($block) {
        if ($block['status'] === 'pending' && strtotime((string)$block['created_at']) < time() - 1800) {
            $pdo->prepare(
                "UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(), error_message = ?, updated_at = NOW() WHERE id = ?"
            )->execute(['Ödeme tamamlanmadı', (int)$block['id']]);
        } elseif (in_array($block['status'], ['active', 'past_due'], true)) {
            header('Location: /ogrenci/aboneliklerim.php', true, 302);
            exit;
        } elseif ($block['status'] === 'pending') {
            sub_checkout_fail('Açık bir ödeme oturumunuz var. Birkaç dakika sonra tekrar deneyin veya Aboneliklerim’den iptal edin.');
        }
    }

    $plan = subscription_ensure_iyzico_plan($pdo);
    if (!$plan['ok']) {
        sub_checkout_fail('Abonelik başlatılamadı: ' . $plan['error']);
    }

    $amountKurus = subscription_price_kurus($pdo);
    if ($amountKurus < 100) {
        sub_checkout_fail('Abonelik fiyatı tanımlı değil.');
    }

    if (trim((string)($student['phone'] ?? '')) === '') {
        $pdo->prepare("UPDATE students SET phone = ? WHERE id = ?")
            ->execute([mb_substr($phone, 0, 40), (int)$student['id']]);
    }

    $fullName = trim((string)$student['name']) !== '' ? trim((string)$student['name']) : 'Öğrenci';
    $reference = payments_new_reference('SUB');
    $interval = $plan['interval'];

    $pdo->prepare(
        "INSERT INTO subscriptions
         (student_id, instructor_id, conversation_id, iyzico_plan_ref, student_name, student_email, student_phone,
          amount_kurus, interval_unit, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
    )->execute([
        (int)$student['id'],
        subscription_instructor_id($pdo),
        $reference,
        $plan['planRef'],
        $fullName,
        (string)$student['email'],
        $phone,
        $amountKurus,
        $interval,
    ]);
    $subId = (int)$pdo->lastInsertId();

    list($firstName, $lastName) = iyzico_split_name($fullName);
    $city = trim(clean($_POST['city'] ?? ''));
    if ($city === '') {
        $city = defined('BRAND_CITY') ? BRAND_CITY : 'İstanbul';
    }
    $address = trim(clean($_POST['address'] ?? ''));
    if ($address === '') {
        $address = 'Dijital ürün — adres bildirilmedi';
    }
    $identity = preg_replace('/\D/', '', (string)($_POST['identity'] ?? ''));
    if (strlen((string)$identity) !== 11) {
        $identity = IYZICO_PLACEHOLDER_IDENTITY;
    }

    $payload = [
        'locale' => 'tr',
        'conversationId' => $reference,
        'callbackUrl' => iyzico_sub_callback_url(),
        'pricingPlanReferenceCode' => $plan['planRef'],
        'subscriptionInitialStatus' => 'ACTIVE',
        'customer' => [
            'name' => $firstName,
            'surname' => $lastName,
            'email' => (string)$student['email'],
            'gsmNumber' => iyzico_format_gsm($phone),
            'identityNumber' => $identity,
            'billingAddress' => [
                'address' => mb_substr($address, 0, 250),
                'contactName' => mb_substr($fullName, 0, 100),
                'city' => mb_substr($city, 0, 60),
                'country' => 'Turkey',
            ],
        ],
    ];

    $res = iyzico_sub_checkout_initialize($payload);
    if (!$res['ok']) {
        $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(), error_message = ?, updated_at = NOW() WHERE id = ?")
            ->execute([mb_substr($res['error'], 0, 255), $subId]);
        error_log('iyzico sub initialize: ' . $res['error']);
        sub_checkout_fail('Ödeme başlatılamadı: ' . $res['error']);
    }

    $pdo->prepare("UPDATE subscriptions SET provider_token = ?, error_message = '', updated_at = NOW() WHERE id = ?")
        ->execute([$res['token'], $subId]);

    if ($res['paymentPageUrl'] !== '') {
        header('Location: ' . $res['paymentPageUrl'], true, 302);
        exit;
    }

    $_SESSION['iyzico_sub_form'] = $res['formContent'];
    header('Location: /ogrenci/abonelik-odeme.php', true, 302);
    exit;
} catch (Throwable $e) {
    error_log('iyzico sub checkout: ' . $e->getMessage());
    sub_checkout_fail('Ödeme başlatılamadı. Lütfen tekrar deneyin.');
}
