<?php
/**
 * iyzico ödeme başlatma — Checkout Form oturumu açar ve kullanıcıyı
 * iyzico'nun ödeme sayfasına yönlendirir.
 *
 * POST /api/iyzico_checkout.php  (odeme.php formundan, öğrenci oturumu şart)
 *   course_id, phone, [address], [city], [identity]
 *
 * Tutar her zaman veritabanındaki kurs fiyatından okunur; istemciden gelen
 * hiçbir fiyat bilgisine güvenilmez.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/student_account.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/iyzico_client.php';

start_student_session();

$courseRaw = clean($_POST['course_id'] ?? '');

/** Hata durumunda ödeme sayfasına mesajla dön */
function checkout_fail(string $message, string $courseRef = '') {
    $qs = ['err' => $message];
    if ($courseRef !== '') {
        $qs['course'] = $courseRef;
    }
    header('Location: /odeme.php?' . http_build_query($qs), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkout_fail('Geçersiz istek.');
}
if (!is_student()) {
    header('Location: /ogrenci/giris.php?next=' . rawurlencode('../odeme.php?course=' . $courseRaw), true, 302);
    exit;
}
if (!student_csrf_valid($_POST['csrf'] ?? '')) {
    checkout_fail('Oturum süresi doldu. Lütfen tekrar deneyin.', $courseRaw);
}
if (empty($_POST['terms'])) {
    checkout_fail('Devam etmek için satış sözleşmesini onaylamanız gerekir.', $courseRaw);
}
if (!iyzico_ready()) {
    checkout_fail('Kart ile ödeme henüz yapılandırılmadı.', $courseRaw);
}

// Kurs kimliği: "12" veya "course-12"
$courseId = 0;
if (is_numeric($courseRaw)) {
    $courseId = (int)$courseRaw;
} elseif (preg_match('/^course-(\d+)$/', (string)$courseRaw, $m)) {
    $courseId = (int)$m[1];
}
if ($courseId <= 0) {
    checkout_fail('Eğitim seçilmedi.', $courseRaw);
}

$phone = clean($_POST['phone'] ?? '');
if (preg_replace('/\D/', '', $phone) === '') {
    checkout_fail('Ödeme için telefon numarası gerekli.', $courseRaw);
}

try {
    $pdo = db();
    students_ensure_schema($pdo);
    payments_ensure_schema($pdo);

    $session = current_student();
    $student = student_find_by_id($pdo, $session['id']);
    if (!$student || ($student['status'] ?? 'active') !== 'active') {
        student_logout();
        header('Location: /ogrenci/giris.php', true, 302);
        exit;
    }

    $st = $pdo->prepare("SELECT id, title, price, category FROM courses WHERE id = ? AND status = 'published' LIMIT 1");
    $st->execute([$courseId]);
    $course = $st->fetch();
    if (!$course) {
        checkout_fail('Eğitim bulunamadı veya yayında değil.', $courseRaw);
    }

    // Zaten erişimi varsa tekrar ödeme almayalım
    if (student_has_paid_access($pdo, (int)$student['id'], $courseId)) {
        header('Location: /ogrenci/index.php', true, 302);
        exit;
    }

    $amountKurus = payments_amount_kurus($course['price']);
    if ($amountKurus < 100) {
        checkout_fail('Bu eğitim için online ödeme tutarı tanımlı değil. Lütfen bizimle iletişime geçin.', $courseRaw);
    }

    // Profilde telefon yoksa doldur — sonraki ödemede tekrar sorulmasın
    if (trim((string)($student['phone'] ?? '')) === '') {
        $pdo->prepare("UPDATE students SET phone = ? WHERE id = ?")
            ->execute([mb_substr($phone, 0, 40), (int)$student['id']]);
    }

    $reference = payments_new_reference('IYZ');
    $fullName = trim((string)$student['name']) !== '' ? trim((string)$student['name']) : 'Öğrenci';

    // Siparişi ÖNCE kaydet — iyzico'dan dönen jetonu bu satıra bağlayacağız
    $pdo->prepare(
        "INSERT INTO payment_orders
         (merchant_oid, provider, conversation_id, course_id, student_id,
          student_name, student_email, student_phone, amount_kurus, currency, status, created_at)
         VALUES (?, 'iyzico', ?, ?, ?, ?, ?, ?, ?, 'TRY', 'pending', NOW())"
    )->execute([
        $reference,
        $reference,
        $courseId,
        (int)$student['id'],
        $fullName,
        (string)$student['email'],
        $phone,
        $amountKurus,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $payload = iyzico_build_checkout_payload([
        'reference' => $reference,
        'amountKurus' => $amountKurus,
        'courseId' => $courseId,
        'courseTitle' => (string)$course['title'],
        'courseCategory' => (string)$course['category'],
        'studentId' => (int)$student['id'],
        'fullName' => $fullName,
        'email' => (string)$student['email'],
        'phone' => $phone,
        'city' => clean($_POST['city'] ?? ''),
        'address' => clean($_POST['address'] ?? ''),
        'identity' => clean($_POST['identity'] ?? ''),
        'registeredAt' => $student['created_at'] ?? null,
        'lastLoginAt' => $student['last_login_at'] ?? null,
        'ip' => paytr_client_ip(),
        'callbackUrl' => iyzico_callback_url(),
    ]);

    $res = iyzico_checkout_initialize($payload);

    if (!$res['ok']) {
        $pdo->prepare("UPDATE payment_orders SET status = 'failed', error_message = ? WHERE id = ?")
            ->execute([mb_substr($res['error'], 0, 255), $orderId]);
        error_log('iyzico initialize hatasi: ' . $res['error']);
        checkout_fail('Ödeme başlatılamadı: ' . $res['error'], $courseRaw);
    }

    $pdo->prepare("UPDATE payment_orders SET provider_token = ? WHERE id = ?")
        ->execute([$res['token'], $orderId]);

    header('Location: ' . $res['paymentPageUrl'], true, 302);
    exit;
} catch (Throwable $e) {
    error_log('iyzico checkout istisnasi: ' . $e->getMessage());
    checkout_fail('Ödeme başlatılamadı. Lütfen tekrar deneyin.', $courseRaw);
}
