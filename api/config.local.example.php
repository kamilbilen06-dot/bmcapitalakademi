<?php
/**
 * ÖRNEK — kopyalayın: api/config.local.php
 * Bu dosya (local) Git'e eklenmez. GitHub kopyası config.php'yi ezse bile bu kalır.
 *
 * cPanel → MySQL® Databases: veritabanı adı, kullanıcı adı, şifre.
 * İsimler genelde önekli olur: ucrjblb7bbh4_...
 *
 * Canlıda root / boş şifre KULLANMAYIN.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'KULLANICI_veritabani');
define('DB_USER', 'KULLANICI_dbuser');
define('DB_PASS', 'GÜÇLÜ_SIFRE');
define('DB_CHARSET', 'utf8mb4');

define('INSTALL_LOCKED', true);
define('CRON_KEY', '');

/** İlk açılışta yönetici yoksa bu hesap oluşturulur (en az 6 karakter). */
define('BOOTSTRAP_ADMIN_USER', 'admin');
define('BOOTSTRAP_ADMIN_PASS', 'BURAYA_PANEL_SIFRESI');
