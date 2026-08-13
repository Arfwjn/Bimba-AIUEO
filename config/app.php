<?php
// config/app.php
require_once __DIR__ . '/env.php';

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', env('APP_NAME', 'biMBA AIUEO Unit Kebanggan'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_VERSION', '1.0.0');

// AES Encryption Settings
define('AES_KEY', env('AES_KEY', 'biMBA_AIUEO_SecretKey_2026_AES256'));
define('AES_METHOD', env('AES_METHOD', 'AES-256-CBC'));

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
