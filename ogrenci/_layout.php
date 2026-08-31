<?php
/**
 * Öğrenci alanı sayfa iskeleti — auth ekranları ve uygulama kabuğu.
 * Marka değerleri api/site_brand.php üzerinden gelir.
 */
require_once __DIR__ . '/../api/site_brand.php';

function ogrenci_e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Giriş sonrası dönülecek adres — yalnızca site içi göreli yol kabul edilir.
 * (Açık yönlendirme / oturum çalma girişimlerini engeller.)
 */
function ogrenci_safe_next($next): string {
    $next = trim((string)$next);
    if ($next === '') {
        return '';
    }
    if (preg_match('/[\r\n\t]/', $next)) {
        return '';
    }
    // Şema içeren (http:, javascript:) veya protokolden bağımsız (//host) adresler
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $next) || strncmp($next, '//', 2) === 0) {
        return '';
    }
    return $next;
}

/**
 * Yerel geliştirme ortamı mı? SMTP yoksa doğrulama/sıfırlama yedekleri
 * yalnızca burada ekranda gösterilir.
 */
function ogrenci_is_local_env(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = explode(':', $host)[0];
    if (in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)) {
        return true;
    }
    return (bool)preg_match('/\.(local|test|localhost)$/', $host);
}

function ogrenci_head(string $title, string $bodyClass = ''): void {
    $brand = BRAND_NAME;
    ?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ogrenci_e($title) ?> · <?= ogrenci_e($brand) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/ogrenci.css">
</head>
<body<?= $bodyClass !== '' ? ' class="' . ogrenci_e($bodyClass) . '"' : '' ?>><?php
}

function ogrenci_foot(): void {
    ?>
  <script src="assets/ogrenci.js"></script>
  <script src="../assets/js/track.js"></script>
</body>
</html><?php
}

/** Marka kilidi (logo + wordmark) */
function ogrenci_brand(bool $dark = false): void {
    $cls = $dark ? 'brand brand--dark' : 'brand';
    ?><a href="../index.html" class="<?= $cls ?>" aria-label="<?= ogrenci_e(BRAND_NAME) ?>">
    <span class="brand-mark"><?= ogrenci_e(BRAND_MARK) ?></span>
    <span class="brand-word"><?= ogrenci_e(BRAND_WORD) ?><small><?= ogrenci_e(BRAND_TAGLINE) ?></small></span>
  </a><?php
}

/**
 * Auth ekranlarının üst barı — solda marka, sağda karşıt aksiyon.
 * $current: 'giris' | 'kayit' | ''
 */
function ogrenci_auth_top(string $current = '', string $next = ''): void {
    $q = $next !== '' ? '?next=' . rawurlencode($next) : '';
    ?>
  <header class="auth-top">
    <div class="auth-top-inner">
      <?php ogrenci_brand(true); ?>
      <nav class="auth-top-links">
        <?php if ($current !== 'giris'): ?>
          <a href="giris.php<?= ogrenci_e($q) ?>">Giriş Yap</a>
        <?php endif; ?>
        <?php if ($current !== 'kayit'): ?>
          <a href="kayit.php<?= ogrenci_e($q) ?>" class="is-cta">Kayıt Ol</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
    <?php
}

/**
 * Sosyal giriş butonları + ayırıcı.
 *
 * Anahtarlar tanımlı değilse canlıda hiç basılmaz (ölü buton olmasın);
 * yerelde kurulum hatırlatmasıyla devre dışı gösterilir.
 */
function ogrenci_social_buttons(string $next = ''): void {
    require_once __DIR__ . '/../api/oauth_config.php';

    $ready = oauth_google_ready();
    if (!$ready && !ogrenci_is_local_env()) {
        return;
    }

    $href = '../api/oauth_google.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');
    ?>
  <div class="social-auth">
    <?php if ($ready): ?>
      <a class="btn-social" href="<?= ogrenci_e($href) ?>" rel="nofollow">
        <?php ogrenci_google_icon(); ?>
        <span>Google ile devam et</span>
      </a>
    <?php else: ?>
      <span class="btn-social is-disabled" aria-disabled="true" title="api/oauth_config.local.php dosyasi olusturulmali">
        <?php ogrenci_google_icon(); ?>
        <span>Google ile devam et</span>
      </span>
      <p class="social-setup-note">
        Yerel not: Google girişini açmak için <code>api/oauth_config.local.php</code> dosyasını oluşturun.
        Gerekli redirect adresi: <code>/api/oauth_status.php</code>
      </p>
    <?php endif; ?>
  </div>

  <div class="auth-divider"><span>ya da</span></div>
    <?php
}

/** Google'ın marka kılavuzuna uygun renkli "G" logosu */
function ogrenci_google_icon(): void {
    ?><svg class="social-icon" viewBox="0 0 18 18" width="18" height="18" aria-hidden="true" focusable="false">
      <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.91c1.7-1.57 2.69-3.88 2.69-6.62z"/>
      <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.91-2.26c-.81.54-1.84.86-3.05.86-2.35 0-4.34-1.58-5.05-3.71H.96v2.34A9 9 0 0 0 9 18z"/>
      <path fill="#FBBC05" d="M3.95 10.71a5.4 5.4 0 0 1 0-3.42V4.95H.96a9 9 0 0 0 0 8.1l2.99-2.34z"/>
      <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.95l2.99 2.34C4.66 5.16 6.65 3.58 9 3.58z"/>
    </svg><?php
}

/** Auth ekranlarının büyük başlık bloğu */
function ogrenci_auth_hero(string $title, string $subtitle): void {
    ?>
  <section class="auth-hero">
    <h1><?= ogrenci_e($title) ?></h1>
    <p><?= ogrenci_e($subtitle) ?></p>
  </section>
    <?php
}

/**
 * Panel sayfaları için ortak giriş kontrolü.
 *
 * Oturumu doğrular, öğrenci kaydını okur; hesap silinmiş/askıya alınmışsa
 * oturumu düşürüp giriş sayfasına yönlendirir. Veritabanına ulaşılamazsa
 * sayfanın yine açılabilmesi için oturumdaki bilgilerle asgari bir satır döner.
 *
 * @return array{0: array, 1: string} [öğrenci satırı, hata mesajı]
 */
function ogrenci_panel_student(string $self): array {
    require_once __DIR__ . '/../api/student_account.php';

    start_student_session();
    require_student_page($self);

    $session = current_student();

    try {
        $pdo = db();
        students_ensure_schema($pdo);
        $row = student_find_by_id($pdo, $session['id']);
        if (!$row || ($row['status'] ?? 'active') !== 'active') {
            student_logout();
            header('Location: giris.php');
            exit;
        }
        return [$row, ''];
    } catch (Throwable $e) {
        return [
            [
                'id' => $session['id'],
                'name' => $session['name'],
                'email' => $session['email'],
            ],
            'Bilgileriniz yüklenemedi. Veritabanı bağlantısını kontrol edin.',
        ];
    }
}

/**
 * Panel üst çubuğu — panel menüsü solda olduğu için burada yalnızca marka,
 * katalog bağlantısı ve kullanıcı bloğu durur.
 */
function ogrenci_app_bar(array $student): void {
    $initials = ogrenci_initials($student['name'] ?? '', $student['email'] ?? '');
    ?>
  <header class="app-bar">
    <div class="app-bar-inner">
      <?php ogrenci_brand(); ?>
      <nav class="app-nav">
        <a href="../egitimler.html">Tüm Eğitimler</a>
      </nav>
      <div class="app-user">
        <span class="avatar"><?= ogrenci_e($initials) ?></span>
        <span class="app-user-meta">
          <b><?= ogrenci_e($student['name'] ?: 'Öğrenci') ?></b>
          <span><?= ogrenci_e($student['email']) ?></span>
        </span>
        <a class="app-logout" href="logout.php" title="Oturumu kapat"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
      </div>
    </div>
  </header>
    <?php
}

/**
 * Panel menüsü — tek kaynak. Yeni bölüm eklemek için buraya satır eklemek yeterli.
 * 'group' değeri değiştiğinde menüde ayırıcı çizgi çıkar.
 */
function ogrenci_menu(): array {
    return [
        ['group' => 1, 'key' => 'kurslarim',   'label' => 'Kurslarım',          'href' => 'index.php'],
        ['group' => 1, 'key' => 'abonelikler', 'label' => 'Aboneliklerim',      'href' => 'aboneliklerim.php'],
        ['group' => 2, 'key' => 'sepet',       'label' => 'Sepetim',            'href' => 'sepetim.php'],
        ['group' => 2, 'key' => 'gecmis',      'label' => 'Satın Alma Geçmişi', 'href' => 'satin-alma-gecmisi.php'],
        ['group' => 3, 'key' => 'profil',      'label' => 'Profili Düzenle',    'href' => 'profil.php'],
        ['group' => 3, 'key' => 'cikis',       'label' => 'Oturumu Kapat',      'href' => 'logout.php'],
    ];
}

/** Sol menü kartı — üstte kullanıcı bilgisi, altında gruplanmış bağlantılar */
function ogrenci_sidebar(array $student, string $active = ''): void {
    $initials = ogrenci_initials($student['name'] ?? '', $student['email'] ?? '');

    // Ayırıcıları kolayca basmak için önce gruplara ayır
    $groups = [];
    foreach (ogrenci_menu() as $item) {
        $groups[$item['group']][] = $item;
    }
    ?>
  <aside class="panel-side">
    <div class="side-card">
      <div class="side-user">
        <span class="avatar avatar-lg"><?= ogrenci_e($initials) ?></span>
        <span class="side-user-meta">
          <b><?= ogrenci_e($student['name'] ?: 'Öğrenci') ?></b>
          <span><?= ogrenci_e($student['email'] ?? '') ?></span>
        </span>
      </div>

      <nav class="side-nav" aria-label="Panel menüsü">
        <?php foreach ($groups as $items): ?>
          <div class="side-group">
            <?php foreach ($items as $item): ?>
              <?php
              $classes = [];
              if ($item['key'] === $active) {
                  $classes[] = 'active';
              }
              if ($item['key'] === 'cikis') {
                  $classes[] = 'is-exit';
              }
              ?>
              <a href="<?= ogrenci_e($item['href']) ?>"
                 <?= $classes ? 'class="' . implode(' ', $classes) . '"' : '' ?>
                 <?= $item['key'] === $active ? 'aria-current="page"' : '' ?>><?= ogrenci_e($item['label']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
    <?php
}

/**
 * Panel gövdesini aç: kırıntı yolu, başlık ve iki kolonlu kabuk.
 * Kapatmak için ogrenci_panel_end().
 */
function ogrenci_panel_start(array $student, string $active, string $title, string $subtitle = ''): void {
    ?>
  <main class="panel-page">
    <div class="app-wrap">
      <nav class="crumbs" aria-label="Konum">
        <a href="../index.html">Anasayfa</a>
        <span aria-hidden="true">/</span>
        <b><?= ogrenci_e($title) ?></b>
      </nav>

      <h1 class="panel-title"><?= ogrenci_e($title) ?></h1>
      <?php if ($subtitle !== ''): ?>
        <p class="panel-lead"><?= ogrenci_e($subtitle) ?></p>
      <?php endif; ?>

      <div class="panel-shell">
        <?php ogrenci_sidebar($student, $active); ?>
        <div class="panel-main">
    <?php
}

function ogrenci_panel_end(): void {
    ?>
        </div>
      </div>
    </div>
  </main>
    <?php
}

/**
 * Henüz yayına alınmamış bölümler için bilgilendirme ekranı.
 * Ölü bağlantı bırakmamak için kullanılır.
 */
function ogrenci_soon(string $icon, string $title, string $text, string $eta = ''): void {
    ?>
  <div class="soon">
    <div class="soon-ico"><i class="<?= ogrenci_e($icon) ?>"></i></div>
    <h2><?= ogrenci_e($title) ?></h2>
    <p><?= ogrenci_e($text) ?></p>
    <?php if ($eta !== ''): ?>
      <p class="soon-eta"><i class="fa-regular fa-clock"></i> <?= ogrenci_e($eta) ?></p>
    <?php endif; ?>
    <div class="soon-actions">
      <a class="btn btn-primary" href="../egitimler.html"><i class="fa-solid fa-compass"></i> Eğitimleri keşfet</a>
      <a class="btn btn-outline" href="index.php">Kurslarıma dön</a>
    </div>
  </div>
    <?php
}

/** Ad soyaddan baş harfler (avatar) */
function ogrenci_initials(string $name, string $email = ''): string {
    $name = trim($name);
    if ($name === '') {
        return mb_strtoupper(mb_substr($email !== '' ? $email : 'Ö', 0, 1));
    }
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}
