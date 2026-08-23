<?php
/**
 * BM Capital / yeni akademi — Veritabanı ve panel yapılandırması
 * Hostinge yükledikten sonra MySQL bilgilerini güncelleyin.
 * Marka/domain: api/site_brand.local.php (örnek: site_brand.local.example.php)
 */

// --- MySQL bağlantı bilgileri ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'bmcapital');       // cPanel'de oluşturduğunuz veritabanı adı
define('DB_USER', 'root');            // cPanel veritabanı kullanıcısı
define('DB_PASS', '');                // cPanel veritabanı şifresi
define('DB_CHARSET', 'utf8mb4');

// --- Güvenlik ---
// install.php çalıştırıldıktan sonra bu değeri true yapın (kurulum dosyası devre dışı kalır).
define('INSTALL_LOCKED', true);

// Cron anahtarı — /api/cron.php?key=... (boşsa yalnız localhost). Canlıda mutlaka doldurun.
if (!defined('CRON_KEY')) {
    define('CRON_KEY', '');
}

// Oturum çerezi adı (admin + eğitmen paneli)
define('SESSION_NAME', 'bmcap_admin');

// Öğrenci oturum çerezi — panel oturumundan tamamen ayrı tutulur.
define('STUDENT_SESSION_NAME', 'bmcap_student');

// Site kök URL'si — boş bırakılırsa site_brand / istek host kullanılır.
// Canlıda tercihen api/site_brand.local.php içinde PUBLIC_SITE_URL tanımlayın.
define('SITE_URL', '');

require_once __DIR__ . '/site_brand.php';

// SITE_URL boşsa marka config'ten doldur (PayTR callback için kritik)
if (SITE_URL === '' && PUBLIC_SITE_URL !== '') {
    // SITE_URL const yeniden tanımlanamaz; paytr_site_base zaten PUBLIC_SITE_URL okur.
}
