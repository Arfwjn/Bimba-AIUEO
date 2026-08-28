<?php
/**
 * Guard Middleware Autentikasi Halaman Admin
 * 
 * Memastikan setiap halaman yang menyertakan file ini hanya dapat diakses oleh pengguna
 * yang sudah berhasil login. Mengarahkan pengguna anonim ke halaman login.php secara otomatis.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Security
 * @author      Developer Team biMBA AIUEO
 */

require_once __DIR__ . '/../config/security.php';

require_login();
