<?php
/**
 * ÖRNEK — kopyalayın: api/iyzico_config.local.php
 * Bu dosya (local) Git'e eklenmez.
 *
 * Anahtarları alma:
 *   Sandbox : https://sandbox-merchant.iyzipay.com
 *   Canlı   : https://merchant.iyzipay.com
 *   Her ikisinde: Ayarlar → Firma Ayarları → sayfanın altındaki "API Anahtarları"
 *                 → Görüntüle
 *
 * Sandbox anahtarları "sandbox-" ile başlar ve IYZICO_TEST_MODE = 1 ile
 * kullanılmalıdır. Canlıya geçerken hem anahtarları hem test modunu değiştirin.
 *
 * Kurulumu doğrulamak için: /api/iyzico_status.php
 *
 * İade / abonelik bildirimi (canlıda HTTPS gerekir; localhost’a iyzico ulaşamaz):
 *   Merchant paneli → Ayarlar → Firma Ayarları → Merchant Bildirimleri
 *   URL: https://www.bmcapitalakademi.com/api/iyzico_webhook.php
 *   Abonelik bildirim URL’si de aynı adrese yazılır.
 *
 * Sandbox abonelik ürünü kapalıysa: entegrasyon@iyzico.com
 */

define('IYZICO_API_KEY', 'sandbox-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('IYZICO_SECRET_KEY', 'sandbox-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

/** İşyeri no — abonelik webhook imzası için zorunlu. Merchant panelinden. */
define('IYZICO_MERCHANT_ID', '');

// 1 = sandbox (test kartlarıyla), 0 = canlı (gerçek para)
define('IYZICO_TEST_MODE', 1);

// Sunulacak taksit seçenekleri. Yalnızca tek çekim için: '1'
define('IYZICO_INSTALLMENTS', '1,2,3,6,9,12');
