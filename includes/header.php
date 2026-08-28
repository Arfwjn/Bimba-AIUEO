<?php
/**
 * Komponen Core Header & Wrapper Layanan Admin biMBA AIUEO
 * 
 * Menyediakan bagian HTML head, stylesheet CSS, pembuka wrapper aplikasi,
 * tombol toggle sidebar mobile, judul halaman dinamis, serta jam digital real-time.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Templates
 * @author      Developer Team biMBA AIUEO
 */
if (!function_exists('get_logged_user')) {
    require_once __DIR__ . '/../config/security.php';
}
$user = get_logged_user();

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard Admin';
}
if (!isset($pageBreadcrumb)) {
    $pageBreadcrumb = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - biMBA AIUEO</title>
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <!-- Custom CSS (Dengan Cache-Busting Versioning) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= defined('APP_VERSION') ? APP_VERSION : time() ?>">
    
    <!-- Official Print & Kop Surat CSS -->
    <link rel="stylesheet" href="assets/css/print-official.css?v=<?= defined('APP_VERSION') ? APP_VERSION : time() ?>">
</head>
<body>
<div class="app-container">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="main-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button id="sidebarToggle" class="btn btn-secondary btn-sm no-print" style="padding: 6px 10px;">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="header-title-section">
                    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="breadcrumb"><?= htmlspecialchars($pageBreadcrumb) ?></div>
                </div>
            </div>

            <div class="header-actions">
                <div class="time-badge">
                    <span class="material-symbols-outlined" style="font-size: 18px;">schedule</span>
                    <span id="liveClock">--:--:--</span>
                </div>
            </div>
        </header>

        <main class="content-body">
