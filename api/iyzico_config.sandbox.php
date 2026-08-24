<?php
/**
 * iyzico SANDBOX anahtarları — yalnızca test içindir.
 *
 * Bu dosya Git'te durur, böylece sunucuya deploy ile gelir ve kart ödemesi
 * elle dosya oluşturmadan test edilebilir. Sandbox anahtarlarıyla gerçek para
 * çekilmez; yalnızca iyzico'nun test kartları çalışır.
 *
 * CANLIYA GEÇİŞ: api/iyzico_config.local.php dosyasını canlı anahtarlarla
 * oluşturun. O dosya varsa iyzico_config.php buraya hiç bakmaz. Canlı anahtar
 * konulmadan gerçek satış YAPILMAZ: sandbox'ta ödeme sahtedir ve test kartını
 * bilen biri eğitime bedava erişebilir.
 */

define('IYZICO_API_KEY', 'sandbox-zyzLudDGDNsnz6nCe5PPBZxuC7xMnFAh');
define('IYZICO_SECRET_KEY', 'sandbox-Eh6i3kKVAb1FkVYB8vpq7qr7WCb0wPqR');

/** Bu dosya yalnızca sandbox içindir; test modu sabittir. */
define('IYZICO_TEST_MODE', 1);

define('IYZICO_INSTALLMENTS', '1,2,3,6,9,12');
