<?php
/**
 * ÖRNEK — kopyalayın: site_brand.local.php
 * Bu dosya (local) Git'e eklenmemeli.
 *
 * YEREL GELİŞTİRMEDE bu dosyayı OLUŞTURMAYIN: PUBLIC_SITE_URL boş kalırsa
 * bağlantılar isteğin geldiği adrese (127.0.0.1:8000) göre üretilir.
 * Aşağıdaki değerler canlıya (bmcapitalakademi.com) çıkarken kullanılır.
 */

// --- 1) Marka ---
define('BRAND_NAME', 'BM Capital Akademi');    // Tam ad (SEO / başlık)
define('BRAND_SHORT', 'BM Capital');           // Kısa ad
define('BRAND_MARK', 'BM');                    // Logo kutusu (2 harf önerilir)
define('BRAND_WORD', 'Capital');               // Wordmark
define('BRAND_TAGLINE', 'Akademi');            // Wordmark altı
define('BRAND_CITY', 'İzmir');

// --- 2) Domain (HTTPS, sonda / yok) ---
define('PUBLIC_SITE_URL', 'https://www.bmcapitalakademi.com');

// Marka + domain kesinleşince true → PayTR / iyzico başvurusuna geçilebilir
define('BRAND_DOMAIN_READY', true);
