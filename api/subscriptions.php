<?php
/**
 * WhatsApp grubu aboneliği — iş kuralları.
 *
 * Kartı biz saklamayız; iyzico Subscription ürünü çeker.
 * Gruba ekleme/çıkarma siteden yapılmaz (admin WhatsApp'tan elle).
 */
require_once __DIR__ . '/subscriptions_schema.php';
require_once __DIR__ . '/iyzico_client.php';

function subscription_setting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $st = $pdo->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return $default;
        }
        $v = trim((string)$v);
        return $v === '' ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function subscription_set_setting(PDO $pdo, string $key, string $value): void {
    $st = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $st->execute([$key, $value]);
}

function subscription_enabled(PDO $pdo): bool {
    return subscription_setting($pdo, 'sub_enabled', '1') !== '0';
}

function subscription_title(PDO $pdo): string {
    return subscription_setting($pdo, 'sub_title', 'WhatsApp analiz grubu');
}

function subscription_blurb(PDO $pdo): string {
    return subscription_setting($pdo, 'sub_blurb', 'Aylık WhatsApp analiz grubu üyeliği.');
}

function subscription_price_kurus(PDO $pdo): int {
    $n = payments_amount_kurus(subscription_setting($pdo, 'sub_price', '199'));
    return $n >= 100 ? $n : 0;
}

function subscription_price_label(PDO $pdo): string {
    $k = subscription_price_kurus($pdo);
    if ($k < 100) {
        return '—';
    }
    $tl = $k / 100;
    if (abs($tl - round($tl)) < 0.001) {
        return number_format((int)round($tl), 0, ',', '.') . ' TL';
    }
    return number_format($tl, 2, ',', '.') . ' TL';
}

function subscription_interval(PDO $pdo = null): string {
    return iyzico_is_sandbox() ? 'DAILY' : 'MONTHLY';
}

function subscription_interval_label(): string {
    return iyzico_is_sandbox() ? 'günlük (sandbox test)' : 'aylık';
}

function subscription_add_period(string $interval, string $fromSql = ''): string {
    $t = $fromSql !== '' ? strtotime($fromSql) : false;
    if ($t === false) {
        $t = time();
    }
    $u = strtoupper($interval);
    if ($u === 'DAILY') {
        return date('Y-m-d H:i:s', strtotime('+1 day', $t));
    }
    if ($u === 'WEEKLY') {
        return date('Y-m-d H:i:s', strtotime('+1 week', $t));
    }
    if ($u === 'YEARLY') {
        return date('Y-m-d H:i:s', strtotime('+1 year', $t));
    }
    return date('Y-m-d H:i:s', strtotime('+1 month', $t));
}

function subscription_status_label(string $status, bool $entitled = false): string {
    if ($status === 'cancelled' && $entitled) {
        return 'Aktif · iptal edildi';
    }
    $map = [
        'pending' => 'Ödeme bekliyor',
        'active' => 'Aktif',
        'past_due' => 'Kart reddedildi',
        'cancelled' => 'İptal edildi',
        'expired' => 'Süresi doldu',
    ];
    return $map[$status] ?? $status;
}

/**
 * Dönem henüz açıksa (aktif, iptal ama bitmemiş, kısa geçmiş-due) grup üyesi sayılır.
 */
function subscription_is_entitled(array $row): bool {
    $st = (string)($row['status'] ?? '');
    $end = (string)($row['current_period_end'] ?? '');
    $endTs = $end !== '' ? strtotime($end) : false;
    $periodOpen = $endTs === false || $endTs > time();
    if ($st === 'active') {
        return $periodOpen;
    }
    if ($st === 'cancelled' && $endTs && $endTs > time()) {
        return true;
    }
    if ($st === 'past_due') {
        if ($endTs && $endTs > time()) {
            return true;
        }
        $fail = (string)($row['last_failure_at'] ?? '');
        $failTs = $fail !== '' ? strtotime($fail) : false;
        return $failTs !== false && (time() - $failTs) < (3 * 86400);
    }
    return false;
}

/** Dönemi bitmiş aktif kayıtları expired yap. Cron ve panel okumasında çağrılır. */
function subscription_expire_overdue(PDO $pdo): int {
    try {
        $n = $pdo->exec(
            "UPDATE subscriptions
             SET status = 'expired', updated_at = NOW()
             WHERE status IN ('active', 'past_due')
               AND current_period_end IS NOT NULL
               AND current_period_end < NOW()"
        );
        return (int)$n;
    } catch (Throwable $e) {
        return 0;
    }
}

function subscription_find_by_id(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function subscription_find_by_token(PDO $pdo, string $token): ?array {
    if ($token === '') {
        return null;
    }
    $st = $pdo->prepare("SELECT * FROM subscriptions WHERE provider_token = ? LIMIT 1");
    $st->execute([$token]);
    $row = $st->fetch();
    return $row ?: null;
}

function subscription_find_by_iyzico_ref(PDO $pdo, string $ref): ?array {
    if ($ref === '') {
        return null;
    }
    $st = $pdo->prepare("SELECT * FROM subscriptions WHERE iyzico_subscription_ref = ? LIMIT 1");
    $st->execute([$ref]);
    $row = $st->fetch();
    return $row ?: null;
}

function subscription_find_current(PDO $pdo, int $studentId): ?array {
    subscription_expire_overdue($pdo);
    $st = $pdo->prepare(
        "SELECT * FROM subscriptions
         WHERE student_id = ?
           AND (
             status IN ('active', 'past_due')
             OR (status = 'cancelled'
                 AND current_period_end IS NOT NULL
                 AND current_period_end > NOW())
           )
         ORDER BY FIELD(status, 'active', 'past_due', 'cancelled'), id DESC
         LIMIT 1"
    );
    $st->execute([$studentId]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Ödeme sayfasında bırakılmış / iptal edilmiş denemeyi kapat.
 *
 * iyzico referansı yok ve hiç tahsilat yoksa bu bir abonelik değildir;
 * Aboneliklerim'de "Ödeme bekliyor" görünmesin, yeni deneme de engellenmesin.
 */
function subscription_abandon_unpaid_pending(PDO $pdo, int $studentId): int {
    if ($studentId <= 0) {
        return 0;
    }
    try {
        $st = $pdo->prepare(
            "UPDATE subscriptions
             SET status = 'cancelled', cancelled_at = NOW(),
                 error_message = 'Ödeme tamamlanmadı', updated_at = NOW()
             WHERE student_id = ?
               AND status = 'pending'
               AND iyzico_subscription_ref = ''
               AND last_paid_at IS NULL"
        );
        $st->execute([$studentId]);
        return (int)$st->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function subscription_blocking_row(PDO $pdo, int $studentId): ?array {
    $st = $pdo->prepare(
        "SELECT * FROM subscriptions
         WHERE student_id = ? AND status IN ('active', 'past_due', 'pending')
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$studentId]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * iyzico'da ürün + fiyat planı olduğundan emin ol.
 * Sandbox: DAILY, canlı: MONTHLY. Fiyat değişince yeni plan açılır.
 *
 * @return array{ok:bool, productRef:string, planRef:string, interval:string, price:string, error:string}
 */
function subscription_iyzico_env(): string {
    return iyzico_is_sandbox() ? 'sandbox' : 'live';
}

/** settings’teki iyzico abonelik referans anahtarları */
function subscription_iyzico_setting_keys(string $env): array {
    return [
        'product' => 'sub_iyzico_product_ref_' . $env,
        'plan' => 'sub_iyzico_plan_ref_' . $env,
        'interval' => 'sub_iyzico_plan_interval_' . $env,
        'price' => 'sub_iyzico_plan_price_' . $env,
    ];
}

function subscription_save_iyzico_plan_refs(
    PDO $pdo,
    string $env,
    string $productRef,
    string $planRef,
    string $interval,
    string $price
): void {
    $keys = subscription_iyzico_setting_keys($env);
    subscription_set_setting($pdo, $keys['product'], $productRef);
    subscription_set_setting($pdo, $keys['plan'], $planRef);
    subscription_set_setting($pdo, $keys['interval'], $interval);
    subscription_set_setting($pdo, $keys['price'], $price);
    if ($env === 'live') {
        subscription_set_setting($pdo, 'sub_iyzico_product_ref', $productRef);
        subscription_set_setting($pdo, 'sub_iyzico_plan_ref', $planRef);
        subscription_set_setting($pdo, 'sub_iyzico_plan_interval', $interval);
        subscription_set_setting($pdo, 'sub_iyzico_plan_price', $price);
    }
}

/**
 * Kayıtlı plan referansını sil.
 *
 * iyzico ödeme başlatmayı reddederse (silinmiş/bozuk plan) referans ayarlarda
 * kalıyor ve sonraki her deneme aynı hatayı alıyordu. Referans temizlenince
 * bir sonraki denemede plan yeniden kurulur.
 */
function subscription_forget_iyzico_plan_refs(PDO $pdo, string $env): void {
    $keys = subscription_iyzico_setting_keys($env);
    foreach ($keys as $key) {
        subscription_set_setting($pdo, $key, '');
    }
    foreach (['sub_iyzico_product_ref', 'sub_iyzico_plan_ref', 'sub_iyzico_plan_interval', 'sub_iyzico_plan_price'] as $legacy) {
        subscription_set_setting($pdo, $legacy, '');
    }
}

/** Eski tek set ayarlar (yerel DB’den kalan) — sandbox/canlı ayrımı yokken kaydedilmiş olabilir. */
function subscription_legacy_iyzico_refs(PDO $pdo): array {
    return [
        'product' => subscription_setting($pdo, 'sub_iyzico_product_ref', ''),
        'plan' => subscription_setting($pdo, 'sub_iyzico_plan_ref', ''),
        'interval' => subscription_setting($pdo, 'sub_iyzico_plan_interval', ''),
        'price' => subscription_setting($pdo, 'sub_iyzico_plan_price', ''),
    ];
}

/**
 * @param string $excludePlanRef iyzico'nun reddettiği plan; yeniden seçilmez.
 */
function subscription_ensure_iyzico_plan(PDO $pdo, string $excludePlanRef = ''): array {
    $interval = subscription_interval();
    $kurus = subscription_price_kurus($pdo);
    if ($kurus < 100) {
        return ['ok' => false, 'productRef' => '', 'planRef' => '', 'interval' => $interval, 'price' => '', 'error' => 'Abonelik fiyatı tanımlı değil.'];
    }
    $price = payments_kurus_to_decimal($kurus);
    $env = subscription_iyzico_env();
    $keys = subscription_iyzico_setting_keys($env);

    $productRef = subscription_setting($pdo, $keys['product'], '');
    $planRef = subscription_setting($pdo, $keys['plan'], '');
    $storedInterval = subscription_setting($pdo, $keys['interval'], '');
    $storedPrice = subscription_setting($pdo, $keys['price'], '');

    if ($productRef === '' || $planRef === '') {
        $legacy = subscription_legacy_iyzico_refs($pdo);
        if ($productRef === '' && $legacy['product'] !== '') {
            $productRef = $legacy['product'];
        }
        if ($planRef === '' && $legacy['plan'] !== '') {
            $planRef = $legacy['plan'];
        }
        if ($storedInterval === '' && $legacy['interval'] !== '') {
            $storedInterval = $legacy['interval'];
        }
        if ($storedPrice === '' && $legacy['price'] !== '') {
            $storedPrice = $legacy['price'];
        }
    }

    $excludePlanRef = trim($excludePlanRef);
    if ($excludePlanRef !== '' && $planRef === $excludePlanRef) {
        $planRef = '';
    }

    if ($productRef !== '' && $planRef !== '' && $storedInterval === $interval && $storedPrice === $price) {
        return ['ok' => true, 'productRef' => $productRef, 'planRef' => $planRef, 'interval' => $interval, 'price' => $price, 'error' => ''];
    }

    if (!iyzico_ready()) {
        return ['ok' => false, 'productRef' => '', 'planRef' => '', 'interval' => $interval, 'price' => $price, 'error' => 'iyzico anahtarları tanımlı değil.'];
    }

    $title = subscription_title($pdo);
    $blurb = subscription_blurb($pdo);
    $planName = $interval === 'DAILY' ? ($title . ' (günlük test)') : ($title . ' (aylık)');
    $productRow = null;

    if ($productRef === '') {
        $productRow = iyzico_sub_product_find_by_name($title);
        if (is_array($productRow)) {
            $productRef = trim((string)($productRow['referenceCode'] ?? ''));
        }
    }

    if ($productRef === '') {
        $created = iyzico_sub_product_create($title, $blurb);
        if (!$created['ok']) {
            if (iyzico_sub_error_is_duplicate($created['error'])) {
                $productRow = iyzico_sub_product_find_by_name($title);
                $productRef = is_array($productRow)
                    ? trim((string)($productRow['referenceCode'] ?? ''))
                    : '';
            }
            if ($productRef === '') {
                return ['ok' => false, 'productRef' => '', 'planRef' => '', 'interval' => $interval, 'price' => $price, 'error' => iyzico_sub_addon_hint($created['error'])];
            }
        } else {
            $inner = iyzico_v2_data($created['data']);
            $productRef = (string)($inner['referenceCode'] ?? $inner['productReferenceCode'] ?? '');
            if ($productRef === '') {
                return ['ok' => false, 'productRef' => '', 'planRef' => '', 'interval' => $interval, 'price' => $price, 'error' => 'iyzico ürün referansı dönmedi.'];
            }
        }
    }

    if ($planRef === '' || $storedInterval !== $interval || $storedPrice !== $price) {
        if (!is_array($productRow) || trim((string)($productRow['referenceCode'] ?? '')) !== $productRef) {
            $productRow = iyzico_sub_product_find_by_name($title);
            if (!is_array($productRow) || trim((string)($productRow['referenceCode'] ?? '')) !== $productRef) {
                foreach (iyzico_sub_products_all() as $item) {
                    if (trim((string)($item['referenceCode'] ?? '')) === $productRef) {
                        $productRow = $item;
                        break;
                    }
                }
            }
        }
        if (is_array($productRow)) {
            $planRef = iyzico_sub_plan_pick($productRow, $price, $interval, $excludePlanRef);
        }
    }

    if ($planRef === '' || $storedInterval !== $interval || $storedPrice !== $price) {
        $plan = iyzico_sub_plan_create($productRef, $planName, $price, $interval);
        if (!$plan['ok']) {
            foreach (iyzico_sub_products_all() as $item) {
                if (trim((string)($item['referenceCode'] ?? '')) === $productRef) {
                    $planRef = iyzico_sub_plan_pick($item, $price, $interval, $excludePlanRef);
                    if ($planRef !== '') {
                        break;
                    }
                }
            }
            if ($planRef === '') {
                return ['ok' => false, 'productRef' => $productRef, 'planRef' => '', 'interval' => $interval, 'price' => $price, 'error' => iyzico_sub_addon_hint($plan['error'])];
            }
        } else {
            $inner = iyzico_v2_data($plan['data']);
            $planRef = (string)($inner['referenceCode'] ?? $inner['pricingPlanReferenceCode'] ?? '');
            if ($planRef === '') {
                return ['ok' => false, 'productRef' => $productRef, 'planRef' => '', 'interval' => $interval, 'price' => $price, 'error' => 'iyzico plan referansı dönmedi.'];
            }
        }
    }

    subscription_save_iyzico_plan_refs($pdo, $env, $productRef, $planRef, $interval, $price);

    return ['ok' => true, 'productRef' => $productRef, 'planRef' => $planRef, 'interval' => $interval, 'price' => $price, 'error' => ''];
}

function subscription_touch(PDO $pdo, int $id): void {
    $pdo->prepare("UPDATE subscriptions SET updated_at = NOW() WHERE id = ?")->execute([$id]);
}

function subscription_mark_active(PDO $pdo, array $row, array $inner, string $interval): void {
    $id = (int)$row['id'];
    $ref = (string)($inner['subscriptionReferenceCode'] ?? $inner['referenceCode'] ?? $row['iyzico_subscription_ref']);
    $cust = (string)($inner['customerReferenceCode'] ?? $row['iyzico_customer_ref']);
    $endRaw = (string)($inner['endDate'] ?? $inner['subscriptionEndDate'] ?? $inner['nextPaymentDate'] ?? '');
    $endTs = $endRaw !== '' ? strtotime($endRaw) : false;
    $end = $endTs ? date('Y-m-d H:i:s', $endTs) : '';
    if ($end === '') {
        $base = (string)($row['current_period_end'] ?? '');
        if ($base !== '' && strtotime($base) > time()) {
            $end = subscription_add_period($interval, $base);
        } else {
            $end = subscription_add_period($interval, date('Y-m-d H:i:s'));
        }
    }
    $pdo->prepare(
        "UPDATE subscriptions
         SET status = 'active',
             iyzico_subscription_ref = ?,
             iyzico_customer_ref = ?,
             current_period_end = ?,
             last_paid_at = NOW(),
             error_message = '',
             cancelled_at = NULL,
             updated_at = NOW()
         WHERE id = ?"
    )->execute([$ref, $cust, $end, $id]);
}

function subscription_record_invoice(PDO $pdo, int $subscriptionId, string $orderRef, int $amountKurus, string $status): bool {
    $orderRef = mb_substr(trim($orderRef), 0, 80);
    if ($orderRef === '') {
        $orderRef = 'inv-' . $subscriptionId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));
    }
    try {
        $pdo->prepare(
            "INSERT INTO subscription_invoices (subscription_id, order_reference, amount_kurus, status, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        )->execute([$subscriptionId, $orderRef, $amountKurus, $status]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function subscription_notify_new(PDO $pdo, array $row): void {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    try {
        $lock = $pdo->prepare("UPDATE subscriptions SET mail_sent_at = NOW() WHERE id = ? AND mail_sent_at IS NULL");
        $lock->execute([$id]);
        if ($lock->rowCount() === 0) {
            return;
        }
        require_once __DIR__ . '/mailer.php';
        $rowCopy = $row;
        mailer_defer(static function () use ($rowCopy) {
            mailer_notify_subscription_new($rowCopy);
            mailer_notify_subscription_student_new($rowCopy);
        });
    } catch (Throwable $e) {
        error_log('abonelik maili: ' . $e->getMessage());
    }
}

function subscription_activate_from_retrieve(PDO $pdo, array $row, array $inner): void {
    $interval = (string)($row['interval_unit'] ?: subscription_interval());
    $wasNew = ($row['status'] ?? '') !== 'active';
    subscription_mark_active($pdo, $row, $inner, $interval);
    if ($wasNew) {
        $orderRef = (string)($inner['orderReferenceCode'] ?? $inner['parentReferenceCode'] ?? ('first-' . $row['conversation_id']));
        subscription_record_invoice($pdo, (int)$row['id'], $orderRef, (int)$row['amount_kurus'], 'paid');
        subscription_notify_new($pdo, $row);
    }
}

/**
 * Ödeme sonrası doğrulanamamış kaydı iyzico'dan tekrar sorup eşitle.
 *
 * Callback anında iyzico doğrulaması patlarsa (ağ/geçici API hatası) kayıt
 * pending kalır ama iyzico tarafında abonelik aktif olabilir. Bu durumda
 * sayfada "Ödeme bekliyor" donup kalıyordu. Aynı şekilde hatayla iptal edilmiş
 * ama iyzico'da aktif olan kayıtlar da buradan kurtarılır.
 *
 * @return bool Kayıt güncellendiyse true.
 */
function subscription_reconcile_row(PDO $pdo, array $row): bool {
    $token = trim((string)($row['provider_token'] ?? ''));
    if ($token === '' || !iyzico_ready()) {
        return false;
    }

    $res = iyzico_sub_checkout_retrieve($token, (string)($row['conversation_id'] ?? ''));
    if (!$res['ok']) {
        subscription_touch($pdo, (int)$row['id']);
        return false;
    }

    if ($res['active']) {
        subscription_activate_from_retrieve($pdo, $row, $res['inner']);
        return true;
    }

    // iyzico kesin olarak "aktif değil" dedi; ancak zaten iptalse dokunmuyoruz.
    if ((string)$row['status'] === 'pending') {
        $st = strtoupper((string)($res['inner']['subscriptionStatus'] ?? $res['inner']['status'] ?? ''));
        $pdo->prepare(
            "UPDATE subscriptions
             SET status = 'cancelled', cancelled_at = NOW(), error_message = ?, updated_at = NOW()
             WHERE id = ? AND status = 'pending'"
        )->execute(['Abonelik onaylanmadı (' . $st . ')', (int)$row['id']]);
        return true;
    }

    subscription_touch($pdo, (int)$row['id']);
    return false;
}

/**
 * Ödemeye hiç başlanmadan bırakılmış kayıtların kendi işaretlerimiz.
 * Bunlar için iyzico'ya sormak gereksizdir; sorulunca her sayfa açılışına
 * boşuna API gecikmesi ekleniyordu.
 */
function subscription_error_needs_verify(string $error): bool {
    $e = mb_strtolower(trim($error));
    if ($e === '') {
        return false;
    }
    foreach (['ödeme tamamlanmadı', 'ödeme tamamlanmadan', 'abonelik onaylanmadı'] as $skip) {
        if (str_contains($e, $skip)) {
            return false;
        }
    }
    return true;
}

/**
 * Öğrencinin son aboneliği doğrulanmayı bekliyorsa iyzico'dan eşitle.
 *
 * Sayfayı yavaşlatmamak için yalnızca son 3 gün içinde açılmış, jetonu olan ve
 * iyzico referansı henüz yazılmamış kayıtlar denenir. $force, kullanıcı ödeme
 * dönüşünden geldiğinde (callback redirect) bekleme süresini atlar.
 * $pendingOnly, abone olma akışında kullanılır: yalnızca yeni ödemeyi engelleyen
 * pending kayıt sorulur, iptal kayıtları için API isteği atılmaz.
 */
function subscription_reconcile_for_student(
    PDO $pdo,
    int $studentId,
    bool $force = false,
    bool $pendingOnly = false
): bool {
    if ($studentId <= 0) {
        return false;
    }
    try {
        $statusFilter = $pendingOnly ? "status = 'pending'" : "status IN ('pending', 'cancelled')";
        $st = $pdo->prepare(
            "SELECT * FROM subscriptions
             WHERE student_id = ?
               AND provider_token <> ''
               AND iyzico_subscription_ref = ''
               AND $statusFilter
               AND created_at > (NOW() - INTERVAL 3 DAY)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([$studentId]);
        $row = $st->fetch();
        if (!$row) {
            return false;
        }
        // İptal kaydı yalnızca doğrulama hatası yüzünden kapandıysa sorulur;
        // kullanıcının kendi iptali veya yarım bırakılmış ödeme sorulmaz.
        if ((string)$row['status'] === 'cancelled' && !subscription_error_needs_verify((string)$row['error_message'])) {
            return false;
        }
        if (!$force) {
            $last = (string)($row['updated_at'] ?? '');
            $lastTs = $last !== '' ? strtotime($last) : false;
            if ($lastTs !== false && (time() - $lastTs) < 20) {
                return false;
            }
        }
        return subscription_reconcile_row($pdo, $row);
    } catch (Throwable $e) {
        error_log('abonelik esitleme: ' . $e->getMessage());
        return false;
    }
}

function subscription_handle_webhook(PDO $pdo, array $payload): void {
    $event = strtolower((string)($payload['iyziEventType'] ?? ''));
    $subRef = trim((string)($payload['subscriptionReferenceCode'] ?? ''));
    $orderRef = trim((string)($payload['orderReferenceCode'] ?? $payload['iyziReferenceCode'] ?? ''));
    $custRef = trim((string)($payload['customerReferenceCode'] ?? ''));
    if ($subRef === '') {
        return;
    }

    $row = subscription_find_by_iyzico_ref($pdo, $subRef);
    if (!$row && $custRef !== '') {
        $st = $pdo->prepare(
            "SELECT * FROM subscriptions WHERE iyzico_customer_ref = ? ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$custRef]);
        $found = $st->fetch();
        $row = $found ?: null;
    }
    if (!$row) {
        return;
    }

    if ($custRef !== '' && (string)$row['iyzico_customer_ref'] === '') {
        $pdo->prepare("UPDATE subscriptions SET iyzico_customer_ref = ? WHERE id = ?")
            ->execute([$custRef, (int)$row['id']]);
    }

    $success = str_contains($event, 'success') || str_contains($event, 'order.success');
    $failure = str_contains($event, 'fail');

    if ($success) {
        $fresh = false;
        if ($row['iyzico_subscription_ref'] !== '') {
            $det = iyzico_sub_detail((string)$row['iyzico_subscription_ref']);
            $fresh = $det['ok'];
            if ($fresh) {
                $inner = iyzico_v2_data($det['data']);
                subscription_mark_active($pdo, $row, $inner, (string)$row['interval_unit']);
            }
        }
        if (!$fresh) {
            subscription_mark_active($pdo, $row, $payload, (string)$row['interval_unit']);
        }
        subscription_record_invoice($pdo, (int)$row['id'], $orderRef, (int)$row['amount_kurus'], 'paid');
        subscription_notify_new($pdo, $row);
        return;
    }

    if ($failure) {
        $ref = trim((string)$row['iyzico_subscription_ref']);
        if ($ref !== '' && iyzico_ready()) {
            $det = iyzico_sub_detail($ref);
            if ($det['ok']) {
                $st = strtoupper((string)(iyzico_v2_data($det['data'])['subscriptionStatus'] ?? ''));
                if ($st === 'ACTIVE') {
                    return;
                }
            }
        }
        $pdo->prepare(
            "UPDATE subscriptions
             SET status = 'past_due', last_failure_at = NOW(), error_message = ?, updated_at = NOW()
             WHERE id = ? AND status IN ('active', 'past_due', 'pending')"
        )->execute(['Dönemsel çekim başarısız', (int)$row['id']]);
        subscription_record_invoice($pdo, (int)$row['id'], $orderRef, (int)$row['amount_kurus'], 'failed');
        subscription_notify_past_due($pdo, (int)$row['id']);
        return;
    }

    if (str_contains($event, 'cancel') || str_contains($event, 'expired')) {
        $new = str_contains($event, 'expired') ? 'expired' : 'cancelled';
        $pdo->prepare(
            "UPDATE subscriptions
             SET status = ?, cancelled_at = COALESCE(cancelled_at, NOW()), updated_at = NOW()
             WHERE id = ? AND status IN ('active', 'past_due', 'pending', 'cancelled')"
        )->execute([$new, (int)$row['id']]);
        if ($new === 'cancelled') {
            subscription_notify_cancelled($pdo, (int)$row['id']);
        }
    }
}

/**
 * Öğrenci iptali — gelecek çekimleri durdurur. İade yoktur.
 *
 * @return array{ok:bool, error:string}
 */
function subscription_cancel_for_student(PDO $pdo, int $studentId): array {
    $row = subscription_blocking_row($pdo, $studentId);
    if (!$row) {
        $cur = subscription_find_current($pdo, $studentId);
        if ($cur && $cur['status'] === 'cancelled') {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => 'İptal edilecek aktif abonelik yok.'];
    }
    if ($row['status'] === 'pending') {
        $pdo->prepare(
            "UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(), error_message = ?, updated_at = NOW() WHERE id = ?"
        )->execute(['Ödeme tamamlanmadan iptal', (int)$row['id']]);
        return ['ok' => true, 'error' => ''];
    }

    $ref = trim((string)$row['iyzico_subscription_ref']);
    if ($ref !== '' && iyzico_ready()) {
        $res = iyzico_sub_cancel($ref);
        if (!$res['ok']) {
            $msg = mb_strtolower($res['error']);
            $already = str_contains($msg, 'cancel') || str_contains($msg, 'iptal') || str_contains($msg, 'already');
            if (!$already) {
                return ['ok' => false, 'error' => 'İptal iyzico tarafında tamamlanamadı: ' . $res['error']];
            }
        }
    }

    $pdo->prepare(
        "UPDATE subscriptions
         SET status = 'cancelled', cancelled_at = NOW(), error_message = '', updated_at = NOW()
         WHERE id = ?"
    )->execute([(int)$row['id']]);
    subscription_notify_cancelled($pdo, (int)$row['id']);
    return ['ok' => true, 'error' => ''];
}

function subscription_instructor_id(PDO $pdo): int {
    return (int)subscription_setting($pdo, 'sub_instructor_id', '0');
}

function subscription_admin_list(PDO $pdo): array {
    subscription_expire_overdue($pdo);
    $rows = $pdo->query(
        "SELECT * FROM subscriptions ORDER BY FIELD(status, 'active', 'past_due', 'pending', 'cancelled', 'expired'), id DESC LIMIT 500"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $r['entitled'] = subscription_is_entitled($r);
        $r['status_label'] = subscription_status_label((string)$r['status'], $r['entitled']);
        $r['wa_digits'] = preg_replace('/\D/', '', (string)$r['student_phone']);
        $out[] = $r;
    }
    return $out;
}

function subscription_list_for_instructor(PDO $pdo, int $instructorId): array {
    subscription_expire_overdue($pdo);
    if ($instructorId <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        "SELECT * FROM subscriptions
         WHERE instructor_id = ?
         ORDER BY FIELD(status, 'active', 'past_due', 'pending', 'cancelled', 'expired'), id DESC
         LIMIT 500"
    );
    $st->execute([$instructorId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['entitled'] = subscription_is_entitled($r);
        $r['status_label'] = subscription_status_label((string)$r['status'], $r['entitled']);
        $r['wa_digits'] = preg_replace('/\D/', '', (string)$r['student_phone']);
        $out[] = $r;
    }
    return $out;
}

function subscription_notify_cancelled(PDO $pdo, int $id): void {
    if ($id <= 0) {
        return;
    }
    try {
        $lock = $pdo->prepare(
            "UPDATE subscriptions SET cancel_mail_at = NOW() WHERE id = ? AND cancel_mail_at IS NULL"
        );
        $lock->execute([$id]);
        if ($lock->rowCount() === 0) {
            return;
        }
        $st = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return;
        }
        require_once __DIR__ . '/mailer.php';
        mailer_notify_subscription_cancelled($row);
    } catch (Throwable $e) {
        error_log('abonelik iptal maili: ' . $e->getMessage());
    }
}

function subscription_notify_past_due(PDO $pdo, int $id): void {
    if ($id <= 0) {
        return;
    }
    try {
        $lock = $pdo->prepare(
            "UPDATE subscriptions SET past_due_mail_at = NOW()
             WHERE id = ? AND (past_due_mail_at IS NULL OR past_due_mail_at < (NOW() - INTERVAL 20 HOUR))"
        );
        $lock->execute([$id]);
        if ($lock->rowCount() === 0) {
            return;
        }
        $st = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return;
        }
        require_once __DIR__ . '/mailer.php';
        mailer_notify_subscription_past_due($row);
    } catch (Throwable $e) {
        error_log('abonelik past_due maili: ' . $e->getMessage());
    }
}
