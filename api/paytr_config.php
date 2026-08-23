<?php
/**
 * PayTR API ayarları — özel PHP site (iFrame API)
 *
 * Kaynak:
 * - Entegrasyon genel: https://www.paytr.com/entegrasyon
 *   (WooCommerce/OpenCart vb. hazır eklentiler; özel site için Developer Portal kullanılır)
 * - Özel yazılım dokümanı: https://dev.paytr.com/iframe-api
 *   1. ADIM get-token + iframe | 2. ADIM Bildirim URL
 *
 * Mağaza Paneli > Destek & Kurulum > Entegrasyon Bilgileri:
 *   merchant_id, merchant_key, merchant_salt
 *
 * Ayarlar > Bildirim URL (canlı domain — localhost kabul edilmez):
 *   https://SIZIN-DOMAIN.com/api/paytr_callback.php
 * Güncel URL: GET /api/launch_status.php → paytr.callback_url
 *
 * Sıra: marka+domain → HTTPS deploy → PayTR üye işyeri → local keys → test → canlı.
 *
 * Test: PAYTR_TEST_MODE = 1 (ekranda test kartı, gerçek tahsilat yok)
 * Canlı: canlı onay + PAYTR_TEST_MODE = 0
 *
 * Gizli anahtarlar için: paytr_config.local.php oluşturun (öncelikli yüklenir).
 */

if (is_file(__DIR__ . '/paytr_config.local.php')) {
    require __DIR__ . '/paytr_config.local.php';
    return;
}

// --- PayTR panelinden kopyalayın ---
define('PAYTR_MERCHANT_ID', '');      // Mağaza no
define('PAYTR_MERCHANT_KEY', '');     // Mağaza parola
define('PAYTR_MERCHANT_SALT', '');    // Mağaza gizli anahtar

// 1 = test (demo), 0 = canlı tahsilat
define('PAYTR_TEST_MODE', 1);

// Hata mesajlarını PayTR'dan al (testte 1, canlıda 0 önerilir)
define('PAYTR_DEBUG_ON', 1);

// Taksit: 0 = varsayılan izin, 1 = sadece tek çekim
define('PAYTR_NO_INSTALLMENT', 0);
define('PAYTR_MAX_INSTALLMENT', 0);

// Boşsa otomatik (https://siteniz.com) hesaplanır
define('PAYTR_SITE_BASE', '');

// Lokal PHP sunucuda dış IP gerekir (https://www.whatismyip.com/)
// define('PAYTR_FORCE_IP', 'x.x.x.x');

// Lokal SSL hatasında geçici: define('PAYTR_SSL_VERIFY', false);
