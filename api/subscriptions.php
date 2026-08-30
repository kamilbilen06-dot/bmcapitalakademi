<?php
/**
 * WhatsApp grubu aboneliği — iş kuralları.
 *
 * Kartı biz saklamayız; iyzico Subscription ürünü çeker.
 * Kişi e-posta ile ayırt edilir (isim değil). Bir e-postaya en fazla bir açık abonelik.
 * Süre dönem saatinde kapanmaz: Türkiye 24:00’te iyzico’ya bakılır.
 * Çekildiyse aynı satır uzar (yeni abonelik açılmaz); çekilmediyse expired.
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

/**
 * Sandbox günlük planlarında dönem sonunu son başarılı çekimden üret.
 * iyzico'nun plan endDate değeri kayıtlar arasında farklı gün dönebiliyor;
 * site tarafında aynı ödeme kuralı kullanılmalı.
 */
function subscription_normalize_daily_periods(PDO $pdo, ?int $studentId = null): int {
    if (!iyzico_is_sandbox()) {
        return 0;
    }
    try {
        $sql = "SELECT id, last_paid_at, current_period_end
                FROM subscriptions
                WHERE status IN ('active', 'past_due')
                  AND interval_unit = 'DAILY'
                  AND last_paid_at IS NOT NULL
                  AND last_paid_at <> ''";
        $params = [];
        if ($studentId !== null && $studentId > 0) {
            $sql .= ' AND student_id = ?';
            $params[] = $studentId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $upd = $pdo->prepare(
            'UPDATE subscriptions SET current_period_end = ?, updated_at = NOW() WHERE id = ?'
        );
        $n = 0;
        foreach ($st->fetchAll() as $row) {
            $paidAt = trim((string) ($row['last_paid_at'] ?? ''));
            if ($paidAt === '' || strtotime($paidAt) === false) {
                continue;
            }
            $end = subscription_add_period('DAILY', $paidAt);
            if ((string) ($row['current_period_end'] ?? '') === $end) {
                continue;
            }
            $upd->execute([$end, (int) $row['id']]);
            $n++;
        }
        return $n;
    } catch (Throwable $e) {
        error_log('günlük abonelik süre eşitleme: ' . $e->getMessage());
        return 0;
    }
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
 * Dönem saati Türkiye takvim gününün dışına çıktıysa (o gün 24:00 geçtiyse) true.
 * Çekim iyzico’da dönem saatinden sonra gelir; gece yarısından önce süre doldurulmaz.
 */
function subscription_calendar_day_ended(string $periodEnd): bool {
    $endTs = strtotime($periodEnd);
    if ($endTs === false) {
        return false;
    }
    return date('Y-m-d', $endTs) < date('Y-m-d');
}

/**
 * Aktif / kart reddi: süre, iyzico kontrolünden sonra expired yazılana kadar açıktır.
 * İptal: dönem günü bitene kadar (Türkiye 24:00) grupta kalır.
 */
function subscription_is_entitled(array $row): bool {
    $st = (string)($row['status'] ?? '');
    $end = (string)($row['current_period_end'] ?? '');
    if ($st === 'active' || $st === 'past_due') {
        return true;
    }
    if ($st === 'cancelled' && $end !== '') {
        return !subscription_calendar_day_ended($end);
    }
    return false;
}

function subscription_iyzico_row_status(array $inner): string {
    return strtoupper(trim((string)($inner['subscriptionStatus'] ?? $inner['status'] ?? '')));
}

function subscription_mark_expired_row(PDO $pdo, int $id): bool {
    if ($id <= 0) {
        return false;
    }
    try {
        $st = $pdo->prepare(
            "UPDATE subscriptions
             SET status = 'expired', updated_at = NOW()
             WHERE id = ? AND status IN ('active', 'past_due')"
        );
        $st->execute([$id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function subscription_has_paid_invoice_on_day(PDO $pdo, int $subscriptionId, string $ymd): bool {
    if ($subscriptionId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return false;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM subscription_invoices
             WHERE subscription_id = ? AND status = 'paid' AND DATE(created_at) = ?
             LIMIT 1"
        );
        $st->execute([$subscriptionId, $ymd]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Yenilemede fatura satırı (aynı gün ikinci satır açılmaz).
 */
function subscription_record_renewal_invoice(PDO $pdo, array $row, array $inner): void {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $today = date('Y-m-d');
    if (subscription_has_paid_invoice_on_day($pdo, $id, $today)) {
        return;
    }
    $orderRef = trim((string)($inner['orderReferenceCode'] ?? $inner['parentReferenceCode'] ?? ''));
    if ($orderRef === '') {
        $orderRef = 'renew-' . $today . '-' . $id;
    }
    $payId = subscription_resolve_payment_id($row, $inner, [], 1);
    subscription_record_invoice($pdo, $id, $orderRef, (int)$row['amount_kurus'], 'paid', $payId);
}

/**
 * 24:00 sonrası iyzico: ACTIVE ise dönemi uzat (yeni satır yok), değilse expired.
 *
 * @return 'renewed'|'expired'|'kept'|'skipped'
 */
function subscription_sync_row_with_iyzico(PDO $pdo, array $row): string {
    $id = (int)($row['id'] ?? 0);
    $st = (string)($row['status'] ?? '');
    if ($id <= 0) {
        return 'skipped';
    }
    if (!in_array($st, ['active', 'past_due', 'expired'], true)) {
        return 'skipped';
    }

    $ref = trim((string)($row['iyzico_subscription_ref'] ?? ''));
    if ($ref === '') {
        if (in_array($st, ['active', 'past_due'], true) && subscription_calendar_day_ended((string)($row['current_period_end'] ?? ''))) {
            return subscription_mark_expired_row($pdo, $id) ? 'expired' : 'skipped';
        }
        return 'skipped';
    }
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return 'skipped';
    }

    $det = iyzico_sub_detail($ref);
    if (empty($det['ok'])) {
        error_log('abonelik iyzico kontrol: ' . (string)($det['error'] ?? ''));
        return 'skipped';
    }

    $inner = iyzico_v2_data($det['data'] ?? []);
    if (subscription_iyzico_row_status($inner) === 'ACTIVE') {
        $interval = (string)($row['interval_unit'] ?: subscription_interval());
        $endRaw = (string)($inner['endDate'] ?? $inner['subscriptionEndDate'] ?? $inner['nextPaymentDate'] ?? '');
        $end = site_from_iyzico_dt($endRaw);
        if ($end === '' || subscription_calendar_day_ended($end)) {
            $inner['endDate'] = subscription_add_period($interval, site_now());
        }
        subscription_mark_active($pdo, $row, $inner, $interval, site_now());
        subscription_record_renewal_invoice($pdo, $row, $inner);
        return 'renewed';
    }

    if (in_array($st, ['active', 'past_due'], true)) {
        return subscription_mark_expired_row($pdo, $id) ? 'expired' : 'skipped';
    }
    subscription_touch($pdo, $id);
    return 'skipped';
}

/**
 * Yanlışlıkla süresi doldurulmuş (iyzico hâlâ ACTIVE) kaydı geri alır.
 *
 * @return 'renewed'|'skipped'
 */
function subscription_revive_expired_if_iyzico_active(PDO $pdo, array $row): string {
    if ((string)($row['status'] ?? '') !== 'expired') {
        return 'skipped';
    }
    $got = subscription_sync_row_with_iyzico($pdo, $row);
    return $got === 'renewed' ? 'renewed' : 'skipped';
}

/**
 * Gece 24:00 (Türkiye) geçmeden süre doldurma.
 * Sonra iyzico’ya sor: çekildiyse / ACTIVE ise aynı satırı uzat; değilse expired.
 *
 * @return array{expired:int, renewed:int}
 */
function subscription_reconcile_due_periods(PDO $pdo, ?int $studentId = null): array {
    $out = ['expired' => 0, 'renewed' => 0];
    $limit = $studentId !== null && $studentId > 0 ? 8 : 40;
    $calls = 0;
    subscription_normalize_daily_periods($pdo, $studentId);

    $fetch = static function (PDO $pdo, string $sql, array $params) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    };

    try {
        $params = [];
        $studentSql = '';
        if ($studentId !== null && $studentId > 0) {
            $studentSql = ' AND student_id = ?';
            $params[] = $studentId;
        }

        $reviveSql = "SELECT * FROM subscriptions
             WHERE status = 'expired'
               AND iyzico_subscription_ref <> ''
               AND updated_at < (NOW() - INTERVAL 15 MINUTE)
               AND (
                 DATE(updated_at) >= (CURDATE() - INTERVAL 1 DAY)
                 OR DATE(current_period_end) >= (CURDATE() - INTERVAL 2 DAY)
               )
               $studentSql
             ORDER BY id DESC
             LIMIT 25";
        foreach ($fetch($pdo, $reviveSql, $params) as $row) {
            if ($calls >= $limit) {
                break;
            }
            $calls++;
            if (subscription_revive_expired_if_iyzico_active($pdo, $row) === 'renewed') {
                $out['renewed']++;
            }
        }

        $dueSql = "SELECT * FROM subscriptions
             WHERE status IN ('active', 'past_due')
               AND current_period_end IS NOT NULL
               AND DATE(current_period_end) < CURDATE()
               $studentSql
             ORDER BY id ASC
             LIMIT 40";
        foreach ($fetch($pdo, $dueSql, $params) as $row) {
            if ($calls >= $limit) {
                break;
            }
            $calls++;
            $got = subscription_sync_row_with_iyzico($pdo, $row);
            if ($got === 'renewed') {
                $out['renewed']++;
            } elseif ($got === 'expired') {
                $out['expired']++;
            }
        }
    } catch (Throwable $e) {
        error_log('abonelik gece esitligi: ' . $e->getMessage());
    }

    return $out;
}

/** Cron / panel: süresi dolacakları iyzico ile eşitle. Dönüş: expired sayısı. */
function subscription_expire_overdue(PDO $pdo, ?int $studentId = null): int {
    return subscription_reconcile_due_periods($pdo, $studentId)['expired'];
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

function subscription_norm_email(string $email): string {
    return mb_strtolower(trim($email));
}

function subscription_norm_person_name(string $name): string {
    $n = trim((string) preg_replace('/\s+/u', ' ', $name));
    return mb_strtolower($n, 'UTF-8');
}

/**
 * @param list<array<string,mixed>> $subs
 * @return array{byEmail: array<string, list<array<string,mixed>>>, byConv: array<string, array<string,mixed>>, byRef: array<string, array<string,mixed>>, uniqueName: array<string, array<string,mixed>>}
 */
function subscription_buyer_indexes(array $subs): array {
    $byEmail = [];
    $byConv = [];
    $byRef = [];
    $nameEmails = [];
    foreach ($subs as $row) {
        $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
        $conv = trim((string) ($row['conversation_id'] ?? ''));
        if ($ref !== '') {
            $byRef[$ref] = $row;
        }
        if ($conv !== '') {
            $byConv[$conv] = $row;
        }
        $em = subscription_norm_email((string) ($row['student_email'] ?? ''));
        if ($em !== '') {
            $byEmail[$em][] = $row;
        }
        $nm = subscription_norm_person_name((string) ($row['student_name'] ?? ''));
        if ($nm !== '' && $em !== '') {
            $nameEmails[$nm][$em] = true;
        }
    }
    $uniqueName = [];
    foreach ($nameEmails as $nm => $emails) {
        if (count($emails) !== 1) {
            continue;
        }
        $em = (string) array_key_first($emails);
        $cands = subscription_canonical_rows($byEmail[$em] ?? []);
        if ($cands !== []) {
            $uniqueName[$nm] = $cands[0];
        }
    }
    return [
        'byEmail' => $byEmail,
        'byConv' => $byConv,
        'byRef' => $byRef,
        'uniqueName' => $uniqueName,
    ];
}

function subscription_nested_email(array $data, int $depth = 0): string {
    if ($depth > 5) {
        return '';
    }
    foreach (['email', 'buyerEmail', 'customerEmail', 'emailAddress'] as $k) {
        if (!isset($data[$k]) || !is_string($data[$k])) {
            continue;
        }
        $em = subscription_norm_email($data[$k]);
        if ($em !== '' && str_contains($em, '@')) {
            return $em;
        }
    }
    foreach (['buyer', 'customer', 'billingAddress'] as $k) {
        if (isset($data[$k]) && is_array($data[$k])) {
            $em = subscription_nested_email($data[$k], $depth + 1);
            if ($em !== '') {
                return $em;
            }
        }
    }
    foreach ($data as $v) {
        if (is_array($v)) {
            $em = subscription_nested_email($v, $depth + 1);
            if ($em !== '') {
                return $em;
            }
        }
    }
    return '';
}

/** iyzico işlem satırını sitedeki aboneye bağla: e-posta / tekil isim, sonra referans. */
function subscription_match_sub_for_tx(
    array $t,
    array $byRef,
    array $byConv,
    array $byEmail,
    array $uniqueName
): ?array {
    $email = subscription_norm_email((string) ($t['buyerEmail'] ?? $t['email'] ?? $t['customerEmail'] ?? ''));
    if ($email !== '' && isset($byEmail[$email])) {
        $cands = subscription_canonical_rows($byEmail[$email]);
        return $cands[0] ?? null;
    }
    $name = subscription_norm_person_name(subscription_tx_buyer_name($t));
    if ($name !== '' && isset($uniqueName[$name])) {
        return $uniqueName[$name];
    }
    foreach (['conversationId', 'paymentConversationId', 'subscriptionReferenceCode'] as $k) {
        $c = trim((string) ($t[$k] ?? ''));
        if ($c !== '' && isset($byRef[$c])) {
            return $byRef[$c];
        }
        if ($c !== '' && isset($byConv[$c])) {
            return $byConv[$c];
        }
    }
    return null;
}

function subscription_student_email(PDO $pdo, int $studentId): string {
    if ($studentId <= 0) {
        return '';
    }
    try {
        $st = $pdo->prepare("SELECT email FROM students WHERE id = ? LIMIT 1");
        $st->execute([$studentId]);
        return subscription_norm_email((string) ($st->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function subscription_find_current(PDO $pdo, int $studentId, string $email = ''): ?array {
    subscription_reconcile_due_periods($pdo, $studentId);
    $email = subscription_norm_email($email);
    if ($email === '') {
        $email = subscription_student_email($pdo, $studentId);
    }
    $openSql = "status IN ('active', 'past_due')
             OR (status = 'cancelled'
                 AND current_period_end IS NOT NULL
                 AND DATE(current_period_end) >= CURDATE())";
    try {
        if ($email !== '') {
            $st = $pdo->prepare(
                "SELECT * FROM subscriptions
                 WHERE LOWER(student_email) = ? AND ($openSql)
                 ORDER BY FIELD(status, 'active', 'past_due', 'cancelled'), last_paid_at DESC, id DESC
                 LIMIT 1"
            );
            $st->execute([$email]);
            $row = $st->fetch();
            if ($row) {
                return $row;
            }
        }
        if ($studentId > 0) {
            $st = $pdo->prepare(
                "SELECT * FROM subscriptions
                 WHERE student_id = ? AND ($openSql)
                 ORDER BY FIELD(status, 'active', 'past_due', 'cancelled'), id DESC
                 LIMIT 1"
            );
            $st->execute([$studentId]);
            $row = $st->fetch();
            return $row ?: null;
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

/**
 * Ödeme sayfasında bırakılmış / iptal edilmiş denemeyi kapat.
 *
 * iyzico referansı yok ve hiç tahsilat yoksa bu bir abonelik değildir;
 * Aboneliklerim'de "Ödeme bekliyor" görünmesin, yeni deneme de engellenmesin.
 */
function subscription_abandon_unpaid_pending(PDO $pdo, int $studentId, string $email = ''): int {
    $email = subscription_norm_email($email);
    if ($email === '' && $studentId > 0) {
        $email = subscription_student_email($pdo, $studentId);
    }
    if ($studentId <= 0 && $email === '') {
        return 0;
    }
    try {
        $sql = "UPDATE subscriptions
             SET status = 'cancelled', cancelled_at = NOW(),
                 error_message = 'Ödeme tamamlanmadı', updated_at = NOW()
             WHERE status = 'pending'
               AND iyzico_subscription_ref = ''
               AND last_paid_at IS NULL";
        $params = [];
        if ($studentId > 0 && $email !== '') {
            $sql .= " AND (student_id = ? OR LOWER(student_email) = ?)";
            $params = [$studentId, $email];
        } elseif ($email !== '') {
            $sql .= " AND LOWER(student_email) = ?";
            $params = [$email];
        } else {
            $sql .= " AND student_id = ?";
            $params = [$studentId];
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function subscription_blocking_row(PDO $pdo, int $studentId, string $email = ''): ?array {
    $email = subscription_norm_email($email);
    if ($email === '') {
        $email = subscription_student_email($pdo, $studentId);
    }
    try {
        if ($email !== '') {
            $st = $pdo->prepare(
                "SELECT * FROM subscriptions
                 WHERE LOWER(student_email) = ? AND status IN ('active', 'past_due', 'pending')
                 ORDER BY FIELD(status, 'active', 'past_due', 'pending'), last_paid_at DESC, id DESC
                 LIMIT 1"
            );
            $st->execute([$email]);
            $row = $st->fetch();
            if ($row) {
                return $row;
            }
        }
        if ($studentId > 0) {
            $st = $pdo->prepare(
                "SELECT * FROM subscriptions
                 WHERE student_id = ? AND status IN ('active', 'past_due', 'pending')
                 ORDER BY id DESC LIMIT 1"
            );
            $st->execute([$studentId]);
            $row = $st->fetch();
            return $row ?: null;
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

function subscription_rows_for_identity(PDO $pdo, int $studentId, string $email): array {
    $email = subscription_norm_email($email);
    if ($email === '' && $studentId > 0) {
        $email = subscription_student_email($pdo, $studentId);
    }
    if ($email === '') {
        return [];
    }
    try {
        $st = $pdo->prepare(
            "SELECT * FROM subscriptions WHERE LOWER(student_email) = ? ORDER BY id DESC LIMIT 50"
        );
        $st->execute([$email]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Aynı e-postadaki fazla satırlardan duran / yeni olanı seç. */
function subscription_row_is_better(array $a, array $b): bool {
    $rank = static function (array $r): int {
        $st = (string) ($r['status'] ?? '');
        if ($st === 'active' || $st === 'past_due') {
            return 3;
        }
        if ($st === 'expired') {
            return 2;
        }
        if ($st === 'pending') {
            return 1;
        }
        return 0;
    };
    $c = $rank($a) <=> $rank($b);
    if ($c !== 0) {
        return $c > 0;
    }
    $pa = (string) ($a['last_paid_at'] ?? '');
    $pb = (string) ($b['last_paid_at'] ?? '');
    if ($pa !== $pb) {
        return $pa > $pb;
    }
    return (int) ($a['id'] ?? 0) > (int) ($b['id'] ?? 0);
}

/**
 * Aynı e-postadaki fazla satırdan birini seçer (isim kullanılmaz).
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function subscription_canonical_rows(array $rows): array {
    $best = [];
    foreach ($rows as $row) {
        $em = subscription_norm_email((string) ($row['student_email'] ?? ''));
        $key = $em !== '' ? 'e:' . $em : 'id:' . (int) ($row['id'] ?? 0);
        if (!isset($best[$key]) || subscription_row_is_better($row, $best[$key])) {
            $best[$key] = $row;
        }
    }
    return array_values($best);
}

function subscription_cancel_iyzico_ref(string $ref): bool {
    $ref = trim($ref);
    if ($ref === '' || !function_exists('iyzico_ready') || !iyzico_ready()) {
        return true;
    }
    $res = iyzico_sub_cancel($ref);
    if (!empty($res['ok'])) {
        return true;
    }
    $msg = mb_strtolower((string) ($res['error'] ?? ''));
    foreach (['cancel', 'iptal', 'already', 'not active', 'aktif değil', 'expired', 'unpaid'] as $n) {
        if (str_contains($msg, $n)) {
            return true;
        }
    }
    error_log('iyzico mukerrer iptal: ' . (string) ($res['error'] ?? ''));
    return false;
}

function subscription_mark_duplicate_closed(PDO $pdo, array $row): void {
    try {
        $pdo->prepare(
            "UPDATE subscriptions
             SET status = 'cancelled',
                 cancelled_at = COALESCE(cancelled_at, NOW()),
                 error_message = 'Mükerrer abonelik kapatıldı',
                 updated_at = NOW()
             WHERE id = ?"
        )->execute([(int) $row['id']]);
    } catch (Throwable $e) {
    }
}

/**
 * Aynı öğrenci / e-postada iyzico'da ACTIVE kalan kaydı siteye alır.
 * Yeni checkout açılmasın diye checkout öncesi çağrılır.
 */
function subscription_adopt_live_iyzico(PDO $pdo, int $studentId, string $email): ?array {
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return null;
    }
    $rows = subscription_rows_for_identity($pdo, $studentId, $email);
    $calls = 0;
    foreach ($rows as $row) {
        $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
        if ($ref === '') {
            continue;
        }
        if ($calls >= 8) {
            break;
        }
        $calls++;
        $det = iyzico_sub_detail($ref);
        if (empty($det['ok'])) {
            continue;
        }
        $inner = iyzico_v2_data($det['data'] ?? []);
        if (subscription_iyzico_row_status($inner) !== 'ACTIVE') {
            continue;
        }
        $interval = (string) ($row['interval_unit'] ?: subscription_interval());
        subscription_mark_active($pdo, $row, $inner, $interval);
        return subscription_find_by_id($pdo, (int) $row['id']);
    }
    return null;
}

/**
 * Aynı kişiye ait fazla iyzico aboneliklerini kapatır; sitede bir satır kalır.
 * $keepId > 0 ise o satır korunur. $keepId === -1 ise hepsi kapatılır (öğrenci iptali).
 *
 * @return int kapatılan satır
 */
function subscription_collapse_duplicates_for_identity(
    PDO $pdo,
    int $studentId,
    string $email,
    int $keepId = 0
): int {
    $rows = subscription_rows_for_identity($pdo, $studentId, $email);
    if ($rows === []) {
        return 0;
    }

    $liveIds = [];
    $calls = 0;
    $innerById = [];
    if (function_exists('iyzico_ready') && iyzico_ready()) {
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
            if ($ref === '' || $calls >= 10) {
                continue;
            }
            $calls++;
            $det = iyzico_sub_detail($ref);
            if (empty($det['ok'])) {
                continue;
            }
            $inner = iyzico_v2_data($det['data'] ?? []);
            if (subscription_iyzico_row_status($inner) === 'ACTIVE') {
                $liveIds[$id] = true;
                $innerById[$id] = $inner;
            }
        }
    }

    if ($keepId === -1) {
        $n = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
            if (isset($liveIds[$id]) && $ref !== '') {
                if (!subscription_cancel_iyzico_ref($ref)) {
                    continue;
                }
            }
            $st = (string) ($row['status'] ?? '');
            if (isset($liveIds[$id]) || in_array($st, ['active', 'past_due', 'pending'], true)) {
                subscription_mark_duplicate_closed($pdo, $row);
                $n++;
            }
        }
        return $n;
    }

    if ($keepId <= 0) {
        $keep = null;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($liveIds[$id]) && !in_array((string) $row['status'], ['active', 'past_due'], true)) {
                continue;
            }
            if ($keep === null) {
                $keep = $row;
                continue;
            }
            $aLive = isset($liveIds[$id]);
            $bLive = isset($liveIds[(int) $keep['id']]);
            if ($aLive !== $bLive) {
                if ($aLive) {
                    $keep = $row;
                }
                continue;
            }
            if (subscription_row_is_better($row, $keep)) {
                $keep = $row;
            }
        }
        $keepId = $keep ? (int) $keep['id'] : 0;
    }
    if ($keepId <= 0) {
        return 0;
    }

    $n = 0;
    $keepRow = null;
    foreach ($rows as $row) {
        if ((int) $row['id'] === $keepId) {
            $keepRow = $row;
            break;
        }
    }
    if (
        $keepRow
        && isset($innerById[$keepId])
        && in_array((string) ($keepRow['status'] ?? ''), ['active', 'past_due', 'expired', 'pending'], true)
    ) {
        $interval = (string) ($keepRow['interval_unit'] ?: subscription_interval());
        subscription_mark_active($pdo, $keepRow, $innerById[$keepId], $interval);
        $keepRow = subscription_find_by_id($pdo, $keepId) ?: $keepRow;
    }

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if ($id === $keepId) {
            continue;
        }
        $st = (string) ($row['status'] ?? '');
        $live = isset($liveIds[$id]);
        $siteOpen = in_array($st, ['active', 'past_due', 'pending'], true);
        if (!$live && !$siteOpen) {
            continue;
        }
        $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
        if ($live && $ref !== '' && !subscription_cancel_iyzico_ref($ref)) {
            continue;
        }
        if ((int) ($row['wa_added'] ?? 0) === 1 && $keepRow && (int) ($keepRow['wa_added'] ?? 0) === 0) {
            try {
                $pdo->prepare("UPDATE subscriptions SET wa_added = 1 WHERE id = ?")->execute([$keepId]);
                $keepRow['wa_added'] = 1;
            } catch (Throwable $e) {
            }
        }
        subscription_mark_duplicate_closed($pdo, $row);
        $n++;
    }
    return $n;
}

/** Aynı e-postada birden fazla açık abonelik varsa fazlasını kapatır. */
function subscription_collapse_duplicate_actives(PDO $pdo): int {
    $n = 0;
    $seen = [];
    try {
        foreach ($pdo->query(
            "SELECT LOWER(student_email) e FROM subscriptions
             WHERE student_email <> '' AND status IN ('active', 'past_due')
             GROUP BY LOWER(student_email) HAVING COUNT(*) > 1
             LIMIT 40"
        ) ?: [] as $r) {
            $e = (string) $r['e'];
            if ($e === '' || isset($seen[$e])) {
                continue;
            }
            $seen[$e] = true;
            $n += subscription_collapse_duplicates_for_identity($pdo, 0, $e);
        }
    } catch (Throwable $e) {
        error_log('mukerrer abonelik: ' . $e->getMessage());
    }
    return $n;
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

function subscription_mark_active(
    PDO $pdo,
    array $row,
    array $inner,
    string $interval,
    string $periodBase = ''
): void {
    $id = (int)$row['id'];
    $ref = (string)($inner['subscriptionReferenceCode'] ?? $inner['referenceCode'] ?? $row['iyzico_subscription_ref']);
    $cust = (string)($inner['customerReferenceCode'] ?? $row['iyzico_customer_ref']);
    $end = '';
    if (iyzico_is_sandbox() && strtoupper($interval) === 'DAILY') {
        $base = trim($periodBase);
        if ($base === '') {
            $base = trim((string)($row['last_paid_at'] ?? ''));
        }
        if ($base === '') {
            $base = site_from_iyzico_dt((string)($inner['createdDate'] ?? ''));
        }
        if ($base === '') {
            $base = site_now();
        }
        $end = subscription_add_period('DAILY', $base);
    } else {
        $endRaw = (string)($inner['endDate'] ?? $inner['subscriptionEndDate'] ?? $inner['nextPaymentDate'] ?? '');
        $end = site_from_iyzico_dt($endRaw);
        if ($end === '') {
            $base = (string)($row['current_period_end'] ?? '');
            if ($base !== '' && strtotime($base) > time()) {
                $end = subscription_add_period($interval, $base);
            } else {
                $end = subscription_add_period($interval, site_now());
            }
        }
    }
    $pdo->prepare(
        "UPDATE subscriptions
         SET status = 'active',
             iyzico_subscription_ref = ?,
             iyzico_customer_ref = ?,
             current_period_end = ?,
             last_paid_at = ?,
             error_message = '',
             cancelled_at = NULL,
             updated_at = ?
         WHERE id = ?"
    )->execute([$ref, $cust, $end, site_now(), site_now(), $id]);
}

/**
 * iyzico ödeme numarasını iç yanıt, webhook veya rapor API'sinden bul.
 *
 * @param array<string,mixed> $row
 * @param array<string,mixed> $inner
 * @param array<string,mixed> $payload
 */
function subscription_resolve_payment_id(array $row, array $inner = [], array $payload = [], int $maxLookups = 2): string {
    foreach ([$payload, $inner] as $src) {
        if ($src !== []) {
            $id = iyzico_extract_payment_id($src);
            if ($id !== '') {
                return $id;
            }
        }
    }
    $seen = [];
    $lookups = 0;
    foreach ([
        $payload['iyziPaymentId'] ?? '',
        $payload['paymentId'] ?? '',
        $payload['orderReferenceCode'] ?? '',
        $inner['orderReferenceCode'] ?? '',
        $row['iyzico_subscription_ref'] ?? '',
        $inner['subscriptionReferenceCode'] ?? '',
        $payload['subscriptionReferenceCode'] ?? '',
        $payload['iyziReferenceCode'] ?? '',
        $row['conversation_id'] ?? '',
        $inner['parentReferenceCode'] ?? '',
    ] as $cand) {
        $cand = trim((string)$cand);
        if ($cand === '' || isset($seen[$cand])) {
            continue;
        }
        $seen[$cand] = true;
        $direct = iyzico_normalize_payment_id($cand);
        if ($direct !== '') {
            return $direct;
        }
        if ($lookups >= $maxLookups) {
            continue;
        }
        $lookups++;
        $id = iyzico_find_payment_id_by_conversation($cand);
        if ($id !== '') {
            return $id;
        }
    }
    return '';
}

function subscription_record_invoice(
    PDO $pdo,
    int $subscriptionId,
    string $orderRef,
    int $amountKurus,
    string $status,
    string $paymentId = '',
    string $createdAt = ''
): bool {
    $orderRef = mb_substr(trim($orderRef), 0, 80);
    $paymentId = iyzico_normalize_payment_id($paymentId);
    if ($orderRef === '') {
        $orderRef = 'inv-' . $subscriptionId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));
    }
    $at = $createdAt !== '' ? $createdAt : site_now();
    try {
        $pdo->prepare(
            "INSERT INTO subscription_invoices
                (subscription_id, order_reference, provider_payment_id, amount_kurus, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                provider_payment_id = IF(VALUES(provider_payment_id) = '', provider_payment_id, VALUES(provider_payment_id))"
        )->execute([$subscriptionId, $orderRef, $paymentId, $amountKurus, $status, $at]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function subscription_fill_invoice_payment_id(PDO $pdo, array $inv): string {
    $have = iyzico_normalize_payment_id($inv['provider_payment_id'] ?? '');
    if ($have !== '') {
        return $have;
    }
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return '';
    }
    $id = subscription_resolve_payment_id(
        [
            'conversation_id' => (string)($inv['conversation_id'] ?? ''),
            'iyzico_subscription_ref' => (string)($inv['iyzico_subscription_ref'] ?? ''),
        ],
        [],
        ['orderReferenceCode' => (string)($inv['order_reference'] ?? '')]
    );
    $invId = (int)($inv['id'] ?? 0);
    if ($id !== '' && $invId > 0) {
        try {
            $pdo->prepare(
                "UPDATE subscription_invoices SET provider_payment_id = ? WHERE id = ? AND provider_payment_id = ''"
            )->execute([$id, $invId]);
        } catch (Throwable $e) {
        }
    }
    return $id;
}

/**
 * Ödemeler listesindeki boş abonelik ödeme nolarını iyzico İşlemler'den doldur.
 * Gün raporunu tarihe göre bir kez çeker, conversation / tutar ile eşler.
 *
 * @param array<int, array<string,mixed>> $invoices
 * @return array<int, string> invoice id => payment id
 */
function subscription_backfill_missing_payment_ids(PDO $pdo, array $invoices): array {
    $filled = [];
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return $filled;
    }
    $need = [];
    foreach ($invoices as $inv) {
        if (iyzico_normalize_payment_id($inv['provider_payment_id'] ?? '') !== '') {
            continue;
        }
        if ((string)($inv['status'] ?? '') !== 'paid') {
            continue;
        }
        $need[] = $inv;
    }
    if ($need === []) {
        return $filled;
    }

    $used = [];
    try {
        foreach ($pdo->query("SELECT provider_payment_id FROM subscription_invoices WHERE provider_payment_id <> ''") ?: [] as $r) {
            $id = iyzico_normalize_payment_id($r['provider_payment_id'] ?? '');
            if ($id !== '') {
                $used[$id] = true;
            }
        }
        foreach ($pdo->query("SELECT provider_payment_id FROM payment_orders WHERE provider_payment_id <> ''") ?: [] as $r) {
            $id = iyzico_normalize_payment_id($r['provider_payment_id'] ?? '');
            if ($id !== '') {
                $used[$id] = true;
            }
        }
    } catch (Throwable $e) {
    }

    $dates = [];
    foreach ($need as $inv) {
        $d = substr((string)($inv['created_at'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            continue;
        }
        $dates[$d] = true;
        $ts = strtotime($d . ' 12:00:00');
        if ($ts) {
            $dates[date('Y-m-d', $ts - 86400)] = true;
            $dates[date('Y-m-d', $ts + 86400)] = true;
        }
    }

    $byConv = [];
    $byAmountDay = [];
    foreach (array_keys($dates) as $ymd) {
        foreach (iyzico_payment_transactions_by_date($ymd) as $t) {
            $type = strtoupper((string)($t['transactionType'] ?? 'PAYMENT'));
            if ($type !== '' && $type !== 'PAYMENT') {
                continue;
            }
            $pid = iyzico_normalize_payment_id($t['paymentId'] ?? '');
            if ($pid === '' || isset($used[$pid])) {
                continue;
            }
            foreach (['conversationId', 'paymentConversationId'] as $k) {
                $c = trim((string)($t[$k] ?? ''));
                if ($c !== '') {
                    $byConv[$c] = $pid;
                }
            }
            $kurus = (int)round(((float)($t['paidPrice'] ?? $t['price'] ?? 0)) * 100);
            $day = substr((string)($t['transactionDate'] ?? $ymd), 0, 10);
            if ($kurus > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $byAmountDay[$day . ':' . $kurus][] = $pid;
            }
        }
    }

    $upd = $pdo->prepare(
        "UPDATE subscription_invoices SET provider_payment_id = ? WHERE id = ? AND provider_payment_id = ''"
    );
    foreach ($need as $inv) {
        $invId = (int)($inv['id'] ?? 0);
        $pid = '';
        foreach ([
            $inv['iyzico_subscription_ref'] ?? '',
            $inv['order_reference'] ?? '',
            $inv['conversation_id'] ?? '',
        ] as $c) {
            $c = trim((string)$c);
            if ($c !== '' && isset($byConv[$c]) && !isset($used[$byConv[$c]])) {
                $pid = $byConv[$c];
                break;
            }
        }
        if ($pid === '') {
            $day = substr((string)($inv['created_at'] ?? ''), 0, 10);
            $kurus = (int)($inv['amount_kurus'] ?? 0);
            $pool = [];
            $days = [$day];
            $ts = strtotime($day . ' 12:00:00');
            if ($ts) {
                $days[] = date('Y-m-d', $ts - 86400);
                $days[] = date('Y-m-d', $ts + 86400);
            }
            foreach ($days as $d) {
                foreach ($byAmountDay[$d . ':' . $kurus] ?? [] as $p) {
                    if (!isset($used[$p])) {
                        $pool[$p] = true;
                    }
                }
            }
            $cands = array_keys($pool);
            if (count($cands) === 1) {
                $pid = $cands[0];
            }
        }
        if ($pid === '' || $invId <= 0 || isset($used[$pid])) {
            continue;
        }
        try {
            $upd->execute([$pid, $invId]);
            $used[$pid] = true;
            $filled[$invId] = $pid;
        } catch (Throwable $e) {
        }
    }
    return $filled;
}

function subscription_tx_paid_at(array $t, string $ymd): string {
    $raw = trim((string) ($t['transactionDate'] ?? $t['createdDate'] ?? ''));
    $converted = site_from_iyzico_dt($raw);
    if ($converted !== '') {
        return $converted;
    }
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}:\d{2}:\d{2}))?/', $raw, $m)) {
        $h = $m[4] !== '' ? $m[4] : '12:00:00';
        return $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $h;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) ? $ymd . ' 12:00:00' : site_now();
}

function subscription_tx_buyer_name(array $t): string {
    $n = trim((string) ($t['buyerName'] ?? $t['name'] ?? $t['customerName'] ?? $t['firstName'] ?? $t['memberName'] ?? ''));
    $s = trim((string) ($t['buyerSurname'] ?? $t['surname'] ?? $t['customerSurname'] ?? $t['lastName'] ?? ''));
    $full = trim($n . ' ' . $s);
    if ($full !== '') {
        return $full;
    }
    $one = trim((string) ($t['cardHolderName'] ?? $t['buyerFullName'] ?? $t['fullName'] ?? ''));
    return $one;
}

/**
 * iyzico İşlemler listesi: ödeme no => ad, saat, iade.
 *
 * @return array<string, array{name:string,email:string,paidAt:string,refunded:bool}>
 */
function iyzico_tx_index_by_dates(string $from, string $to): array {
    $out = [];
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return $out;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return $out;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $ts = strtotime($from . ' 12:00:00');
    $end = strtotime($to . ' 12:00:00');
    if (!$ts || !$end) {
        return $out;
    }
    for ($i = 0; $i < 10 && $end >= $ts; $i++, $end -= 86400) {
        $ymd = date('Y-m-d', $end);
        foreach (iyzico_payment_transactions_by_date($ymd) as $t) {
            $pid = iyzico_normalize_payment_id($t['paymentId'] ?? '');
            if ($pid === '') {
                continue;
            }
            $type = strtoupper((string) ($t['transactionType'] ?? 'PAYMENT'));
            $refunded = str_contains($type, 'REFUND')
                || str_contains(strtoupper((string) ($t['paymentRefundStatus'] ?? $t['refundStatus'] ?? '')), 'REFUND');
            $name = subscription_tx_buyer_name($t);
            $email = subscription_norm_email((string) ($t['buyerEmail'] ?? $t['email'] ?? $t['customerEmail'] ?? ''));
            $paidAt = subscription_tx_paid_at($t, $ymd);
            if (!isset($out[$pid])) {
                $out[$pid] = [
                    'name' => $name,
                    'email' => $email,
                    'paidAt' => $paidAt,
                    'refunded' => $refunded,
                ];
            } else {
                if ($name !== '' && $out[$pid]['name'] === '') {
                    $out[$pid]['name'] = $name;
                }
                if ($email !== '' && $out[$pid]['email'] === '') {
                    $out[$pid]['email'] = $email;
                }
                if ($paidAt !== '' && ($out[$pid]['paidAt'] === '' || $paidAt < $out[$pid]['paidAt'])) {
                    $out[$pid]['paidAt'] = $paidAt;
                }
                if ($refunded) {
                    $out[$pid]['refunded'] = true;
                }
            }
        }
    }
    return $out;
}

function subscription_buyer_from_iyzico_sub(array $item): array {
    $email = subscription_norm_email((string) ($item['customerEmail'] ?? ''));
    if ($email === '') {
        $email = subscription_nested_email($item);
    }
    $name = subscription_tx_buyer_name($item);
    $cust = $item['customer'] ?? null;
    if (is_array($cust)) {
        $cn = trim((string) ($cust['name'] ?? '') . ' ' . (string) ($cust['surname'] ?? $cust['lastName'] ?? ''));
        if ($cn !== '') {
            $name = $cn;
        }
        if ($email === '') {
            $email = subscription_norm_email((string) ($cust['email'] ?? ''));
        }
    }
    return [
        'name' => trim($name),
        'email' => $email,
        'ref' => trim((string) ($item['referenceCode'] ?? $item['subscriptionReferenceCode'] ?? '')),
    ];
}

/**
 * iyzico abonelik siparişlerinden ödeme no => alıcı (ad / e-posta).
 * Raporlama listesinde alıcı adı yoktur; paneldeki isim buradan gelir.
 *
 * @return array<string, array{name:string,email:string,ref:string}>
 */
function iyzico_payment_buyer_map(float $deadline = 0.0, ?array $replace = null): array {
    static $cache = null;
    if ($replace !== null) {
        $cache = $replace;
        return $cache;
    }
    if (is_array($cache)) {
        return $cache;
    }
    $out = [];
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        $cache = $out;
        return $out;
    }
    $absorb = static function (array $item) use (&$out): void {
        $buyer = subscription_buyer_from_iyzico_sub($item);
        foreach (iyzico_collect_payment_ids($item) as $pid) {
            if (!isset($out[$pid])) {
                $out[$pid] = $buyer;
                continue;
            }
            if ($buyer['name'] !== '' && $out[$pid]['name'] === '') {
                $out[$pid]['name'] = $buyer['name'];
            }
            if ($buyer['email'] !== '' && $out[$pid]['email'] === '') {
                $out[$pid]['email'] = $buyer['email'];
            }
        }
    };

    $pageCount = 1;
    for ($page = 1; $page <= 8; $page++) {
        if ($deadline > 0 && microtime(true) >= $deadline) {
            break;
        }
        $res = iyzico_sub_search($page, 50);
        if (empty($res['ok'])) {
            break;
        }
        $inner = iyzico_v2_data($res['data'] ?? []);
        $items = $inner['items'] ?? [];
        if (!is_array($items) || $items === []) {
            break;
        }
        foreach ($items as $item) {
            if (is_array($item)) {
                $absorb($item);
            }
        }
        $pageCount = max(1, (int) ($inner['pageCount'] ?? 1));
        if ($page >= $pageCount) {
            break;
        }
    }

    $cache = $out;
    return $out;
}

/**
 * Yerel abonelik referanslarından eksik ödeme no eşlemesini tamamlar.
 *
 * @param array<string, array{name:string,email:string,ref:string}> $map
 * @return array<string, array{name:string,email:string,ref:string}>
 */
function iyzico_payment_buyer_map_fill_refs(array $map, array $subs, float $deadline = 0.0): array {
    $seen = [];
    foreach ($subs as $row) {
        if ($deadline > 0 && microtime(true) >= $deadline) {
            break;
        }
        $ref = trim((string) ($row['iyzico_subscription_ref'] ?? ''));
        if ($ref === '' || isset($seen[$ref])) {
            continue;
        }
        $seen[$ref] = true;
        $det = iyzico_sub_detail($ref);
        if (empty($det['ok'])) {
            continue;
        }
        $inner = iyzico_v2_data($det['data'] ?? []);
        $buyer = subscription_buyer_from_iyzico_sub($inner);
        if ($buyer['email'] === '') {
            $buyer['email'] = subscription_norm_email((string) ($row['student_email'] ?? ''));
        }
        if ($buyer['name'] === '') {
            $buyer['name'] = trim((string) ($row['student_name'] ?? ''));
        }
        if ($buyer['ref'] === '') {
            $buyer['ref'] = $ref;
        }
        foreach (iyzico_collect_payment_ids($inner) as $pid) {
            $map[$pid] = $buyer;
        }
    }
    return $map;
}

/**
 * Fatura satırına iyzico'daki gerçek alıcıyı yazar ve abone kaydını taşır.
 */
function subscription_sync_invoice_buyers(PDO $pdo, float $deadline = 0.0): int {
    try {
        $subs = $pdo->query("SELECT * FROM subscriptions ORDER BY id DESC LIMIT 400")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return 0;
    }
    $map = iyzico_payment_buyer_map($deadline);
    $map = iyzico_payment_buyer_map_fill_refs($map, $subs, $deadline);
    iyzico_payment_buyer_map(0.0, $map);
    if ($map === []) {
        return 0;
    }
    $idx = subscription_buyer_indexes($subs);
    $n = 0;
    try {
        $rows = $pdo->query(
            "SELECT id, subscription_id, provider_payment_id FROM subscription_invoices
             WHERE provider_payment_id <> ''"
        )->fetchAll() ?: [];
    } catch (Throwable $e) {
        return 0;
    }
    try {
        $upd = $pdo->prepare(
            "UPDATE subscription_invoices
             SET iyzico_buyer_name = ?, iyzico_buyer_email = ?, subscription_id = ?
             WHERE id = ?"
        );
    } catch (Throwable $e) {
        return 0;
    }
    foreach ($rows as $inv) {
        $pid = iyzico_normalize_payment_id($inv['provider_payment_id'] ?? '');
        if ($pid === '' || !isset($map[$pid])) {
            continue;
        }
        $b = $map[$pid];
        $name = trim((string) ($b['name'] ?? ''));
        $email = subscription_norm_email((string) ($b['email'] ?? ''));
        $newSub = (int) $inv['subscription_id'];
        if ($email !== '' && isset($idx['byEmail'][$email])) {
            $cands = subscription_canonical_rows($idx['byEmail'][$email]);
            if ($cands !== []) {
                $newSub = (int) $cands[0]['id'];
                if ($name === '') {
                    $name = trim((string) ($cands[0]['student_name'] ?? ''));
                }
            }
        } elseif ($name !== '') {
            $nm = subscription_norm_person_name($name);
            if (isset($idx['uniqueName'][$nm])) {
                $newSub = (int) $idx['uniqueName'][$nm]['id'];
                if ($email === '') {
                    $email = subscription_norm_email((string) ($idx['uniqueName'][$nm]['student_email'] ?? ''));
                }
            }
        }
        if ($name === '' && $email === '') {
            continue;
        }
        if ($newSub <= 0) {
            $newSub = (int) $inv['subscription_id'];
        }
        try {
            $upd->execute([$name, $email, $newSub, (int) $inv['id']]);
            $n++;
        } catch (Throwable $e) {
        }
    }
    return $n;
}

/**
 * Yanlış aboneye yazılmış faturaları iyzico alıcı adı / e-postasına göre taşır.
 * Saat alanına dokunmaz.
 */
function subscription_reattach_invoices_by_buyer(
    PDO $pdo,
    string $from,
    string $to,
    float $deadline = 0.0,
    int $detailLeft = 4
): int {
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return 0;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return 0;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    try {
        $subs = $pdo->query("SELECT * FROM subscriptions ORDER BY id DESC LIMIT 400")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return 0;
    }
    $idx = subscription_buyer_indexes($subs);
    $byEmail = $idx['byEmail'];
    $byConv = $idx['byConv'];
    $byRef = $idx['byRef'];
    $uniqueName = $idx['uniqueName'];

    $need = [];
    try {
        $st = $pdo->prepare(
            "SELECT id, subscription_id, provider_payment_id
             FROM subscription_invoices
             WHERE status = 'paid' AND provider_payment_id <> ''
               AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 2 DAY)"
        );
        $st->execute([$from . ' 00:00:00', $to]);
        foreach ($st as $inv) {
            $pid = iyzico_normalize_payment_id($inv['provider_payment_id'] ?? '');
            if ($pid !== '') {
                $need[$pid] = $inv;
            }
        }
    } catch (Throwable $e) {
        return 0;
    }
    if ($need === []) {
        return 0;
    }

    $days = [];
    $ts = strtotime($from . ' 12:00:00');
    $end = strtotime($to . ' 12:00:00');
    if (!$ts || !$end) {
        return 0;
    }
    for ($i = 0; $i < 10 && $end >= $ts; $i++, $end -= 86400) {
        $days[] = date('Y-m-d', $end);
    }

    $upd = $pdo->prepare("UPDATE subscription_invoices SET subscription_id = ? WHERE id = ?");
    $n = 0;
    $unmatched = $need;
    foreach ($days as $ymd) {
        if ($deadline > 0 && microtime(true) >= $deadline) {
            break;
        }
        foreach (iyzico_payment_transactions_by_date($ymd) as $t) {
            $pid = iyzico_normalize_payment_id($t['paymentId'] ?? '');
            if ($pid === '' || !isset($unmatched[$pid])) {
                continue;
            }
            $row = subscription_match_sub_for_tx($t, $byRef, $byConv, $byEmail, $uniqueName);
            if ($row === null) {
                continue;
            }
            $invId = (int) $unmatched[$pid]['id'];
            $old = (int) $unmatched[$pid]['subscription_id'];
            $new = (int) $row['id'];
            if ($new > 0 && $new !== $old) {
                try {
                    $upd->execute([$new, $invId]);
                    $n++;
                } catch (Throwable $e) {
                }
            }
            unset($unmatched[$pid]);
        }
    }

    foreach ($unmatched as $pid => $inv) {
        if ($detailLeft <= 0 || ($deadline > 0 && microtime(true) >= $deadline)) {
            break;
        }
        $detailLeft--;
        $rep = iyzico_payment_report($pid);
        if (empty($rep['ok'])) {
            continue;
        }
        $data = iyzico_v2_data($rep['data'] ?? []);
        $t = [
            'conversationId' => (string) ($data['conversationId'] ?? $data['paymentConversationId'] ?? ''),
            'paymentConversationId' => (string) ($data['paymentConversationId'] ?? ''),
            'subscriptionReferenceCode' => (string) ($data['subscriptionReferenceCode'] ?? ''),
            'buyerEmail' => subscription_nested_email($data),
            'buyerName' => (string) ($data['buyerName'] ?? $data['name'] ?? ''),
            'buyerSurname' => (string) ($data['buyerSurname'] ?? $data['surname'] ?? ''),
        ];
        $row = subscription_match_sub_for_tx($t, $byRef, $byConv, $byEmail, $uniqueName);
        if ($row === null) {
            continue;
        }
        $new = (int) $row['id'];
        $old = (int) $inv['subscription_id'];
        if ($new > 0 && $new !== $old) {
            try {
                $upd->execute([$new, (int) $inv['id']]);
                $n++;
            } catch (Throwable $e) {
            }
        }
    }
    return $n;
}

/**
 * Aynı iyzico ödeme nosuna veya aynı gün boş-nolu kopyaya düşen fazla faturaları siler.
 */
function subscription_dedupe_invoices_by_payment_id(PDO $pdo): int {
    $n = 0;
    try {
        $n += (int) $pdo->exec(
            "DELETE i1 FROM subscription_invoices i1
             INNER JOIN subscription_invoices i2
                ON i1.provider_payment_id = i2.provider_payment_id
               AND i1.provider_payment_id <> ''
               AND i1.id > i2.id"
        );
        $n += (int) $pdo->exec(
            "DELETE i1 FROM subscription_invoices i1
             INNER JOIN subscription_invoices i2
                ON i1.subscription_id = i2.subscription_id
               AND DATE(i1.created_at) = DATE(i2.created_at)
               AND i1.amount_kurus = i2.amount_kurus
               AND i1.status = 'paid' AND i2.status = 'paid'
               AND (i1.provider_payment_id IS NULL OR i1.provider_payment_id = '')
               AND i2.provider_payment_id <> ''"
        );
    } catch (Throwable $e) {
        return $n;
    }
    return $n;
}

/**
 * iyzico İşlemler raporundan eksik abonelik faturalarını yazar.
 * Panel yüklemesinde en yeni günden geriye, kısa süre bütçesiyle çalışır.
 *
 * @return int yeni yazılan fatura
 */
function subscription_import_iyzico_payments(
    PDO $pdo,
    string $from,
    string $to,
    float $deadline = 0.0,
    int $detailLeft = 0
): int {
    if (!function_exists('iyzico_ready') || !iyzico_ready()) {
        return 0;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return 0;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $days = [];
    $ts = strtotime($from . ' 12:00:00');
    $end = strtotime($to . ' 12:00:00');
    if (!$ts || !$end) {
        return 0;
    }
    for ($i = 0; $i < 8 && $end >= $ts; $i++, $end -= 86400) {
        $days[] = date('Y-m-d', $end);
    }

    $used = [];
    try {
        foreach ($pdo->query("SELECT provider_payment_id FROM subscription_invoices WHERE provider_payment_id <> ''") ?: [] as $r) {
            $id = iyzico_normalize_payment_id($r['provider_payment_id'] ?? '');
            if ($id !== '') {
                $used[$id] = true;
            }
        }
        foreach ($pdo->query("SELECT provider_payment_id, amount_kurus FROM payment_orders WHERE provider_payment_id <> ''") ?: [] as $r) {
            $id = iyzico_normalize_payment_id($r['provider_payment_id'] ?? '');
            if ($id !== '') {
                $used[$id] = true;
            }
        }
    } catch (Throwable $e) {
        return 0;
    }

    $subs = [];
    try {
        $subs = $pdo->query("SELECT * FROM subscriptions ORDER BY id DESC LIMIT 400")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return 0;
    }
    $idx = subscription_buyer_indexes($subs);
    $byRef = $idx['byRef'];
    $byConv = $idx['byConv'];
    $byEmail = $idx['byEmail'];
    $uniqueName = $idx['uniqueName'];

    $billed = [];
    try {
        $bst = $pdo->prepare(
            "SELECT subscription_id, DATE(created_at) AS d
             FROM subscription_invoices
             WHERE status = 'paid' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)"
        );
        $bst->execute([$from, $to]);
        foreach ($bst as $r) {
            $billed[(int) $r['subscription_id'] . ':' . $r['d']] = true;
        }
    } catch (Throwable $e) {
    }

    $timedOut = static function () use ($deadline): bool {
        return $deadline > 0 && microtime(true) >= $deadline;
    };

    $findSub = static function (array $t, string $pid) use (
        $byRef,
        $byConv,
        $byEmail,
        $uniqueName,
        &$detailLeft
    ): ?array {
        $row = subscription_match_sub_for_tx($t, $byRef, $byConv, $byEmail, $uniqueName);
        if ($row !== null) {
            return $row;
        }
        if ($pid === '' || $detailLeft <= 0) {
            return null;
        }
        $detailLeft--;
        $rep = iyzico_payment_report($pid);
        if (empty($rep['ok'])) {
            return null;
        }
        $data = iyzico_v2_data($rep['data'] ?? []);
        $t2 = [
            'conversationId' => (string) ($data['conversationId'] ?? $data['paymentConversationId'] ?? ''),
            'paymentConversationId' => (string) ($data['paymentConversationId'] ?? ''),
            'subscriptionReferenceCode' => (string) ($data['subscriptionReferenceCode'] ?? ''),
            'buyerEmail' => subscription_nested_email($data),
            'buyerName' => (string) ($data['buyerName'] ?? $data['name'] ?? ''),
            'buyerSurname' => (string) ($data['buyerSurname'] ?? $data['surname'] ?? ''),
        ];
        return subscription_match_sub_for_tx($t2, $byRef, $byConv, $byEmail, $uniqueName);
    };

    $n = 0;
    $commitInv = static function (array $row, string $pid, int $kurus, string $ymd, array $t) use ($pdo, &$used, &$billed, &$n): void {
        $subKurus = (int) $row['amount_kurus'];
        if ($subKurus > 0 && $kurus !== $subKurus) {
            return;
        }
        $payAt = subscription_tx_paid_at($t, $ymd);
        $ok = subscription_record_invoice(
            $pdo,
            (int) $row['id'],
            'iyz-' . $pid,
            $kurus,
            'paid',
            $pid,
            $payAt
        );
        if (!$ok) {
            return;
        }
        $used[$pid] = true;
        $billed[(int) $row['id'] . ':' . substr($payAt, 0, 10)] = true;
        $n++;
        $st = (string) $row['status'];
        $last = (string) ($row['last_paid_at'] ?? '');
        if (in_array($st, ['active', 'past_due', 'expired'], true) && ($last === '' || $payAt >= $last)) {
            $interval = (string) ($row['interval_unit'] ?: subscription_interval());
            $end = subscription_add_period($interval, $payAt);
            try {
                $pdo->prepare(
                    "UPDATE subscriptions
                     SET status = 'active', last_paid_at = ?, current_period_end = ?,
                         error_message = '', cancelled_at = NULL, updated_at = NOW()
                     WHERE id = ?"
                )->execute([$payAt, $end, (int) $row['id']]);
            } catch (Throwable $e) {
            }
        }
    };

    foreach ($days as $ymd) {
        if ($timedOut()) {
            break;
        }
        foreach (iyzico_payment_transactions_by_date($ymd) as $t) {
            if ($timedOut()) {
                break 2;
            }
            $type = strtoupper((string) ($t['transactionType'] ?? 'PAYMENT'));
            if ($type !== '' && $type !== 'PAYMENT') {
                continue;
            }
            $pid = iyzico_normalize_payment_id($t['paymentId'] ?? '');
            if ($pid === '' || isset($used[$pid])) {
                continue;
            }
            $kurus = (int) round(((float) ($t['paidPrice'] ?? $t['price'] ?? 0)) * 100);
            if ($kurus < 100) {
                continue;
            }
            $row = $findSub($t, $pid);
            if ($row === null) {
                continue;
            }
            $commitInv($row, $pid, $kurus, $ymd, $t);
        }
    }
    return $n;
}

/**
 * Aynı takvim gününde bir e-postaya birden fazla başarılı abonelik çekimi.
 *
 * @return list<array{name:string,email:string,day:string,count:int}>
 */
function subscription_duplicate_charges(PDO $pdo, string $from, string $to): array {
    $out = [];
    try {
        $sql = "SELECT COALESCE(NULLIF(i.iyzico_buyer_name, ''), s.student_name) AS name,
                       COALESCE(NULLIF(i.iyzico_buyer_email, ''), s.student_email) AS email,
                       DATE(i.created_at) AS day, COUNT(*) AS c
                FROM subscription_invoices i
                JOIN subscriptions s ON s.id = i.subscription_id
                WHERE i.status = 'paid'";
        $params = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $sql .= ' AND DATE(i.created_at) >= ?';
            $params[] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $sql .= ' AND DATE(i.created_at) <= ?';
            $params[] = $to;
        }
        $sql .= ' GROUP BY COALESCE(NULLIF(i.iyzico_buyer_email, \'\'), s.student_email),'
            . ' COALESCE(NULLIF(i.iyzico_buyer_name, \'\'), s.student_name), DATE(i.created_at)'
            . ' HAVING c > 1 ORDER BY day DESC LIMIT 20';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'name' => (string) $r['name'],
                'email' => (string) $r['email'],
                'day' => (string) $r['day'],
                'count' => (int) $r['c'],
            ];
        }
    } catch (Throwable $e) {
    }
    return $out;
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
        $payId = subscription_resolve_payment_id($row, $inner);
        subscription_record_invoice($pdo, (int)$row['id'], $orderRef, (int)$row['amount_kurus'], 'paid', $payId);
        subscription_notify_new($pdo, $row);
    }
    subscription_collapse_duplicates_for_identity(
        $pdo,
        (int) ($row['student_id'] ?? 0),
        (string) ($row['student_email'] ?? ''),
        (int) $row['id']
    );
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
        $inner = [];
        if ($row['iyzico_subscription_ref'] !== '') {
            $det = iyzico_sub_detail((string)$row['iyzico_subscription_ref']);
            $fresh = $det['ok'];
            if ($fresh) {
                $inner = iyzico_v2_data($det['data']);
                subscription_mark_active($pdo, $row, $inner, (string)$row['interval_unit'], site_now());
            }
        }
        if (!$fresh) {
            subscription_mark_active($pdo, $row, $payload, (string)$row['interval_unit'], site_now());
        }
        subscription_record_invoice($pdo, (int)$row['id'], $orderRef, (int)$row['amount_kurus'], 'paid', subscription_resolve_payment_id($row, $inner, $payload));
        subscription_notify_new($pdo, $row);
        subscription_collapse_duplicates_for_identity(
            $pdo,
            (int) ($row['student_id'] ?? 0),
            (string) ($row['student_email'] ?? ''),
            (int) $row['id']
        );
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
    subscription_collapse_duplicates_for_identity(
        $pdo,
        (int) ($row['student_id'] ?? $studentId),
        (string) ($row['student_email'] ?? ''),
        (int) $row['id']
    );
    return ['ok' => true, 'error' => ''];
}

function subscription_instructor_id(PDO $pdo): int {
    return (int)subscription_setting($pdo, 'sub_instructor_id', '0');
}

function subscription_admin_list(PDO $pdo): array {
    subscription_collapse_duplicate_actives($pdo);
    subscription_reconcile_due_periods($pdo);
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
    return subscription_sort_admin_rows($out);
}

function subscription_sort_admin_rows(array $out): array {
    usort($out, static function ($a, $b) {
        $rank = static function (array $r): int {
            $st = (string)($r['status'] ?? '');
            if ($st === 'active') {
                return 0;
            }
            if ($st === 'past_due') {
                return 1;
            }
            if ($st === 'pending') {
                return 2;
            }
            if ($st === 'cancelled' && !empty($r['entitled'])) {
                return 3;
            }
            if ($st === 'cancelled') {
                return 4;
            }
            return 5;
        };
        $c = $rank($a) <=> $rank($b);
        if ($c !== 0) {
            return $c;
        }
        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });
    return $out;
}

function subscription_list_for_instructor(PDO $pdo, int $instructorId): array {
    subscription_collapse_duplicate_actives($pdo);
    subscription_reconcile_due_periods($pdo);
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
    return subscription_sort_admin_rows($out);
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
