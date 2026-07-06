<?php
/* ============================================================
   WELL PHARMACY — configuration
   Local dev defaults here; on the server drop inc/config.prod.php
   with the real cPanel DB credentials (it overrides these).
   ============================================================ */
date_default_timezone_set('Asia/Beirut');

if (is_file(__DIR__ . '/config.prod.php')) {
    require __DIR__ . '/config.prod.php';
}

/* ---- local dev fallbacks (only define what prod didn't) ---- */
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'wellpharmacy_dev');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('ASSET_VER')) define('ASSET_VER', 'dev4');   // bump to bust CSS/JS caches
if (!defined('DEV'))      define('DEV', !is_file(__DIR__ . '/config.prod.php'));
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', 'uploads');  // relative to site root
