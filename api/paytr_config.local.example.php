<?php
/**
 * ÖRNEK — bu dosyayı paytr_config.local.php olarak kopyalayın ve doldurun.
 * paytr_config.local.php Git'e eklenmemeli (gizli anahtarlar).
 *
 * Önkoşul: api/site_brand.local.php içinde PUBLIC_SITE_URL = https://domain
 * PayTR panel → Bildirim URL: https://domain/api/paytr_callback.php
 */

define('PAYTR_MERCHANT_ID', 'XXXXXX');
define('PAYTR_MERCHANT_KEY', 'YYYYYYYYYYYYYY');
define('PAYTR_MERCHANT_SALT', 'ZZZZZZZZZZZZZZ');

// 1 = test kartı (gerçek tahsilat yok) · 0 = canlı
define('PAYTR_TEST_MODE', 1);
define('PAYTR_DEBUG_ON', 1);
define('PAYTR_NO_INSTALLMENT', 0);
define('PAYTR_MAX_INSTALLMENT', 0);

// Boş bırakın; site_brand PUBLIC_SITE_URL kullanılır. Gerekirse override:
define('PAYTR_SITE_BASE', '');
