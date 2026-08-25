<?php
/**
 * iyzico API istemcisi — resmi SDK/Composer olmadan.
 *
 * Kimlik doğrulama IYZWSv2 (HMAC-SHA256) şemasıdır:
 *   payload   = randomKey + uriPath + requestBody
 *   signature = HMAC-SHA256(payload, secretKey)  (hex, küçük harf)
 *   authStr   = "apiKey:{apiKey}&randomKey:{randomKey}&signature:{signature}"
 *   header    = "IYZWSv2 " + base64(authStr)
 *
 * Kaynak: docs.iyzico.com → Getting Started → Authentication → HMACSHA256 Auth
 *
 * ÖNEMLİ: İmza, gövdenin birebir aynı metni üzerinden üretilir. Bu yüzden JSON
 * bir kez kurulup hem imzada hem istekte aynı dize olarak kullanılır.
 */
require_once __DIR__ . '/iyzico_config.php';
require_once __DIR__ . '/payments_schema.php';

/** cPanel'de curl.cainfo boş olabiliyor; yerelde çalışıp canlıda SSL hatası vermesin. */
function iyzico_ca_file(): string {
    $candidates = [
        (string)ini_get('curl.cainfo'),
        (string)ini_get('openssl.cafile'),
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
    ];
    foreach ($candidates as $p) {
        $p = trim($p);
        if ($p !== '' && is_file($p)) {
            return $p;
        }
    }
    return '';
}

function iyzico_curl_ssl_opts(): array {
    $opts = [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $ca = iyzico_ca_file();
    if ($ca !== '') {
        $opts[CURLOPT_CAINFO] = $ca;
    }
    return $opts;
}

/**
 * iyzico'ya imzalı POST isteği.
 *
 * `reason` çağıranın doğru kararı verebilmesi için hatanın türünü söyler:
 *   'network' — iyzico'ya ulaşılamadı, sonuç BİLİNMİYOR
 *   'parse'   — yanıt okunamadı, sonuç BİLİNMİYOR
 *   'api'     — iyzico açıkça hata döndü, sonuç BİLİNİYOR (başarısız)
 *
 * @return array{ok:bool, status:int, data:array, error:string, reason:string}
 */
function iyzico_request(string $uriPath, array $payload): array {
    if (!iyzico_ready()) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'iyzico anahtarları tanımlı değil.', 'reason' => 'config'];
    }

    // Gövde tek sefer kurulur; imza da bu metinden üretilir.
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'İstek gövdesi hazırlanamadı.', 'reason' => 'config'];
    }

    $randomKey = iyzico_random_key();
    $authHeader = iyzico_auth_header($uriPath, $body, $randomKey);

    $ch = curl_init(iyzico_base_url() . $uriPath);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authHeader,
            'x-iyzi-rnd: ' . $randomKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ] + iyzico_curl_ssl_opts());

    $raw = curl_exec($ch);
    $curlError = curl_errno($ch) ? curl_error($ch) : '';
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        $out = ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Bağlantı hatası: ' . $curlError, 'reason' => 'network'];
    } else {
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $out = ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'iyzico yanıtı okunamadı.', 'reason' => 'parse'];
        } elseif (($data['status'] ?? '') !== 'success') {
            $msg = trim((string)($data['errorMessage'] ?? ''));
            $code = trim((string)($data['errorCode'] ?? ''));
            $out = [
                'ok' => false,
                'status' => $status,
                'data' => $data,
                'error' => $msg !== '' ? $msg : ('iyzico işlemi reddetti' . ($code !== '' ? " (kod $code)" : '')),
                'reason' => 'api',
            ];
        } else {
            $out = ['ok' => true, 'status' => $status, 'data' => $data, 'error' => '', 'reason' => ''];
        }
    }

    iyzico_audit_log('POST', $uriPath, $payload, $out);
    return $out;
}

/**
 * iyzico GET (rapor uçları). İmza: randomKey + uriPath  (gövde yok).
 *
 * @return array{ok:bool, status:int, data:array, error:string, reason:string}
 */
function iyzico_get(string $uriPath, array $query = []): array {
    if (!iyzico_ready()) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'iyzico anahtarları tanımlı değil.', 'reason' => 'config'];
    }
    $randomKey = iyzico_random_key();
    $authHeader = iyzico_auth_header($uriPath, '', $randomKey);
    $url = iyzico_base_url() . $uriPath;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authHeader,
            'x-iyzi-rnd: ' . $randomKey,
            'Accept: application/json',
        ],
    ] + iyzico_curl_ssl_opts());
    $raw = curl_exec($ch);
    $curlError = curl_errno($ch) ? curl_error($ch) : '';
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlError !== '') {
        $out = ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Bağlantı hatası: ' . $curlError, 'reason' => 'network'];
    } else {
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $out = ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'iyzico yanıtı okunamadı.', 'reason' => 'parse'];
        } elseif (($data['status'] ?? '') !== 'success') {
            $msg = trim((string)($data['errorMessage'] ?? ''));
            $out = ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg !== '' ? $msg : 'iyzico işlemi reddetti', 'reason' => 'api'];
        } else {
            $out = ['ok' => true, 'status' => $status, 'data' => $data, 'error' => '', 'reason' => ''];
        }
    }
    iyzico_audit_log('GET', $uriPath, $query, $out);
    return $out;
}

/**
 * iyzico istek/yanıtını payment_logs'a yazar. İmza ve kart alanı yazılmaz.
 *
 * @param array $reqPayload
 * @param array{ok:bool,status:int,data:array,error:string,reason:string} $result
 */
function iyzico_audit_log(string $method, string $uriPath, array $reqPayload, array $result): void {
    $resp = is_array($result['data'] ?? null) ? $result['data'] : [];
    $orderId = payments_log_resolve_order_id(array_merge($reqPayload, $resp));
    if (($reqPayload['token'] ?? '') === 'status-ping') {
        return;
    }
    payments_log_write($orderId, 'iyzico', 'request', $method . ' ' . $uriPath, $reqPayload);
    if ($resp === []) {
        $resp = [
            '_ok' => !empty($result['ok']),
            '_error' => (string)($result['error'] ?? ''),
            '_http' => (int)($result['status'] ?? 0),
            '_reason' => (string)($result['reason'] ?? ''),
        ];
    } else {
        $resp['_http'] = (int)($result['status'] ?? 0);
        if (empty($result['ok'])) {
            $resp['_error'] = (string)($result['error'] ?? '');
        }
    }
    payments_log_write($orderId, 'iyzico', 'response', $method . ' ' . $uriPath, $resp);
}

/** Her istek için benzersiz rastgele anahtar (x-iyzi-rnd) */
function iyzico_random_key(): string {
    return (string)round(microtime(true) * 1000) . bin2hex(random_bytes(4));
}

/** IYZWSv2 Authorization başlığını üret */
function iyzico_auth_header(string $uriPath, string $body, string $randomKey): string {
    $signature = hash_hmac('sha256', $randomKey . $uriPath . $body, IYZICO_SECRET_KEY);
    $authString = 'apiKey:' . IYZICO_API_KEY
        . '&randomKey:' . $randomKey
        . '&signature:' . $signature;
    return 'IYZWSv2 ' . base64_encode($authString);
}

/**
 * Fiyat alanlarını imza için normalize et ("sondaki sıfırlar").
 * iyzico dokümanı: 50.00 -> 50, 10.50 -> 10.5, 10.510 -> 10.51
 */
function iyzico_trailing_zero($value): string {
    if (is_int($value) || is_float($value)) {
        // Para için 4 ondalık fazlasıyla yeterli; float gösterim farklarını engeller
        $s = number_format((float)$value, 4, '.', '');
    } else {
        $s = trim((string)$value);
    }
    if ($s === '') {
        return '';
    }
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s === '' ? '0' : $s;
}

/**
 * Yanıt imzasını doğrula.
 *
 * iyzico her yanıtta, belirli alanların ":" ile birleştirilip secretKey ile
 * HMAC-SHA256'lanmasından oluşan bir `signature` döner. Bu doğrulama, araya
 * girip yanıtı değiştirmeye karşı korur — ödemeyi onaylamadan önce şart.
 *
 * Alan sıraları docs.iyzico.com → Advanced → Response Signature Validation
 */
function iyzico_verify_signature(string $uriPath, array $response): bool {
    $given = trim((string)($response['signature'] ?? ''));
    if ($given === '') {
        return false;
    }

    if ($uriPath === IYZICO_PATH_CF_RETRIEVE) {
        $parts = [
            (string)($response['paymentStatus'] ?? ''),
            (string)($response['paymentId'] ?? ''),
            (string)($response['currency'] ?? ''),
            (string)($response['basketId'] ?? ''),
            (string)($response['conversationId'] ?? ''),
            iyzico_trailing_zero($response['paidPrice'] ?? ''),
            iyzico_trailing_zero($response['price'] ?? ''),
            (string)($response['token'] ?? ''),
        ];
    } elseif ($uriPath === IYZICO_PATH_CF_INITIALIZE) {
        $parts = [
            (string)($response['conversationId'] ?? ''),
            (string)($response['token'] ?? ''),
        ];
    } else {
        return false;
    }

    $expected = hash_hmac('sha256', implode(':', $parts), IYZICO_SECRET_KEY);
    return hash_equals($expected, $given);
}

/**
 * iyzico buyer.identityNumber alanını zorunlu tutuyor. Dijital ürün sattığımız
 * için TC kimlik numarası toplamıyoruz; iyzico'nun kendi örneklerinde de
 * kullanılan dolgu değeri gönderilir. Gerçek fatura kesilecekse form üzerinden
 * TC toplanıp bu değer yerine iletilebilir.
 */
const IYZICO_PLACEHOLDER_IDENTITY = '11111111111';

/** Ad soyadı ad + soyad olarak ayır (iyzico ikisini ayrı ister) */
function iyzico_split_name(string $fullName): array {
    $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return ['Ogrenci', '-'];
    }
    if (count($parts) === 1) {
        return [$parts[0], '-'];
    }
    $last = array_pop($parts);
    return [implode(' ', $parts), $last];
}

/** Telefonu +90XXXXXXXXXX biçimine getir */
function iyzico_format_gsm(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if ($digits === '') {
        return '';
    }
    // Baştaki 0 veya 90 önekini temizle, 10 haneli çekirdeği bul
    if (strlen($digits) > 10 && strpos($digits, '90') === 0) {
        $digits = substr($digits, 2);
    }
    $digits = ltrim($digits, '0');
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    return '+90' . $digits;
}

/** iyzico'nun beklediği tarih biçimi (YYYY-MM-DD hh:mm:ss) */
function iyzico_date($value): string {
    $ts = $value ? strtotime((string)$value) : false;
    return date('Y-m-d H:i:s', $ts ?: time());
}

/**
 * Checkout Form initialize gövdesini kur.
 *
 * Ayrı fonksiyon: iyzico zorunlu alanları çok ve tutar hesabı kritik olduğu
 * için ağ çağrısı olmadan test edilebilir olması gerekiyor.
 *
 * @param array $a reference, amountKurus, courseId, courseTitle, courseCategory,
 *                 studentId, fullName, email, phone, city, address, identity,
 *                 registeredAt, lastLoginAt, ip, callbackUrl
 */
function iyzico_build_checkout_payload(array $a): array {
    $price = payments_kurus_to_decimal((int)$a['amountKurus']);
    list($firstName, $lastName) = iyzico_split_name((string)$a['fullName']);

    $city = trim((string)($a['city'] ?? '')) !== ''
        ? (string)$a['city']
        : (defined('BRAND_CITY') ? BRAND_CITY : 'İstanbul');
    $address = trim((string)($a['address'] ?? '')) !== ''
        ? (string)$a['address']
        : 'Dijital ürün — adres bildirilmedi';

    $identity = preg_replace('/\D/', '', (string)($a['identity'] ?? ''));
    if (strlen((string)$identity) !== 11) {
        $identity = IYZICO_PLACEHOLDER_IDENTITY;
    }

    $addressBlock = [
        'address' => mb_substr($address, 0, 250),
        'contactName' => mb_substr((string)$a['fullName'], 0, 100),
        'city' => mb_substr($city, 0, 60),
        'country' => 'Turkey',
    ];

    return [
        'locale' => 'tr',
        'conversationId' => (string)$a['reference'],
        'price' => $price,
        'paidPrice' => $price,
        'currency' => 'TRY',
        'basketId' => (string)$a['reference'],
        'paymentGroup' => 'PRODUCT',
        'callbackUrl' => (string)$a['callbackUrl'],
        'enabledInstallments' => iyzico_installments(),
        'buyer' => [
            'id' => (string)$a['studentId'],
            'name' => $firstName,
            'surname' => $lastName,
            'identityNumber' => $identity,
            'email' => (string)$a['email'],
            'gsmNumber' => iyzico_format_gsm((string)$a['phone']),
            'registrationDate' => iyzico_date($a['registeredAt'] ?? null),
            'lastLoginDate' => iyzico_date($a['lastLoginAt'] ?? null),
            'registrationAddress' => mb_substr($address, 0, 250),
            'city' => mb_substr($city, 0, 60),
            'country' => 'Turkey',
            'ip' => (string)($a['ip'] ?? '127.0.0.1'),
        ],
        // Tüm kalemler VIRTUAL olsa da iyzico her iki adresi de bekliyor
        'shippingAddress' => $addressBlock,
        'billingAddress' => $addressBlock,
        'basketItems' => [[
            'id' => 'course-' . (int)$a['courseId'],
            'name' => mb_substr(trim((string)$a['courseTitle']) !== '' ? (string)$a['courseTitle'] : 'Eğitim', 0, 100),
            'category1' => mb_substr(trim((string)($a['courseCategory'] ?? '')) !== '' ? (string)$a['courseCategory'] : 'Eğitim', 0, 60),
            'itemType' => 'VIRTUAL',
            'price' => $price,
        ]],
    ];
}

/**
 * Checkout Form oturumu başlat.
 *
 * @return array{ok:bool, token:string, paymentPageUrl:string, formContent:string, error:string, data:array}
 */
function iyzico_checkout_initialize(array $payload): array {
    $res = iyzico_request(IYZICO_PATH_CF_INITIALIZE, $payload);
    if (!$res['ok']) {
        return ['ok' => false, 'token' => '', 'paymentPageUrl' => '', 'formContent' => '', 'error' => $res['error'], 'data' => $res['data']];
    }

    $d = $res['data'];
    if (!iyzico_verify_signature(IYZICO_PATH_CF_INITIALIZE, $d)) {
        return ['ok' => false, 'token' => '', 'paymentPageUrl' => '', 'formContent' => '', 'error' => 'iyzico yanıt imzası doğrulanamadı.', 'data' => $d];
    }

    $token = (string)($d['token'] ?? '');
    $pageUrl = (string)($d['paymentPageUrl'] ?? '');
    if ($token === '' || $pageUrl === '') {
        return ['ok' => false, 'token' => $token, 'paymentPageUrl' => '', 'formContent' => '', 'error' => 'iyzico ödeme sayfası bilgisi eksik döndü.', 'data' => $d];
    }

    return [
        'ok' => true,
        'token' => $token,
        'paymentPageUrl' => $pageUrl,
        'formContent' => (string)($d['checkoutFormContent'] ?? ''),
        'error' => '',
        'data' => $d,
    ];
}

/**
 * Ödeme sonucunu jetonla sorgula ve imzasını doğrula.
 *
 * @return array{ok:bool, paid:bool, data:array, error:string, reason:string}
 */
function iyzico_checkout_retrieve(string $token, string $conversationId = ''): array {
    $payload = ['locale' => 'tr', 'token' => $token];
    if ($conversationId !== '') {
        $payload['conversationId'] = $conversationId;
    }

    $res = iyzico_request(IYZICO_PATH_CF_RETRIEVE, $payload);
    if (!$res['ok']) {
        return ['ok' => false, 'paid' => false, 'data' => $res['data'], 'error' => $res['error'], 'reason' => $res['reason']];
    }

    $d = $res['data'];
    if (!iyzico_verify_signature(IYZICO_PATH_CF_RETRIEVE, $d)) {
        // Yanıt doğrulanmış TLS üzerinden sunucudan sunucuya geldi; buradaki
        // uyuşmazlık saldırıdan çok bizim tarafta bir hataya işaret eder.
        // Erişim yine açılmaz ama sipariş 'başarısız' sayılamaz.
        return ['ok' => false, 'paid' => false, 'data' => $d, 'error' => 'iyzico yanıt imzası doğrulanamadı.', 'reason' => 'signature'];
    }

    // paymentStatus = SUCCESS ve dolandırıcılık kontrolü reddetmemiş olmalı.
    // fraudStatus 0 = incelemede: erişimi hemen açmıyoruz.
    $paid = strtoupper((string)($d['paymentStatus'] ?? '')) === 'SUCCESS'
        && (int)($d['fraudStatus'] ?? 1) === 1;

    return ['ok' => true, 'paid' => $paid, 'data' => $d, 'error' => '', 'reason' => ''];
}

/**
 * Ödemeyi paymentId ile sorgula (iade kontrolü için).
 *
 * @return array{ok:bool, data:array, error:string}
 */
function iyzico_payment_retrieve(string $paymentId, string $conversationId = ''): array {
    $payload = ['locale' => 'tr', 'paymentId' => $paymentId];
    if ($conversationId !== '') {
        $payload['conversationId'] = $conversationId;
    }
    $res = iyzico_request(IYZICO_PATH_PAYMENT_DETAIL, $payload);
    if (!$res['ok']) {
        return ['ok' => false, 'data' => $res['data'], 'error' => $res['error']];
    }
    return ['ok' => true, 'data' => $res['data'], 'error' => ''];
}

/**
 * Ödeme raporu — panel iptal/iadesi burada görünür.
 * /payment/detail iptalden sonra bile SUCCESS dönebiliyor.
 */
function iyzico_payment_report(string $paymentId): array {
    return iyzico_get('/v2/reporting/payment/details', ['paymentId' => $paymentId]);
}

function iyzico_report_looks_refunded(array $report): bool {
    foreach (is_array($report['payments'] ?? null) ? $report['payments'] : [] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $rs = strtoupper((string)($p['paymentRefundStatus'] ?? ''));
        if (in_array($rs, ['TOTALLY_REFUNDED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
            return true;
        }
        if (!empty($p['cancels']) && is_array($p['cancels'])) {
            return true;
        }
    }
    return false;
}

function iyzico_report_cancel_reason(array $report): string {
    foreach (is_array($report['payments'] ?? null) ? $report['payments'] : [] as $p) {
        if (!empty($p['cancels']) && is_array($p['cancels'])) {
            return 'iyzico iptal';
        }
        $rs = strtoupper((string)($p['paymentRefundStatus'] ?? ''));
        if (in_array($rs, ['TOTALLY_REFUNDED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
            return 'iyzico iade';
        }
    }
    return 'iyzico iade';
}

/** Panelden iade / iptal edilmiş bir ödeme mi? */
function iyzico_looks_refunded(array $d): bool {
    $status = strtoupper((string)($d['paymentStatus'] ?? $d['status'] ?? ''));
    if (in_array($status, ['FAILURE', 'FAILED', 'CANCEL', 'CANCELLED', 'CANCELED', 'REFUNDED'], true)) {
        return true;
    }
    $phase = strtoupper((string)($d['phase'] ?? ''));
    if (in_array($phase, ['CANCEL', 'CANCELLED', 'CANCELED', 'REFUND', 'REFUNDED'], true)) {
        return true;
    }
    if (!empty($d['cancel']) || !empty($d['cancelReason'])) {
        return true;
    }
    if (!empty($d['refunds']) && is_array($d['refunds'])) {
        return true;
    }
    foreach (is_array($d['itemTransactions'] ?? null) ? $d['itemTransactions'] : [] as $it) {
        if (!is_array($it)) {
            continue;
        }
        if ((int)($it['refundStatus'] ?? 0) === 1) {
            return true;
        }
        $tx = (int)($it['transactionStatus'] ?? 1);
        if ($tx === -1) {
            return true;
        }
    }
    return false;
}

/**
 * Checkout Form bildirim imzası (HPP, X-IYZ-SIGNATURE-V3).
 * İmzalı başlık yoksa null döner — çağıran karar verir.
 */
function iyzico_webhook_signature_ok(array $payload, string $headerSig): bool {
    if ($headerSig === '' || !iyzico_ready()) {
        return false;
    }
    $event = (string)($payload['iyziEventType'] ?? '');
    $paymentId = (string)($payload['iyziPaymentId'] ?? $payload['paymentId'] ?? '');
    $token = (string)($payload['token'] ?? '');
    $conv = (string)($payload['paymentConversationId'] ?? $payload['conversationId'] ?? '');
    $status = (string)($payload['status'] ?? '');

    // HPP (Checkout Form) sırası
    $hpp = hash_hmac('sha256', IYZICO_SECRET_KEY . $event . $paymentId . $token . $conv . $status, IYZICO_SECRET_KEY);
    if (hash_equals($hpp, strtolower($headerSig))) {
        return true;
    }
    // Direct format (token yok)
    $direct = hash_hmac('sha256', IYZICO_SECRET_KEY . $event . $paymentId . $conv . $status, IYZICO_SECRET_KEY);
    return hash_equals($direct, strtolower($headerSig));
}

/**
 * Öğrencinin iyzico siparişlerini iyzico'dan doğrula.
 * - Ödenmişler: panel iadesi / iptali varsa erişim kapanır
 * - Bekleyenler: ödeme hiç oluşmadıysa (kapatılan form) iptal yazılır
 */
function payments_sync_iyzico_refunds(PDO $pdo, int $studentId): int {
    if ($studentId <= 0 || !iyzico_ready()) {
        return 0;
    }
    $n = 0;

    $pending = $pdo->prepare(
        "SELECT * FROM payment_orders
         WHERE student_id = ? AND provider = 'iyzico' AND status IN ('pending', 'review')
           AND created_at < (NOW() - INTERVAL 15 MINUTE)"
    );
    $pending->execute([$studentId]);
    foreach ($pending->fetchAll() as $order) {
        $token = (string)($order['provider_token'] ?? '');
        $abandon = $token === '';
        if ($token !== '') {
            $res = iyzico_checkout_retrieve($token, (string)$order['conversation_id']);
            $code = (string)($res['data']['errorCode'] ?? '');
            $abandon = !$res['ok'] && ($res['reason'] === 'api') && ($code === '5122' || ($res['data']['paymentStatus'] ?? '') === '');
            if ($res['ok'] && !empty($res['paid'])) {
                continue;
            }
            if ($res['ok'] && iyzico_looks_refunded($res['data'])) {
                payments_revoke_enrollment($pdo, $order, 'iyzico iade');
                $n++;
                continue;
            }
        }
        if ($abandon) {
            $pdo->prepare(
                "UPDATE payment_orders SET status = 'cancelled', error_message = ? WHERE id = ? AND status IN ('pending','review')"
            )->execute(['Ödeme tamamlanmadı', (int)$order['id']]);
            $n++;
        }
    }

    $st = $pdo->prepare(
        "SELECT * FROM payment_orders
         WHERE student_id = ? AND provider = 'iyzico' AND status = 'paid'
           AND provider_payment_id <> ''"
    );
    $st->execute([$studentId]);
    foreach ($st->fetchAll() as $order) {
        $report = iyzico_payment_report((string)$order['provider_payment_id']);
        if ($report['ok'] && iyzico_report_looks_refunded($report['data'])) {
            payments_revoke_enrollment($pdo, $order, iyzico_report_cancel_reason($report['data']));
            $n++;
            continue;
        }
        $res = iyzico_payment_retrieve(
            (string)$order['provider_payment_id'],
            (string)$order['conversation_id']
        );
        if ($res['ok'] && iyzico_looks_refunded($res['data'])) {
            payments_revoke_enrollment($pdo, $order, 'iyzico iade');
            $n++;
        }
    }
    return $n;
}

function payments_sync_iyzico_refunds_all(PDO $pdo, int $limit = 40): int {
    if (!iyzico_ready()) {
        return 0;
    }
    $limit = max(1, min(80, $limit));
    $st = $pdo->query(
        "SELECT DISTINCT student_id FROM payment_orders
         WHERE provider = 'iyzico' AND student_id > 0
           AND status IN ('paid', 'pending', 'review')
         ORDER BY id DESC
         LIMIT $limit"
    );
    $n = 0;
    foreach ($st->fetchAll() as $r) {
        $n += payments_sync_iyzico_refunds($pdo, (int)$r['student_id']);
    }
    return $n;
}

/**
 * v2 yanıtında asıl kayıt çoğu zaman `data` içindedir.
 *
 * @return array<string,mixed>
 */
function iyzico_v2_data(array $response): array {
    return (isset($response['data']) && is_array($response['data'])) ? $response['data'] : $response;
}

function iyzico_sub_addon_hint(string $error): string {
    $e = mb_strtolower($error);
    if (
        str_contains($e, 'subscription')
        || str_contains($e, 'abonelik')
        || str_contains($e, 'not enabled')
        || str_contains($e, 'yetki')
        || str_contains($e, 'permission')
    ) {
        return $error . ' Sandbox abonelik ürünü kapalıysa entegrasyon@iyzico.com adresine yazın.';
    }
    return $error;
}

/**
 * Abonelik Checkout Form oturumu başlat.
 *
 * @return array{ok:bool, token:string, paymentPageUrl:string, formContent:string, error:string, data:array}
 */
function iyzico_sub_checkout_initialize(array $payload): array {
    $empty = ['ok' => false, 'token' => '', 'paymentPageUrl' => '', 'formContent' => '', 'error' => '', 'data' => []];
    $res = iyzico_request(IYZICO_PATH_SUB_CF_INIT, $payload);
    if (!$res['ok']) {
        $empty['error'] = iyzico_sub_addon_hint($res['error']);
        $empty['data'] = $res['data'];
        return $empty;
    }
    $top = $res['data'];
    $d = iyzico_v2_data($top);
    $token = (string)($d['token'] ?? $top['token'] ?? '');
    $form = (string)($d['checkoutFormContent'] ?? $top['checkoutFormContent'] ?? '');
    $pageUrl = (string)($d['paymentPageUrl'] ?? $top['paymentPageUrl'] ?? '');
    if ($token === '' || ($form === '' && $pageUrl === '')) {
        $empty['error'] = 'iyzico abonelik ödeme formu bilgisi eksik döndü.';
        $empty['data'] = $top;
        $empty['token'] = $token;
        return $empty;
    }
    return [
        'ok' => true,
        'token' => $token,
        'paymentPageUrl' => $pageUrl,
        'formContent' => $form,
        'error' => '',
        'data' => $top,
    ];
}

/**
 * Abonelik Checkout Form sonucunu iyzico'dan sor.
 *
 * Önce GET /v2/subscription/checkoutform/{token}, olmazsa POST retrieve.
 *
 * @return array{ok:bool, active:bool, error:string, reason:string, data:array, inner:array}
 */
function iyzico_sub_checkout_retrieve(string $token, string $conversationId = ''): array {
    $token = trim($token);
    if ($token === '') {
        return ['ok' => false, 'active' => false, 'error' => 'Jeton yok.', 'reason' => 'config', 'data' => [], 'inner' => []];
    }

    // Belgelenen uç GET /v2/subscription/checkoutform/{token}. POST retrieve bazı
    // hesaplarda "Sistem hatası" döndürüyor; bu yüzden GET birincil, POST yedektir.
    // İki uçtan biri başarılıysa sonuç ondan okunur; 'api' hatası tek başına
    // aboneliğin başarısız olduğu anlamına gelmez.
    $res = iyzico_get('/v2/subscription/checkoutform/' . rawurlencode($token));
    if (!$res['ok']) {
        $body = ['locale' => 'tr', 'token' => $token];
        if ($conversationId !== '') {
            $body['conversationId'] = $conversationId;
        }
        $alt = iyzico_request(IYZICO_PATH_SUB_CF_RETRIEVE, $body);
        if ($alt['ok']) {
            $res = $alt;
        }
    }
    if (!$res['ok']) {
        return [
            'ok' => false,
            'active' => false,
            'error' => $res['error'],
            'reason' => $res['reason'],
            'data' => $res['data'],
            'inner' => [],
        ];
    }
    $inner = iyzico_v2_data($res['data']);
    $st = strtoupper((string)($inner['subscriptionStatus'] ?? $inner['status'] ?? ''));
    $ref = (string)($inner['subscriptionReferenceCode'] ?? $inner['referenceCode'] ?? '');
    $active = $ref !== '' && in_array($st, ['ACTIVE', 'SUCCESS', ''], true);
    if ($ref !== '' && ($st === '' || $st === 'SUCCESS')) {
        $active = true;
    }
    if ($st !== '' && !in_array($st, ['ACTIVE', 'SUCCESS'], true)) {
        $active = false;
    }
    return [
        'ok' => true,
        'active' => $active,
        'error' => '',
        'reason' => '',
        'data' => $res['data'],
        'inner' => $inner,
    ];
}

function iyzico_sub_product_create(string $name, string $description): array {
    return iyzico_request(IYZICO_PATH_SUB_PRODUCTS, [
        'locale' => 'tr',
        'conversationId' => payments_new_reference('PRD'),
        'name' => mb_substr($name, 0, 100),
        'description' => mb_substr($description !== '' ? $description : $name, 0, 250),
    ]);
}

/** iyzico "ürün/plan zaten var" benzeri hataları yakala */
function iyzico_sub_error_is_duplicate(string $error): bool {
    $e = mb_strtolower(trim($error));
    if ($e === '') {
        return false;
    }
    foreach (['zaten var', 'already exist', 'already exists', 'duplicate'] as $needle) {
        if (str_contains($e, $needle)) {
            return true;
        }
    }
    return false;
}

/**
 * Abonelik ürünlerini listele (sayfalanmış).
 *
 * @return array<int, array<string,mixed>>
 */
function iyzico_sub_products_list(int $page = 1, int $count = 50): array {
    $res = iyzico_get(IYZICO_PATH_SUB_PRODUCTS, [
        'page' => max(1, $page),
        'count' => max(1, min(100, $count)),
    ]);
    if (!$res['ok']) {
        return [];
    }
    $data = iyzico_v2_data($res['data']);
    $items = $data['items'] ?? $res['data']['items'] ?? [];
    return is_array($items) ? $items : [];
}

/** Ürün adına göre mevcut abonelik ürününü bul (tam eşleşme, sonra kısmi). */
function iyzico_sub_product_find_by_name(string $name): ?array {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $target = mb_strtolower($name);
    $partial = null;
    for ($page = 1; $page <= 5; $page++) {
        $items = iyzico_sub_products_list($page, 50);
        if ($items === []) {
            break;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemName = mb_strtolower(trim((string)($item['name'] ?? '')));
            if ($itemName === $target) {
                return $item;
            }
            if ($partial === null && $itemName !== '' && (str_contains($itemName, $target) || str_contains($target, $itemName))) {
                $partial = $item;
            }
        }
        if (count($items) < 50) {
            break;
        }
    }
    return $partial;
}

/**
 * Ürün kaydındaki planlardan fiyat + döneme uyan referansı seç.
 *
 * Deneme süreli (trialPeriodDays > 0) plan KABUL EDİLMEZ: abonelik "aktif"
 * görünür ama ilk tahsilat yapılmaz, iyzico İşlem Listesi'nde kayıt oluşmaz.
 * Aynı sebeple yalnızca RECURRING + TRY planlar kullanılır. Uygun plan
 * bulunamazsa boş döner ve çağıran yeni plan oluşturur.
 *
 * $excludeRef, ödeme başlatmayı reddetmiş planın tekrar seçilmesini engeller.
 */
function iyzico_sub_plan_pick(array $product, string $price, string $interval, string $excludeRef = ''): string {
    $priceNorm = iyzico_trailing_zero($price);
    $interval = strtoupper($interval);
    $excludeRef = trim($excludeRef);
    $plans = is_array($product['pricingPlans'] ?? null) ? $product['pricingPlans'] : [];
    foreach ($plans as $plan) {
        if (!is_array($plan)) {
            continue;
        }
        if ($excludeRef !== '' && trim((string)($plan['referenceCode'] ?? '')) === $excludeRef) {
            continue;
        }
        $planPrice = iyzico_trailing_zero($plan['price'] ?? '');
        $planInterval = strtoupper((string)($plan['paymentInterval'] ?? ''));
        $status = strtoupper((string)($plan['status'] ?? 'ACTIVE'));
        $trial = (int)($plan['trialPeriodDays'] ?? 0);
        $payType = strtoupper((string)($plan['planPaymentType'] ?? 'RECURRING'));
        $currency = strtoupper((string)($plan['currencyCode'] ?? 'TRY'));
        $intervalCount = (int)($plan['paymentIntervalCount'] ?? 1);
        if ($planPrice !== $priceNorm || $planInterval !== $interval) {
            continue;
        }
        if ($status === 'DELETED' || $status === 'PASSIVE') {
            continue;
        }
        if ($trial > 0 || $payType !== 'RECURRING' || $currency !== 'TRY') {
            continue;
        }
        if ($intervalCount > 1) {
            continue;
        }
        $ref = trim((string)($plan['referenceCode'] ?? ''));
        if ($ref !== '') {
            return $ref;
        }
    }
    return '';
}

function iyzico_sub_plan_create(string $productRef, string $name, string $price, string $interval): array {
    $path = IYZICO_PATH_SUB_PRODUCTS . '/' . rawurlencode($productRef) . '/pricing-plans';
    return iyzico_request($path, [
        'locale' => 'tr',
        'conversationId' => payments_new_reference('PLN'),
        'name' => mb_substr($name, 0, 100),
        'price' => $price,
        'currencyCode' => 'TRY',
        'paymentInterval' => $interval,
        'paymentIntervalCount' => 1,
        'planPaymentType' => 'RECURRING',
        'trialPeriodDays' => 0,
    ]);
}

function iyzico_sub_cancel(string $subscriptionRef): array {
    $ref = trim($subscriptionRef);
    if ($ref === '') {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Abonelik referansı yok.', 'reason' => 'config'];
    }
    $path = '/v2/subscription/subscriptions/' . rawurlencode($ref) . '/cancel';
    return iyzico_request($path, ['locale' => 'tr']);
}

function iyzico_sub_detail(string $subscriptionRef): array {
    $ref = trim($subscriptionRef);
    if ($ref === '') {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Abonelik referansı yok.', 'reason' => 'config'];
    }
    return iyzico_get('/v2/subscription/subscriptions/' . rawurlencode($ref));
}

/**
 * Abonelik webhook imzası (merchantId + secret + event + refs).
 * IYZICO_MERCHANT_ID tanımlı değilse false döner.
 */
function iyzico_subscription_webhook_signature_ok(array $payload, string $headerSig): bool {
    if ($headerSig === '' || !iyzico_ready()) {
        return false;
    }
    $merchant = defined('IYZICO_MERCHANT_ID') ? trim((string)IYZICO_MERCHANT_ID) : '';
    if ($merchant === '') {
        return false;
    }
    $event = (string)($payload['iyziEventType'] ?? '');
    $sub = (string)($payload['subscriptionReferenceCode'] ?? '');
    $order = (string)($payload['orderReferenceCode'] ?? '');
    $cust = (string)($payload['customerReferenceCode'] ?? '');
    $msg = $merchant . IYZICO_SECRET_KEY . $event . $sub . $order . $cust;
    $hex = hash_hmac('sha256', $msg, IYZICO_SECRET_KEY);
    return hash_equals(strtolower($hex), strtolower($headerSig));
}
