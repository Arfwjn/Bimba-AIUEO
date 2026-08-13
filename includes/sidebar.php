<?php
// includes/sidebar.php
if (!function_exists('get_logged_user')) {
    require_once __DIR__ . '/../config/security.php';
}

$user = get_logged_user();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">B</div>
        <div>
            <div class="sidebar-brand-title">biMBA AIUEO</div>
            <div class="sidebar-brand-sub">Unit Kebanggan</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">dashboard</span>
            <span>Dashboard</span>
        </a>
        <a href="karyawan.php" class="nav-link <?= ($currentPage === 'karyawan.php' || $currentPage === 'karyawan_portal.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">badge</span>
            <span>Data Karyawan</span>
        </a>
        <a href="presensi.php" class="nav-link <?= ($currentPage === 'presensi.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">qr_code_scanner</span>
            <span>Presensi QR</span>
        </a>
        <a href="petty_cash.php" class="nav-link <?= ($currentPage === 'petty_cash.php' || $currentPage === 'laporan_petty_cash.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">payments</span>
            <span>Petty Cash</span>
        </a>
        <a href="laporan.php" class="nav-link <?= ($currentPage === 'laporan.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">description</span>
            <span>Laporan Presensi</span>
        </a>
        <a href="pengaturan.php" class="nav-link <?= ($currentPage === 'pengaturan.php') ? 'active' : '' ?>">
            <span class="material-symbols-outlined">settings</span>
            <span>Pengaturan</span>
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($user['nama']) ?></span>
                <span class="user-role"><?= htmlspecialchars($user['role']) ?></span>
            </div>
        </div>
        <a href="logout.php" title="Logout" style="color: var(--text-primary); display: flex; align-items: center;">
            <span class="material-symbols-outlined">logout</span>
        </a>
    </div>
</aside>
