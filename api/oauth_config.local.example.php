<?php
/**
 * ÖRNEK — kopyalayın: api/oauth_config.local.php
 * Bu dosya (local) Git'e eklenmez.
 *
 * Değerleri Google Cloud Console → Credentials → OAuth client ID (Web application)
 * ekranından alın. Kurulum adımları: api/oauth_config.php başındaki not.
 *
 * Redirect URI'leri Google panelinde şu şekilde tanımlayın:
 *   http://localhost:8000/api/oauth_google_callback.php
 *   http://127.0.0.1:8000/api/oauth_google_callback.php
 *   https://www.bmcapitalakademi.com/api/oauth_google_callback.php
 *   https://bmcapitalakademi.com/api/oauth_google_callback.php
 */

define('GOOGLE_CLIENT_ID', '000000000000-xxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx');
