<?php
/**
 * Abonelik kurulum teşhisi (yalnızca site yöneticisi).
 *
 * Neden var: "iyzico Abonelikler'de aktif ama İşlem Listesi'nde tahsilat yok"
 * durumunun sebebi genelde kullanılan fiyat planıdır. Deneme süreli
 * (trialPeriodDays > 0) veya RECURRING olmayan bir plan seçilirse abonelik
 * açılır, kart çekilmez. Bu uç, canlı sunucunun gerçekten hangi planı
 * kullandığını ve o planın iyzico'daki gerçek alanlarını gösterir.
 *
 * GET /api/iyzico_sub_status.php
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/subscriptions.php';

require_site_admin();

header('Cache-Control: no-store');

$pdo = db();
subscriptions_ensure_schema($pdo);

$env = subscription_iyzico_env();
$keys = subscription_iyzico_setting_keys($env);
$interval = subscription_interval();
$kurus = subscription_price_kurus($pdo);
$price = payments_kurus_to_decimal($kurus);
$title = subscription_title($pdo);

$stored = [
    'productRef' => subscription_setting($pdo, $keys['product'], ''),
    'planRef' => subscription_setting($pdo, $keys['plan'], ''),
    'interval' => subscription_setting($pdo, $keys['interval'], ''),
    'price' => subscription_setting($pdo, $keys['price'], ''),
];

$product = null;
$plans = [];
$activePlan = null;
$listError = '';

if (iyzico_ready()) {
    $found = iyzico_sub_product_find_by_name($title);
    if (is_array($found)) {
        $product = [
            'name' => (string)($found['name'] ?? ''),
            'referenceCode' => (string)($found['referenceCode'] ?? ''),
            'status' => (string)($found['status'] ?? ''),
        ];
        $rawPlans = is_array($found['pricingPlans'] ?? null) ? $found['pricingPlans'] : [];
        foreach ($rawPlans as $p) {
            if (!is_array($p)) {
                continue;
            }
            $row = [
                'referenceCode' => (string)($p['referenceCode'] ?? ''),
                'name' => (string)($p['name'] ?? ''),
                'price' => (string)($p['price'] ?? ''),
                'currencyCode' => (string)($p['currencyCode'] ?? ''),
                'paymentInterval' => (string)($p['paymentInterval'] ?? ''),
                'paymentIntervalCount' => (int)($p['paymentIntervalCount'] ?? 0),
                'planPaymentType' => (string)($p['planPaymentType'] ?? ''),
                'trialPeriodDays' => (int)($p['trialPeriodDays'] ?? 0),
                'status' => (string)($p['status'] ?? ''),
            ];
            $row['tahsilatYapar'] = $row['trialPeriodDays'] === 0
                && strtoupper($row['planPaymentType']) === 'RECURRING';
            $plans[] = $row;
            if ($row['referenceCode'] !== '' && $row['referenceCode'] === $stored['planRef']) {
                $activePlan = $row;
            }
        }
    } else {
        $listError = 'iyzico ürün listesi okunamadi (ürün bulunamadi veya GET yetkisi/imza hatasi).';
    }
} else {
    $listError = 'iyzico anahtarlari tanimli degil.';
}

$counts = [];
try {
    $st = $pdo->query("SELECT status, COUNT(*) c FROM subscriptions GROUP BY status");
    foreach ($st->fetchAll() as $r) {
        $counts[(string)$r['status']] = (int)$r['c'];
    }
} catch (Throwable $e) {
    $counts = [];
}

$stuck = [];
try {
    $st = $pdo->query(
        "SELECT id, student_email, status, error_message, created_at
         FROM subscriptions
         WHERE iyzico_subscription_ref = '' AND provider_token <> ''
           AND created_at > (NOW() - INTERVAL 3 DAY)
         ORDER BY id DESC LIMIT 20"
    );
    $stuck = $st->fetchAll();
} catch (Throwable $e) {
    $stuck = [];
}

// Kurs ödemeleri: "eğitim aldım ama İşlem Listesinde yok" durumunu teşhis için.
// paid + provider_payment_id dolu ise iyzico gerçekten tahsil etmiştir; o kayit
// sandbox-merchant İşlem Listesinde de görünmelidir. paid ama payment_id bossa
// veya status pending/failed ise tahsilat gerceklesmemistir.
$courseOrders = [];
$courseSummary = ['paid_with_id' => 0, 'paid_no_id' => 0, 'pending' => 0, 'failed' => 0, 'review' => 0, 'other' => 0];
try {
    $st = $pdo->query(
        "SELECT id, merchant_oid, student_email, amount_kurus, status,
                provider_payment_id, provider_transaction_id, error_message, created_at, paid_at
         FROM payment_orders
         WHERE provider = 'iyzico'
         ORDER BY id DESC LIMIT 30"
    );
    foreach ($st->fetchAll() as $o) {
        $status = (string)$o['status'];
        $hasId = trim((string)$o['provider_payment_id']) !== '';
        if ($status === 'paid' && $hasId) {
            $courseSummary['paid_with_id']++;
        } elseif ($status === 'paid') {
            $courseSummary['paid_no_id']++;
        } elseif (isset($courseSummary[$status])) {
            $courseSummary[$status]++;
        } else {
            $courseSummary['other']++;
        }
        $courseOrders[] = [
            'id' => (int)$o['id'],
            'ref' => (string)$o['merchant_oid'],
            'email' => (string)$o['student_email'],
            'tutar' => number_format(((int)$o['amount_kurus']) / 100, 2, ',', '.'),
            'status' => $status,
            'iyzicoPaymentId' => (string)$o['provider_payment_id'],
            'iyzicoTxId' => (string)$o['provider_transaction_id'],
            'hata' => (string)$o['error_message'],
            'created_at' => (string)$o['created_at'],
            'paid_at' => (string)($o['paid_at'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    $courseOrders = [];
}

$warnings = [];
if ($activePlan && !$activePlan['tahsilatYapar']) {
    $warnings[] = 'KULLANILAN PLAN TAHSILAT YAPMIYOR: trialPeriodDays=' . $activePlan['trialPeriodDays']
        . ', planPaymentType=' . $activePlan['planPaymentType']
        . '. Abonelik aktif gorunur ama Islem Listesinde kayit olusmaz.';
}
if ($stored['planRef'] !== '' && $plans !== [] && $activePlan === null) {
    $warnings[] = 'Ayarlardaki plan referansi iyzico urununde bulunamadi; plan silinmis olabilir. '
        . 'Ilk abonelik denemesinde otomatik yeniden kurulur.';
}
if ($stored['interval'] !== '' && $stored['interval'] !== $interval) {
    $warnings[] = 'Kayitli donem (' . $stored['interval'] . ') beklenen donemden (' . $interval . ') farkli.';
}

json_out([
    'ok' => true,
    'env' => $env,
    'keySource' => defined('IYZICO_KEY_SOURCE') ? IYZICO_KEY_SOURCE : 'none',
    'baseUrl' => iyzico_base_url(),
    'subscriptionCallbackUrl' => iyzico_sub_callback_url(),
    'expected' => ['title' => $title, 'interval' => $interval, 'price' => $price],
    'storedRefs' => $stored,
    'iyzicoProduct' => $product,
    'iyzicoPlans' => $plans,
    'kullanilanPlan' => $activePlan,
    'listError' => $listError,
    'warnings' => $warnings,
    'dbStatusCounts' => $counts,
    'dogrulanmamisKayitlar' => $stuck,
    'kursOdemeOzeti' => $courseSummary,
    'kursOdemeleri' => $courseOrders,
    'note' => 'Tahsilat kaydi https://sandbox-merchant.iyzipay.com/transactions adresinde gorunur. '
        . 'paid_with_id > 0 ise o odemeler iyzico tarafinda tahsil edilmistir ve Islem Listesinde '
        . 'gorunmelidir; tarih filtresini bugunu kapsayacak sekilde genisletin.',
]);
