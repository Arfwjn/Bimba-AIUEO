<?php
/**
 * Modul Logout & Destruction Session Administrator biMBA AIUEO
 * 
 * Mengosongkan data session pengguna, menghapus cookie session aktif,
 * menghentikan session secara permanen, dan mengarahkan kembali pengguna ke halaman login.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Authentication
 * @author      Developer Team biMBA AIUEO
 */

require_once __DIR__ . '/config/app.php';

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: login.php');
exit;
