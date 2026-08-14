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
$activeTab = 'tab-unit';

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
            $activeTab = 'tab-admin';
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
            $activeTab = trim($_POST['active_tab'] ?? 'tab-unit');
            save_system_setting('unit_name', trim($_POST['unit_name'] ?? ''));
            save_system_setting('unit_leader', trim($_POST['unit_leader'] ?? ''));
            save_system_setting('unit_location', trim($_POST['unit_location'] ?? ''));
            save_system_setting('unit_address', trim($_POST['unit_address'] ?? ''));
            save_system_setting('unit_phone', trim($_POST['unit_phone'] ?? ''));
            save_system_setting('unit_email', trim($_POST['unit_email'] ?? ''));
            
            $message = 'Pengaturan identitas lembaga berhasil diperbarui!';
        } elseif ($action === 'schedule') {
            $activeTab = 'tab-schedule';
            save_system_setting('work_in_time', trim($_POST['work_in_time'] ?? '08:00'));
            save_system_setting('work_in_late', trim($_POST['work_in_late'] ?? '08:15'));
            save_system_setting('work_out_time', trim($_POST['work_out_time'] ?? '16:00'));
            save_system_setting('qr_hours', (string)intval($_POST['qr_hours'] ?? 12));

            $message = 'Pengaturan Jadwal Jam Kerja Presensi berhasil diperbarui!';
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

// Work Hours Settings
$workInTimeVal = get_system_setting('work_in_time', '08:00');
$workInLateVal = get_system_setting('work_in_late', '08:15');
$workOutTimeVal = get_system_setting('work_out_time', '16:00');

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

<!-- Settings Section Menu Tabs Bar -->
<div class="panel" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-primary setting-tab-btn" data-tab="tab-unit" onclick="switchSettingTab('tab-unit', this)">
            <span class="material-symbols-outlined">domain</span>
            <span>Identitas Unit & Kop</span>
        </button>
        <button type="button" class="btn btn-secondary setting-tab-btn" data-tab="tab-schedule" onclick="switchSettingTab('tab-schedule', this)">
            <span class="material-symbols-outlined">schedule</span>
            <span>Jadwal Jam Kerja</span>
        </button>
        <button type="button" class="btn btn-secondary setting-tab-btn" data-tab="tab-admin" onclick="switchSettingTab('tab-admin', this)">
            <span class="material-symbols-outlined">admin_panel_settings</span>
            <span>Profil Admin</span>
        </button>
        <button type="button" class="btn btn-secondary setting-tab-btn" data-tab="tab-backup" onclick="switchSettingTab('tab-backup', this)">
            <span class="material-symbols-outlined">database</span>
            <span>Backup Database</span>
        </button>
    </div>
</div>

<!-- Tab 1: Identitas Unit & Kop Laporan -->
<div id="tab-unit" class="setting-tab-content" style="display: block;">
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Pengaturan Identitas Unit & Kop Surat Laporan</h2>
            <span class="badge badge-info">Kop Surat PDF</span>
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
            Data di bawah ini akan ditampilkan secara otomatis pada <b>Kop Surat</b> dan <b>Tanda Tangan Dokumen</b> saat mencetak Laporan Presensi dan Laporan Petty Cash.
        </p>

        <form method="POST" action="pengaturan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="institution">
            <input type="hidden" name="active_tab" value="tab-unit">

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

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    <span>Simpan Identitas Unit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tab 2: Pengaturan Jadwal Jam Kerja Presensi -->
<div id="tab-schedule" class="setting-tab-content" style="display: none;">
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Pengaturan Jadwal Jam Kerja & Status Presensi</h2>
            <span class="badge badge-info">Validasi Jam Presensi</span>
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
            Konfigurasi jam masuk, toleransi keterlambatan, dan jadwal pulang untuk validasi otomatis presensi QR karyawan.
        </p>

        <form method="POST" action="pengaturan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="schedule">

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Jam Masuk Standard (Tepat Waktu)</label>
                    <input type="time" name="work_in_time" class="form-control" value="<?= htmlspecialchars($workInTimeVal) ?>" required>
                    <small style="color: var(--text-muted); font-size: 11px;">Waktu awal mulai bertugas (misal 08:00).</small>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Batas Maksimal Toleransi (Terlambat)</label>
                    <input type="time" name="work_in_late" class="form-control" value="<?= htmlspecialchars($workInLateVal) ?>" required>
                    <small style="color: var(--text-muted); font-size: 11px;">Lewat jam ini dianggap status Terlambat (misal 08:15).</small>
                </div>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label class="form-label">Jam Keluar Standard (Jadwal Pulang)</label>
                <input type="time" name="work_out_time" class="form-control" value="<?= htmlspecialchars($workOutTimeVal) ?>" required>
                <small style="color: var(--text-muted); font-size: 11px;">Scan keluar sebelum jam ini statusnya Belum Waktu Pulang / Pulang Awal (misal 16:00).</small>
            </div>           

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    <span>Simpan Jadwal Jam Kerja</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tab 3: Pengaturan Profil Admin -->
<div id="tab-admin" class="setting-tab-content" style="display: none;">
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Pengaturan Profil Admin & Keamanan Akun</h2>
        </div>

        <form method="POST" action="pengaturan.php">
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
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    <span>Simpan Profil Admin</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tab 4: Backup Database & System Info -->
<div id="tab-backup" class="setting-tab-content" style="display: none;">
    <div class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Backup Database & Informasi Keamanan AES-256</h2>
        </div>

        <div style="margin-bottom: 20px;">
            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 8px;">Backup System Database</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                Unduh salinan cadangan seluruh data sistem (karyawan, presensi, petty cash, dan settings) dalam bentuk file `.sql` untuk pemulihan data.
            </p>
            
            <a href="pengaturan.php?action=download_backup" class="btn btn-secondary">
                <span class="material-symbols-outlined">database</span>
                <span>Download Backup Database (.sql)</span>
            </a>
        </div>

        <div style="border-top: 2px solid var(--border-color); padding-top: 16px;">
            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 8px;">Spesifikasi Kriptografi AES</h3>
            <p style="font-size: 13px; color: var(--text-muted);">
                Metode Enkripsi: <strong><?= AES_METHOD ?></strong><br>
                Kunci Rahasia (AES Key): <strong>Terenkripsi SHA-256 di Server Configuration</strong>
            </p>
        </div>
    </div>
</div>

<script>
function switchSettingTab(tabId, btnElement) {
    document.querySelectorAll('.setting-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.setting-tab-btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });

    const targetContent = document.getElementById(tabId);
    if (targetContent) {
        targetContent.style.display = 'block';
    }

    if (btnElement) {
        btnElement.classList.remove('btn-secondary');
        btnElement.classList.add('btn-primary');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const initialTab = '<?= $activeTab ?>';
    const activeBtn = document.querySelector(`.setting-tab-btn[data-tab="${initialTab}"]`);
    if (activeBtn) {
        switchSettingTab(initialTab, activeBtn);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
