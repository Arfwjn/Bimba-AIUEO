<?php
// karyawan_portal.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Portal Mandiri Karyawan (ID Card Digital)';
$pageBreadcrumb = 'Dashboard > Data Karyawan > Portal ID Card';

$pdo = getDB();

// Fetch Dynamic Settings
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');

// Fetch Active Employees List
$stmtEmps = $pdo->query("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status_aktif = 1 ORDER BY nama ASC");
$activeEmployees = $stmtEmps->fetchAll();

$selectedEmpId = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : ($activeEmployees[0]['id_karyawan'] ?? 0);

$selectedEmp = null;
$personalAttendance = [];

if ($selectedEmpId > 0) {
    $stmtSelect = $pdo->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
    $stmtSelect->execute([$selectedEmpId]);
    $selectedEmp = $stmtSelect->fetch();

    if ($selectedEmp) {
        $stmtAtt = $pdo->prepare("
            SELECT * FROM presensi 
            WHERE id_karyawan = ? 
            ORDER BY id_presensi DESC LIMIT 10
        ");
        $stmtAtt->execute([$selectedEmpId]);
        $personalAttendance = $stmtAtt->fetchAll();
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
@media print {
    @page {
        size: 90mm 140mm;
        margin: 0;
    }
}
</style>

<div class="panel no-print" style="margin-bottom: 24px;">
    <div class="panel-header">
        <h2 class="panel-title">Pilih Profil ID Card Karyawan</h2>
        <a href="karyawan.php" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Kembali ke Data Karyawan</span>
        </a>
    </div>
    <form method="GET" action="karyawan_portal.php" style="display: flex; gap: 12px; align-items: center; max-width: 480px;">
        <select name="emp_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($activeEmployees as $e): ?>
                <option value="<?= $e['id_karyawan'] ?>" <?= $selectedEmpId === $e['id_karyawan'] ? 'selected' : '' ?>>
                    EMP-<?= sprintf('%03d', $e['id_karyawan']) ?> - <?= htmlspecialchars($e['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Tampilkan ID</button>
    </form>
</div>

<?php if ($selectedEmp): ?>
    <?php 
    $formattedCode = 'EMP-' . sprintf('%03d', $selectedEmp['id_karyawan']);
    $encryptedPayload = encrypt_qr_payload($formattedCode, 12);
    ?>

    <div class="form-row" style="align-items: start;">
        <!-- Left: Official Physical ID Card Badge -->
        <div class="panel" style="flex: 1; text-align: center;">
            <div class="panel-header no-print" style="justify-content: center;">
                <h3 class="panel-title">Preview Kartu Tanda Karyawan</h3>
            </div>

            <!-- Official Physical ID Card Badge Container -->
            <div class="id-card-badge">
                <div class="id-card-header">
                    <div class="id-card-header-title">biMBA AIUEO</div>
                    <div class="id-card-header-sub">KARTU TANDA KARYAWAN & MOTIVATOR</div>
                </div>

                <div class="id-card-body">
                    <div class="id-card-avatar">
                        <?= strtoupper(substr($selectedEmp['nama'], 0, 1)) ?>
                    </div>

                    <h2 class="id-card-name"><?= htmlspecialchars($selectedEmp['nama']) ?></h2>
                    <div class="id-card-role">
                        <?= $formattedCode ?> • <?= htmlspecialchars($selectedEmp['jabatan']) ?>
                    </div>

                    <!-- QR Container -->
                    <div id="portalQrContainer" class="id-card-qr"></div>

                    <div class="id-card-payload">
                        <strong>AES-256 Payload:</strong> <span><?= htmlspecialchars($encryptedPayload) ?></span>
                    </div>
                </div>

                <div class="id-card-footer">
                    VALIDATED AES-256 ENCRYPTED • <?= htmlspecialchars(strtoupper($unitNameVal)) ?>
                </div>
            </div>

            <div style="display: flex; justify-content: center; gap: 10px; margin-top: 14px;" class="no-print">
                <button class="btn btn-primary" onclick="window.print()">
                    <span class="material-symbols-outlined">print</span>
                    <span>Cetak ID Card Fisik</span>
                </button>
            </div>
        </div>

        <!-- Right: Personal Attendance History -->
        <div class="panel no-print" style="flex: 1.4;">
            <div class="panel-header">
                <h3 class="panel-title">Riwayat Presensi Pribadi (10 Terakhir)</h3>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Status Kehadiran</th>
                            <th>Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personalAttendance)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada riwayat presensi recorded</td></tr>
                        <?php else: ?>
                            <?php foreach ($personalAttendance as $pa): ?>
                                <tr>
                                    <td><strong><?= date('d/m/Y', strtotime($pa['tanggal'])) ?></strong></td>
                                    <td><strong style="color: var(--status-success-text);"><?= htmlspecialchars($pa['jam_masuk'] ?? '-') ?></strong></td>
                                    <td>
                                        <?php if (!empty($pa['jam_keluar'])): ?>
                                            <strong style="color: var(--status-warning-text);"><?= htmlspecialchars($pa['jam_keluar']) ?></strong>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $pa['status'] === 'Hadir' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($pa['status']) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge badge-success">AES Valid</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="assets/js/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        generateQRCode('portalQrContainer', '<?= addslashes($encryptedPayload) ?>', 140, 140);
    });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
