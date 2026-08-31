<?php
/**
 * Anonim ziyaretçi analitiği.
 *
 * Ziyaretçiye isim/e-posta atanmaz. Kalıcı anonim ID yalnızca aynı
 * ziyaretçinin sonraki oturumlarını ilişkilendirmek için kullanılır.
 */

function analytics_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS page_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45),
            visitor_id VARCHAR(16) NOT NULL DEFAULT '',
            path VARCHAR(255),
            referrer VARCHAR(255) NOT NULL DEFAULT '',
            source VARCHAR(40) NOT NULL DEFAULT '',
            city VARCHAR(80) NOT NULL DEFAULT '',
            title VARCHAR(160) NOT NULL DEFAULT '',
            ua VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_pv_visitor (visitor_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    foreach ([
        ['visitor_id', "VARCHAR(16) NOT NULL DEFAULT '' AFTER ip"],
        ['referrer', "VARCHAR(255) NOT NULL DEFAULT '' AFTER path"],
        ['source', "VARCHAR(40) NOT NULL DEFAULT '' AFTER referrer"],
        ['city', "VARCHAR(80) NOT NULL DEFAULT '' AFTER source"],
        ['title', "VARCHAR(160) NOT NULL DEFAULT '' AFTER city"],
    ] as [$column, $definition]) {
        try {
            egitmen_add_column_if_missing($pdo, 'page_views', $column, $definition);
        } catch (Throwable $e) {
            error_log('analitik kolon: ' . $e->getMessage());
        }
    }

    try {
        $pdo->exec("CREATE INDEX idx_pv_visitor ON page_views (visitor_id, created_at)");
    } catch (Throwable $e) {
        // Index mevcutsa devam et.
    }

    try {
        $pdo->exec("CREATE INDEX idx_pv_source ON page_views (source)");
    } catch (Throwable $e) {
        // Index mevcutsa devam et.
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ip_geo (
                ip VARCHAR(45) PRIMARY KEY,
                city VARCHAR(80) NOT NULL DEFAULT '',
                country VARCHAR(80) NOT NULL DEFAULT '',
                fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $e) {
        error_log('analitik geo tablosu: ' . $e->getMessage());
    }
}

function analytics_visitor_id(?string $provided = null): string {
    $raw = strtoupper((string)($provided ?? ''));
    $raw = preg_replace('/[^A-F0-9]/', '', $raw) ?? '';
    if (strlen($raw) >= 8) {
        return substr($raw, 0, 16);
    }

    try {
        return strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $e) {
        return strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    }
}

function analytics_source_from_referrer(string $referrer, string $siteHost = ''): string {
    $referrer = trim($referrer);
    if ($referrer === '') {
        return 'Direct';
    }

    $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    if ($host === '') {
        return 'Direct';
    }
    $siteHost = strtolower(trim($siteHost));
    $siteHost = preg_replace('/^www\./', '', $siteHost) ?? $siteHost;
    if ($siteHost !== '' && $host === $siteHost) {
        return 'Direct';
    }
    if (str_contains($host, 'google.')) {
        return 'Google';
    }
    if (str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) {
        return 'Twitter / X';
    }
    if (str_contains($host, 'instagram.com')) {
        return 'Instagram';
    }
    if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
        return 'Facebook';
    }
    if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
        return 'YouTube';
    }
    return 'Diğer';
}

function analytics_public_ip(string $ip): bool {
    return $ip !== ''
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function analytics_lookup_city(PDO $pdo, string $ip): string {
    if (!analytics_public_ip($ip)) {
        return '';
    }

    try {
        $st = $pdo->prepare("SELECT city FROM ip_geo WHERE ip = ? LIMIT 1");
        $st->execute([$ip]);
        $cached = trim((string)$st->fetchColumn());
        if ($cached !== '') {
            return $cached;
        }
    } catch (Throwable $e) {
        return '';
    }

    $city = '';
    $country = '';
    $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
    try {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'BMCapital-Analytics/1.0',
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            $json = is_string($body) ? json_decode($body, true) : null;
            if (is_array($json)) {
                $city = mb_substr(trim((string)($json['city'] ?? '')), 0, 80);
                $country = mb_substr(trim((string)($json['country_name'] ?? '')), 0, 80);
            }
        }
    } catch (Throwable $e) {
        // GeoIP başarısızlığı ziyaret kaydını engellemez.
    }

    try {
        $pdo->prepare(
            "INSERT INTO ip_geo (ip, city, country) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE city = VALUES(city), country = VALUES(country), fetched_at = NOW()"
        )->execute([$ip, $city, $country]);
    } catch (Throwable $e) {
    }
    return $city;
}

function analytics_unique_expr(): string {
    return "COALESCE(NULLIF(visitor_id, ''), CONCAT('ip:', COALESCE(ip, '')))";
}

function analytics_path_label(string $path, string $title = ''): string {
    $pathOnly = strtolower((string)parse_url($path, PHP_URL_PATH));
    if ($pathOnly === '' || $pathOnly === '/') {
        return 'Ana Sayfa';
    }
    if (str_contains($pathOnly, 'egitim-detay')) {
        return $title !== '' ? $title : 'Eğitim Detayı';
    }
    if (str_contains($pathOnly, 'egitimler')) {
        return 'Eğitimler';
    }
    if (str_contains($pathOnly, 'urun-detay')) {
        return $title !== '' ? $title : 'Ürün Detayı';
    }
    if (str_contains($pathOnly, 'urunler')) {
        return 'Ürünler';
    }
    if (str_contains($pathOnly, 'araclar')) {
        return 'Borsa Araçları';
    }
    if (str_contains($pathOnly, 'iletisim')) {
        return 'İletişim';
    }
    if (str_contains($pathOnly, 'abonelik')) {
        return 'Abonelik';
    }
    return trim(basename($pathOnly), '/') ?: 'Ana Sayfa';
}

function analytics_session_status(string $path, int $lastTimestamp): string {
    if ($lastTimestamp < (time() - 600)) {
        return 'Ayrıldı';
    }
    $path = strtolower($path);
    if (str_contains($path, 'egitim')) {
        return 'İnceliyor';
    }
    if (str_contains($path, 'urun')) {
        return 'Ürün baktı';
    }
    return 'Geziyor';
}

function analytics_build_sessions(array $rows, int $gapSeconds = 1800): array {
    $sessions = [];
    $open = [];
    foreach ($rows as $row) {
        $visitor = trim((string)($row['visitor_id'] ?? ''));
        if ($visitor === '') {
            $visitor = 'IP:' . hash('sha256', (string)($row['ip'] ?? ''));
        }
        $timestamp = strtotime((string)($row['created_at'] ?? '')) ?: time();
        $lastIndex = $open[$visitor] ?? null;
        if (
            $lastIndex === null
            || ($timestamp - $sessions[$lastIndex]['lastTimestamp']) > $gapSeconds
        ) {
            $sessions[] = [
                'visitor' => $visitor,
                'visitorId' => trim((string)($row['visitor_id'] ?? '')),
                'firstTimestamp' => $timestamp,
                'lastTimestamp' => $timestamp,
                'first' => (string)($row['created_at'] ?? ''),
                'last' => (string)($row['created_at'] ?? ''),
                'city' => (string)($row['city'] ?? ''),
                'source' => (string)($row['source'] ?? ''),
                'events' => [],
                'paths' => [],
            ];
            $lastIndex = array_key_last($sessions);
            $open[$visitor] = $lastIndex;
        }

        $session =& $sessions[$lastIndex];
        $session['lastTimestamp'] = $timestamp;
        $session['last'] = (string)($row['created_at'] ?? '');
        if ($session['city'] === '' && !empty($row['city'])) {
            $session['city'] = (string)$row['city'];
        }
        if ($session['source'] === '' && !empty($row['source'])) {
            $session['source'] = (string)$row['source'];
        }
        $session['events'][] = [
            'time' => (string)($row['created_at'] ?? ''),
            'path' => (string)($row['path'] ?? '/'),
            'title' => (string)($row['title'] ?? ''),
        ];
        $session['paths'][(string)($row['path'] ?? '/')] = true;
        unset($session);
    }
    usort($sessions, static function (array $a, array $b): int {
        return $a['firstTimestamp'] <=> $b['firstTimestamp'];
    });
    return $sessions;
}

function analytics_session_public(array $session): array {
    $lastEvent = end($session['events']) ?: [];
    $lastPath = (string)($lastEvent['path'] ?? '/');
    $firstTimestamp = (int)$session['firstTimestamp'];
    $lastTimestamp = (int)$session['lastTimestamp'];
    $purchase = false;
    foreach ($session['events'] as $event) {
        $path = strtolower((string)($event['path'] ?? ''));
        if (str_contains($path, 'odeme-basarili') || str_contains($path, 'payment_success')) {
            $purchase = true;
            break;
        }
    }
    return [
        'id' => $session['visitorId'] !== '' ? $session['visitorId'] : substr($session['visitor'], -8),
        'label' => '#' . substr($session['visitorId'] !== '' ? $session['visitorId'] : $session['visitor'], -4),
        'first' => $session['first'],
        'last' => $session['last'],
        'lastPage' => analytics_path_label($lastPath, (string)($lastEvent['title'] ?? '')),
        'pageCount' => count($session['events']),
        'durationSeconds' => max(0, $lastTimestamp - $firstTimestamp),
        'status' => analytics_session_status($lastPath, $lastTimestamp),
        'purchase' => $purchase,
        'city' => $session['city'] !== '' ? $session['city'] : 'Bilinmiyor',
        'source' => $session['source'] !== '' ? $session['source'] : 'Direct',
    ];
}

function analytics_dashboard(PDO $pdo): array {
    analytics_ensure_schema($pdo);
    $uniq = analytics_unique_expr();
    $today = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $uniqToday = (int)$pdo->query(
        "SELECT COUNT(DISTINCT $uniq) FROM page_views WHERE DATE(created_at) = CURDATE()"
    )->fetchColumn();
    $week = (int)$pdo->query(
        "SELECT COUNT(*) FROM page_views WHERE created_at > (NOW() - INTERVAL 7 DAY)"
    )->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM page_views")->fetchColumn();

    $daily = [];
    $rows = $pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) c, COUNT(DISTINCT $uniq) u
         FROM page_views WHERE created_at > (NOW() - INTERVAL 30 DAY)
         GROUP BY DATE(created_at) ORDER BY d ASC"
    )->fetchAll();
    foreach ($rows as $row) {
        $daily[$row['d']] = ['views' => (int)$row['c'], 'unique' => (int)$row['u']];
    }
    $series = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $series[] = [
            'date' => $date,
            'views' => $daily[$date]['views'] ?? 0,
            'unique' => $daily[$date]['unique'] ?? 0,
        ];
    }

    $topPages = $pdo->query(
        "SELECT path, COUNT(*) c FROM page_views
         WHERE created_at > (NOW() - INTERVAL 30 DAY) AND path <> ''
         GROUP BY path ORDER BY c DESC LIMIT 8"
    )->fetchAll();

    $firstInWindow = "id IN (
        SELECT MIN(pv_first.id) FROM page_views pv_first
        WHERE pv_first.created_at > (NOW() - INTERVAL 30 DAY)
        GROUP BY COALESCE(NULLIF(pv_first.visitor_id, ''), CONCAT('ip:', COALESCE(pv_first.ip, '')))
    )";
    $sourceRows = $pdo->query(
        "SELECT CASE WHEN source IS NULL OR source = '' THEN 'Direct' ELSE source END src,
                COUNT(*) c
         FROM page_views WHERE created_at > (NOW() - INTERVAL 30 DAY) AND $firstInWindow
         GROUP BY src ORDER BY c DESC LIMIT 12"
    )->fetchAll();
    $sourceTotal = array_sum(array_map(static fn($row): int => (int)$row['c'], $sourceRows));
    $sources = [];
    foreach ($sourceRows as $row) {
        $count = (int)$row['c'];
        $sources[] = [
            'name' => (string)$row['src'],
            'count' => $count,
            'pct' => $sourceTotal > 0 ? (int)round($count * 100 / $sourceTotal) : 0,
        ];
    }

    $cityRows = $pdo->query(
        "SELECT CASE WHEN city IS NULL OR city = '' THEN 'Bilinmiyor' ELSE city END cty,
                COUNT(*) c
         FROM page_views WHERE created_at > (NOW() - INTERVAL 30 DAY) AND $firstInWindow
         GROUP BY cty ORDER BY c DESC"
    )->fetchAll();
    $cities = [];
    $other = 0;
    foreach ($cityRows as $index => $row) {
        if ($index < 4) {
            $cities[] = ['name' => (string)$row['cty'], 'count' => (int)$row['c']];
        } else {
            $other += (int)$row['c'];
        }
    }
    if ($other > 0) {
        $cities[] = ['name' => 'Diğer', 'count' => $other];
    }

    $todayRows = $pdo->query(
        "SELECT visitor_id, ip, path, title, city, source, created_at
         FROM page_views WHERE DATE(created_at) = CURDATE()
         ORDER BY created_at ASC"
    )->fetchAll();
    $visitors = array_map(
        'analytics_session_public',
        array_slice(array_reverse(analytics_build_sessions($todayRows)), 0, 40)
    );

    return [
        'series' => $series,
        'topPages' => $topPages,
        'sources' => $sources,
        'cities' => $cities,
        'visitors' => $visitors,
        'cardsExtra' => [
            'today' => $today,
            'uniqueToday' => $uniqToday,
            'week' => $week,
            'total' => $total,
        ],
    ];
}

function analytics_visitor_detail(PDO $pdo, string $visitorId): ?array {
    analytics_ensure_schema($pdo);
    $visitorId = strtoupper(preg_replace('/[^a-f0-9]/i', '', $visitorId) ?? '');
    if (strlen($visitorId) < 8) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT visitor_id, path, title, created_at
         FROM page_views
         WHERE visitor_id = ?
            OR (visitor_id = '' AND RIGHT(SHA2(COALESCE(ip, ''), 256), 8) = ?)
         ORDER BY created_at ASC LIMIT 300"
    );
    $st->execute([$visitorId, strtolower($visitorId)]);
    $rows = $st->fetchAll();
    if (!$rows) {
        return null;
    }

    $events = [];
    $purchase = false;
    foreach ($rows as $row) {
        $path = (string)$row['path'];
        $lower = strtolower($path);
        if (str_contains($lower, 'odeme-basarili') || str_contains($lower, 'payment_success')) {
            $purchase = true;
        }
        $events[] = [
            'time' => (string)$row['created_at'],
            'page' => analytics_path_label($path, (string)($row['title'] ?? '')),
        ];
    }
    $first = strtotime((string)$rows[0]['created_at']) ?: time();
    $last = strtotime((string)$rows[count($rows) - 1]['created_at']) ?: $first;
    return [
        'label' => '#' . substr($visitorId, -4),
        'first' => (string)$rows[0]['created_at'],
        'last' => (string)$rows[count($rows) - 1]['created_at'],
        'durationSeconds' => max(0, $last - $first),
        'pageCount' => count($events),
        'purchase' => $purchase,
        'events' => $events,
    ];
}
