<?php
/**
 * Ziyaretçi analitiği — anonim ID, kaynak, şehir, oturum.
 */
require_once __DIR__ . '/egitmen_schema.php';

function analytics_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45),
        path VARCHAR(255),
        ua VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing($pdo, 'page_views', 'visitor_id', "VARCHAR(16) NOT NULL DEFAULT '' AFTER ip");
    egitmen_add_column_if_missing($pdo, 'page_views', 'referrer', "VARCHAR(255) NOT NULL DEFAULT '' AFTER path");
    egitmen_add_column_if_missing($pdo, 'page_views', 'source', "VARCHAR(40) NOT NULL DEFAULT '' AFTER referrer");
    egitmen_add_column_if_missing($pdo, 'page_views', 'city', "VARCHAR(80) NOT NULL DEFAULT '' AFTER source");
    egitmen_add_column_if_missing($pdo, 'page_views', 'title', "VARCHAR(160) NOT NULL DEFAULT '' AFTER city");

    try {
        $pdo->exec("CREATE INDEX idx_pv_visitor ON page_views (visitor_id, created_at)");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("CREATE INDEX idx_pv_source ON page_views (source)");
    } catch (Throwable $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ip_geo (
        ip VARCHAR(45) PRIMARY KEY,
        city VARCHAR(80) NOT NULL DEFAULT '',
        country VARCHAR(80) NOT NULL DEFAULT '',
        fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function analytics_visitor_key(array $row): string {
    $vid = strtoupper(trim((string)($row['visitor_id'] ?? '')));
    if ($vid !== '') {
        return 'v:' . $vid;
    }
    $ip = trim((string)($row['ip'] ?? ''));
    return $ip !== '' ? 'ip:' . $ip : 'x:' . (string)($row['id'] ?? '0');
}

function analytics_visitor_label(array $row): string {
    $vid = strtoupper(preg_replace('/[^A-F0-9]/', '', (string)($row['visitor_id'] ?? '')));
    if (strlen($vid) >= 4) {
        return '#' . substr($vid, 0, 8);
    }
    $ip = (string)($row['ip'] ?? '');
    return '#' . strtoupper(substr(hash('sha256', 'bmvid|' . $ip), 0, 4));
}

function analytics_unique_expr(): string {
    return "CASE WHEN visitor_id IS NOT NULL AND visitor_id <> '' THEN visitor_id ELSE CONCAT('ip:', IFNULL(ip,'')) END";
}

function analytics_source_from_referrer(string $referrer, string $host): string {
    $ref = trim($referrer);
    if ($ref === '') {
        return 'Direct';
    }
    $h = strtolower((string)(parse_url($ref, PHP_URL_HOST) ?? ''));
    $h = preg_replace('/^www\./', '', $h);
    $own = strtolower(preg_replace('/^www\./', '', $host));
    if ($h === '' || $h === $own || str_ends_with($h, '.' . $own)) {
        return 'Direct';
    }
    if (str_contains($h, 'google.') || $h === 'google.com') {
        return 'Google';
    }
    if (str_contains($h, 'bing.')) {
        return 'Bing';
    }
    if (str_contains($h, 'yandex.')) {
        return 'Yandex';
    }
    if (str_contains($h, 'twitter.') || $h === 't.co' || $h === 'x.com' || str_ends_with($h, '.x.com')) {
        return 'Twitter';
    }
    if (str_contains($h, 'facebook.') || $h === 'fb.com' || $h === 'l.facebook.com') {
        return 'Facebook';
    }
    if (str_contains($h, 'instagram.') || $h === 'l.instagram.com') {
        return 'Instagram';
    }
    if (str_contains($h, 'linkedin.') || $h === 'lnkd.in') {
        return 'LinkedIn';
    }
    if (str_contains($h, 'youtube.') || $h === 'youtu.be') {
        return 'YouTube';
    }
    if (str_contains($h, 'tiktok.')) {
        return 'TikTok';
    }
    if (str_contains($h, 'whatsapp.') || $h === 'wa.me') {
        return 'WhatsApp';
    }
    return ucfirst($h);
}

function analytics_path_label(string $path, string $title = ''): string {
    $p = strtolower((string)(parse_url($path, PHP_URL_PATH) ?: $path));
    $p = '/' . ltrim($p, '/');
    $map = [
        '/' => 'Ana Sayfa',
        '/index.html' => 'Ana Sayfa',
        '/egitimler.html' => 'Eğitimler',
        '/egitim-detay.html' => 'Eğitim',
        '/urunler.html' => 'Ürünler',
        '/urun-detay.html' => 'Ürün',
        '/hakkimizda.html' => 'Hakkımızda',
        '/sss.html' => 'S.S.S.',
        '/iletisim.html' => 'İletişim',
        '/abonelik.html' => 'Abonelik',
        '/odeme.php' => 'Ödeme',
        '/odeme.html' => 'Ödeme',
        '/araclar.html' => 'Araçlar',
        '/ogrenci/giris.php' => 'Öğrenci girişi',
        '/ogrenci/kayit.php' => 'Kayıt',
        '/ogrenci/sepetim.php' => 'Sepet',
        '/ogrenci/index.php' => 'Kurslarım',
        '/ogrenci/abonelik.php' => 'Abone ol',
        '/ogrenci/aboneliklerim.php' => 'Aboneliklerim',
        '/ogrenci/satin-alma-gecmisi.php' => 'Satın alma',
    ];
    if (isset($map[$p])) {
        return $map[$p];
    }
    if (str_contains($p, 'egitim-detay')) {
        return analytics_clean_title($title) ?: 'Eğitim';
    }
    if (str_contains($p, 'urun-detay')) {
        return analytics_clean_title($title) ?: 'Ürün';
    }
    if (str_contains($p, '/ogrenci/ders')) {
        return 'Ders içeriği';
    }
    if (str_contains($p, 'odeme')) {
        return 'Ödeme';
    }
    $clean = analytics_clean_title($title);
    return $clean !== '' ? $clean : $p;
}

function analytics_clean_title(string $title): string {
    $t = trim($title);
    if ($t === '') {
        return '';
    }
    $t = preg_replace('/\s*[\|\-–]\s*BM Capital.*$/iu', '', $t);
    $t = preg_replace('/\s*\|\s*.*$/', '', $t);
    return trim((string)$t);
}

function analytics_is_private_ip(string $ip): bool {
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function analytics_lookup_city(PDO $pdo, string $ip): string {
    if ($ip === '' || analytics_is_private_ip($ip)) {
        return 'Yerel';
    }
    try {
        $st = $pdo->prepare("SELECT city, fetched_at FROM ip_geo WHERE ip = ? LIMIT 1");
        $st->execute([$ip]);
        $row = $st->fetch();
        if ($row && strtotime((string)$row['fetched_at']) > time() - 30 * 86400) {
            return (string)$row['city'];
        }
    } catch (Throwable $e) {
    }

    $city = '';
    $country = '';
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,city&lang=tr';
    $raw = analytics_http_get($url, 1.4);
    if ($raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j) && ($j['status'] ?? '') === 'success') {
            $city = mb_substr(trim((string)($j['city'] ?? '')), 0, 80);
            $country = mb_substr(trim((string)($j['country'] ?? '')), 0, 80);
        }
    }
    if ($city === '') {
        $city = 'Bilinmiyor';
    }
    try {
        $pdo->prepare(
            "INSERT INTO ip_geo (ip, city, country, fetched_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE city = VALUES(city), country = VALUES(country), fetched_at = NOW()"
        )->execute([$ip, $city, $country]);
    } catch (Throwable $e) {
    }
    return $city;
}

function analytics_http_get(string $url, float $timeout): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => (int)max(1, ceil($timeout)),
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $out = curl_exec($ch);
        curl_close($ch);
        return is_string($out) ? $out : '';
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $out = @file_get_contents($url, false, $ctx);
    return is_string($out) ? $out : '';
}

function analytics_status(array $session): array {
    $lastTs = (int)($session['last_ts'] ?? 0);
    $gone = $lastTs > 0 && (time() - $lastTs) > 8 * 60;
    $bought = !empty($session['bought']);
    $lastPath = strtolower((string)($session['last_path'] ?? ''));
    if ($gone) {
        return ['key' => 'left', 'label' => '❌ Ayrıldı'];
    }
    if ($bought || str_contains($lastPath, 'odeme') || str_contains($lastPath, 'sepet')) {
        return ['key' => 'buy', 'label' => '🛒 Ürün baktı'];
    }
    if (str_contains($lastPath, 'urun')) {
        return ['key' => 'product', 'label' => '🛒 Ürün baktı'];
    }
    if (str_contains($lastPath, 'egitim') || str_contains($lastPath, 'ders')) {
        return ['key' => 'course', 'label' => '🎓 İnceliyor'];
    }
    return ['key' => 'browse', 'label' => '👀 Geziyor'];
}

function analytics_is_buy_path(string $path): bool {
    $p = strtolower($path);
    return str_contains($p, 'odeme') || str_contains($p, 'sepet') || str_contains($p, 'satin-alma');
}

function analytics_build_sessions(array $rows, int $gapSec = 1800): array {
    $by = [];
    foreach ($rows as $r) {
        $by[analytics_visitor_key($r)][] = $r;
    }
    $sessions = [];
    foreach ($by as $list) {
        $cur = null;
        foreach ($list as $r) {
            $t = strtotime((string)$r['created_at']) ?: 0;
            if (!$cur || ($t - (int)$cur['last_ts']) > $gapSec) {
                if ($cur) {
                    $sessions[] = $cur;
                }
                $cur = analytics_new_session($r, $t);
            } else {
                $cur['last_ts'] = $t;
                $cur['last_at'] = (string)$r['created_at'];
                $cur['last_path'] = (string)$r['path'];
                $cur['last_title'] = analytics_path_label((string)$r['path'], (string)($r['title'] ?? ''));
                $cur['pages'][] = $r;
                if (analytics_is_buy_path((string)$r['path'])) {
                    $cur['bought'] = true;
                }
                if (($cur['city'] ?? '') === '' && trim((string)($r['city'] ?? '')) !== '') {
                    $cur['city'] = (string)$r['city'];
                }
            }
        }
        if ($cur) {
            $sessions[] = $cur;
        }
    }
    usort($sessions, static function ($a, $b) {
        return ((int)$b['last_ts']) <=> ((int)$a['last_ts']);
    });
    return $sessions;
}

function analytics_new_session(array $r, int $t): array {
    $label = analytics_path_label((string)$r['path'], (string)($r['title'] ?? ''));
    return [
        'vid' => strtoupper(trim((string)($r['visitor_id'] ?? ''))),
        'ip' => (string)($r['ip'] ?? ''),
        'label' => analytics_visitor_label($r),
        'first_ts' => $t,
        'last_ts' => $t,
        'first_at' => (string)$r['created_at'],
        'last_at' => (string)$r['created_at'],
        'last_path' => (string)$r['path'],
        'last_title' => $label,
        'city' => (string)($r['city'] ?? ''),
        'source' => (string)($r['source'] ?? ''),
        'bought' => analytics_is_buy_path((string)$r['path']),
        'pages' => [$r],
    ];
}

function analytics_session_public(array $s, bool $withPages = false): array {
    $mins = max(0, (int)floor(((int)$s['last_ts'] - (int)$s['first_ts']) / 60));
    if ($mins < 1 && count($s['pages']) > 1) {
        $mins = 1;
    }
    $st = analytics_status($s);
    $out = [
        'id' => $s['vid'] !== '' ? $s['vid'] : $s['label'],
        'label' => 'Ziyaretçi ' . $s['label'],
        'first' => date('H:i', (int)$s['first_ts']),
        'last' => date('H:i', (int)$s['last_ts']),
        'page' => $s['last_title'],
        'minutes' => $mins,
        'status' => $st['label'],
        'bought' => !empty($s['bought']),
        'city' => $s['city'] ?: '',
        'pages_count' => count($s['pages']),
    ];
    if ($withPages) {
        $out['journey'] = array_map(static function ($r) {
            $ts = strtotime((string)$r['created_at']);
            return [
                'time' => $ts ? date('H:i', $ts) : '',
                'at' => (string)$r['created_at'],
                'page' => analytics_path_label((string)$r['path'], (string)($r['title'] ?? '')),
            ];
        }, $s['pages']);
        $out['exit'] = true;
    }
    return $out;
}

function analytics_dashboard(PDO $pdo): array {
    analytics_ensure_schema($pdo);
    $uniq = analytics_unique_expr();

    $today = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $uniqToday = (int)$pdo->query("SELECT COUNT(DISTINCT $uniq) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $week = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE created_at > (NOW() - INTERVAL 7 DAY)")->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM page_views")->fetchColumn();

    $daily = [];
    $rows = $pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) c, COUNT(DISTINCT $uniq) u
         FROM page_views WHERE created_at > (NOW() - INTERVAL 30 DAY)
         GROUP BY DATE(created_at) ORDER BY d ASC"
    )->fetchAll();
    foreach ($rows as $r) {
        $daily[$r['d']] = ['views' => (int)$r['c'], 'unique' => (int)$r['u']];
    }
    $series = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $series[] = [
            'date' => $d,
            'views' => $daily[$d]['views'] ?? 0,
            'unique' => $daily[$d]['unique'] ?? 0,
        ];
    }

    $topPages = $pdo->query(
        "SELECT path, COUNT(*) c FROM page_views
         WHERE created_at > (NOW() - INTERVAL 30 DAY) AND path <> ''
         GROUP BY path ORDER BY c DESC LIMIT 8"
    )->fetchAll();

    $srcRows = $pdo->query(
        "SELECT CASE WHEN source IS NULL OR source = '' THEN 'Direct' ELSE source END src,
                COUNT(DISTINCT $uniq) c
         FROM page_views
         WHERE created_at > (NOW() - INTERVAL 30 DAY)
         GROUP BY src
         ORDER BY c DESC
         LIMIT 12"
    )->fetchAll();
    $srcTotal = 0;
    foreach ($srcRows as $r) {
        $srcTotal += (int)$r['c'];
    }
    $sources = [];
    foreach ($srcRows as $r) {
        $c = (int)$r['c'];
        $sources[] = [
            'name' => (string)$r['src'],
            'count' => $c,
            'pct' => $srcTotal > 0 ? (int)round($c * 100 / $srcTotal) : 0,
        ];
    }

    $cityRows = $pdo->query(
        "SELECT CASE WHEN city IS NULL OR city = '' THEN 'Bilinmiyor' ELSE city END cty,
                COUNT(DISTINCT $uniq) c
         FROM page_views
         WHERE created_at > (NOW() - INTERVAL 30 DAY)
         GROUP BY cty
         ORDER BY c DESC"
    )->fetchAll();
    $cities = [];
    $other = 0;
    foreach ($cityRows as $i => $r) {
        if ($i < 4) {
            $cities[] = ['name' => (string)$r['cty'], 'count' => (int)$r['c']];
        } else {
            $other += (int)$r['c'];
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
    $visitors = [];
    foreach (array_slice(analytics_build_sessions($todayRows), 0, 40) as $s) {
        $visitors[] = analytics_session_public($s, false);
    }

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

function analytics_visitor_detail(PDO $pdo, string $id): ?array {
    analytics_ensure_schema($pdo);
    $id = strtoupper(preg_replace('/[^A-F0-9#]/', '', $id));
    $id = ltrim($id, '#');
    if ($id === '') {
        return null;
    }

    $rows = [];
    if (strlen($id) >= 4) {
        $st = $pdo->prepare(
            "SELECT * FROM page_views
             WHERE visitor_id = ? OR visitor_id LIKE ?
             ORDER BY created_at ASC"
        );
        $st->execute([$id, $id . '%']);
        $rows = $st->fetchAll();
    }
    if (!$rows) {
        $recent = $pdo->query(
            "SELECT * FROM page_views WHERE created_at > (NOW() - INTERVAL 14 DAY) ORDER BY created_at ASC"
        )->fetchAll();
        $rows = array_values(array_filter($recent, static function ($r) use ($id) {
            $lab = strtoupper(ltrim(analytics_visitor_label($r), '#'));
            return $lab === $id || str_starts_with($lab, $id);
        }));
    }

    if (!$rows) {
        return null;
    }

    $sessions = analytics_build_sessions($rows);
    if (!$sessions) {
        return null;
    }
    $latest = $sessions[0];
    $detail = analytics_session_public($latest, true);
    $detail['sessions'] = array_map(static function ($s) {
        $p = analytics_session_public($s, false);
        $p['date'] = date('d.m.Y', (int)$s['first_ts']);
        return $p;
    }, array_slice($sessions, 0, 12));
    $detail['visit_count'] = count($sessions);
    $detail['total_minutes'] = $detail['minutes'];
    $detail['bought_label'] = !empty($detail['bought']) ? '✅' : '❌';
    return $detail;
}
