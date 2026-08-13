<?php
// pengaturan.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Pengaturan Sistem & Profil';
$pageBreadcrumb = 'Dashboard > Pengaturan Sistem';

$pdo = getDB();
$user = get_logged_user();
$message = '';
$error = '';

// Process Backup Download
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $filename = "bimba_backup_" . date('Ymd_His') . ".sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "-- ========================================================\n";
    echo "-- Database Backup biMBA AIUEO Unit Kebanggan\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- ========================================================\n\n";

    $tables = ['admin', 'karyawan', 'qr_code', 'presensi', 'petty_cash', 'settings'];
    foreach ($tables as $tbl) {
        try {
            $rows = $pdo->query("SELECT * FROM {$tbl}")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo "-- Table: {$tbl}\n";
                foreach ($rows as $r) {
                    $keys = array_keys($r);
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? "NULL" : $pdo->quote($v);
                    }, array_values($r));
                    
                    echo "INSERT INTO `{$tbl}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {}
    }
    exit;
}

// Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan CSRF Token gagal.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $oldPass = trim($_POST['old_password'] ?? '');
            $newPass = trim($_POST['new_password'] ?? '');

            if (!empty($nama)) {
                $stmtUser = $pdo->prepare("SELECT * FROM admin WHERE id_admin = ?");
                $stmtUser->execute([$user['id']]);
                $userData = $stmtUser->fetch();

                if (!empty($newPass)) {
                    if ($userData && password_verify($oldPass, $userData['password'])) {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmtUp = $pdo->prepare("UPDATE admin SET nama = ?, password = ? WHERE id_admin = ?");
                        $stmtUp->execute([$nama, $newHash, $user['id']]);
                        $_SESSION['nama_lengkap'] = $nama;
                        $message = 'Profil dan password admin berhasil diperbarui!';
                    } else {
                        $error = 'Password lama Anda tidak sesuai.';
                    }
                } else {
                    $stmtUp = $pdo->prepare("UPDATE admin SET nama = ? WHERE id_admin = ?");
                    $stmtUp->execute([$nama, $user['id']]);
                    $_SESSION['nama_lengkap'] = $nama;
                    $message = 'Nama profil berhasil diperbarui!';
                }
            }
        } elseif ($action === 'institution') {
            save_system_setting('unit_name', trim($_POST['unit_name'] ?? ''));
            save_system_setting('unit_leader', trim($_POST['unit_leader'] ?? ''));
            save_system_setting('unit_location', trim($_POST['unit_location'] ?? ''));
            save_system_setting('unit_address', trim($_POST['unit_address'] ?? ''));
            save_system_setting('unit_phone', trim($_POST['unit_phone'] ?? ''));
            save_system_setting('unit_email', trim($_POST['unit_email'] ?? ''));
            save_system_setting('qr_hours', (string)intval($_POST['qr_hours'] ?? 12));

            $message = 'Pengaturan identitas lembaga & Kop Surat laporan berhasil diperbarui!';
        }
    }
}

// Fetch Current Settings with Fallback Defaults
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');
$unitLeaderVal = get_system_setting('unit_leader', 'Siti Rahmawati, S.Pd');
$unitLocationVal = get_system_setting('unit_location', 'Jakarta');
$unitAddressVal = get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta');
$unitPhoneVal = get_system_setting('unit_phone', '(021) 555-8899');
$unitEmailVal = get_system_setting('unit_email', 'info@bimba-kebanggan.sch.id');
$qrHoursVal = get_system_setting('qr_hours', '12');

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span class="material-symbols-outlined">error</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<div class="form-row" style="align-items: start;">
    <!-- Left Column: Institution & Report Printing Settings -->
    <div class="panel" style="flex: 1.2;">
        <div class="panel-header">
            <h2 class="panel-title">Pengaturan Identitas Unit & Kop Laporan</h2>
            <span class="badge badge-info">Dokumen Cetak PDF</span>
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
            Data di bawah ini akan ditampilkan secara otomatis pada **Kop Surat** dan **Tanda Tangan Dokumen** saat mencetak Laporan Presensi dan Laporan Petty Cash.
        </p>

        <form method="POST" action="pengaturan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="institution">

            <div class="form-group">
                <label class="form-label">Nama Unit Lembaga biMBA</label>
                <input type="text" name="unit_name" class="form-control" value="<?= htmlspecialchars($unitNameVal) ?>" placeholder="Contoh: biMBA AIUEO Unit Kebanggan" required>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Nama Kepala / Ketua Unit</label>
                    <input type="text" name="unit_leader" class="form-control" value="<?= htmlspecialchars($unitLeaderVal) ?>" placeholder="Contoh: Siti Rahmawati, S.Pd" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Kota / Lokasi Surat Cetak</label>
                    <input type="text" name="unit_location" class="form-control" value="<?= htmlspecialchars($unitLocationVal) ?>" placeholder="Contoh: Jakarta" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Lengkap Unit</label>
                <input type="text" name="unit_address" class="form-control" value="<?= htmlspecialchars($unitAddressVal) ?>" placeholder="Contoh: Jl. Raya Kebanggan No. 12, Jakarta" required>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Nomor Telepon Unit</label>
                    <input type="text" name="unit_phone" class="form-control" value="<?= htmlspecialchars($unitPhoneVal) ?>" placeholder="Contoh: (021) 555-8899" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Email Resmi Unit</label>
                    <input type="email" name="unit_email" class="form-control" value="<?= htmlspecialchars($unitEmailVal) ?>" placeholder="Contoh: info@bimba-kebanggan.sch.id" required>
                </div>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label class="form-label">Default Masa Berlaku QR Code (Jam)</label>
                <input type="number" name="qr_hours" class="form-control" value="<?= htmlspecialchars($qrHoursVal) ?>" min="1" max="72" required>
                <small style="color: var(--text-muted); font-size: 11px;">Tentukan berapa jam QR Code presensi berlaku sebelum kedaluwarsa.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    <span>Simpan Pengaturan Laporan</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Admin Profile & Security -->
    <div class="panel" style="flex: 1;">
        <div class="panel-header">
            <h2 class="panel-title">Pengaturan Profil Admin</h2>
        </div>

        <form method="POST" action="pengaturan.php" style="margin-bottom: 24px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">

            <div class="form-group">
                <label class="form-label">Username Logged In</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled style="background-color: var(--surface-muted);">
            </div>

            <div class="form-group">
                <label class="form-label">Nama Lengkap Admin</label>
                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
            </div>

            <div style="border-top: 2px solid var(--border-color); margin: 20px 0; padding-top: 16px;">
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 12px;">Ubah Password Account</h3>

                <div class="form-group">
                    <label class="form-label">Password Saat Ini</label>
                    <input type="password" name="old_password" class="form-control" placeholder="Masukkan password saat ini">
                </div>

                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Masukkan password baru">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Simpan Profil Admin</button>
            </div>
        </form>

        <div style="border-top: 2px solid var(--border-color); padding-top: 16px;">
            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 8px;">Backup & Security Enkripsi</h3>
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                Metode AES: <strong><?= AES_METHOD ?></strong>. Kunci rahasia AES tersimpan aman di server.
            </p>
            
            <a href="pengaturan.php?action=download_backup" class="btn btn-secondary">
                <span class="material-symbols-outlined">database</span>
                <span>Download Backup Database (.sql)</span>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
