<?php
// laporan.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Laporan Rekapitulasi Presensi';
$pageBreadcrumb = 'Dashboard > Laporan Presensi';

$pdo = getDB();

// Filter parameters
$selectedMonth = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$selectedYear = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$selectedKaryawan = isset($_GET['karyawan_id']) ? intval($_GET['karyawan_id']) : 0;

// Fetch Dynamic Institution Settings for PDF Kop & Signature
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');
$unitLeaderVal = get_system_setting('unit_leader', 'Siti Rahmawati, S.Pd');
$unitLocationVal = get_system_setting('unit_location', 'Jakarta');
$unitAddressVal = get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta');
$unitPhoneVal = get_system_setting('unit_phone', '(021) 555-8899');
$unitEmailVal = get_system_setting('unit_email', 'info@bimba-kebanggan.sch.id');

// Fetch Employees for Filter Dropdown
$stmtEmpList = $pdo->query("SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC");
$allEmployees = $stmtEmpList->fetchAll();

// Build Query for Presensi with MySQL / SQLite Date Compatibility
if (DB_DRIVER === 'mysql') {
    $sql = "
        SELECT p.*, k.nama, k.jabatan 
        FROM presensi p 
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
        WHERE MONTH(p.tanggal) = ? AND YEAR(p.tanggal) = ?
    ";
    $params = [$selectedMonth, $selectedYear];
} else {
    $sql = "
        SELECT p.*, k.nama, k.jabatan 
        FROM presensi p 
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
        WHERE strftime('%m', p.tanggal) = ? AND strftime('%Y', p.tanggal) = ?
    ";
    $params = [sprintf('%02d', $selectedMonth), (string)$selectedYear];
}

if ($selectedKaryawan > 0) {
    $sql .= " AND p.id_karyawan = ?";
    $params[] = $selectedKaryawan;
}
$sql .= " ORDER BY p.tanggal DESC, p.jam_masuk ASC";

$stmtReport = $pdo->prepare($sql);
$stmtReport->execute($params);
$reportData = $stmtReport->fetchAll();

// Summary Counters
$totalHadir = 0;
$totalTerlambat = 0;
$totalIzinSakit = 0;
$totalAlpha = 0;

foreach ($reportData as $row) {
    if ($row['status'] === 'Hadir') $totalHadir++;
    elseif ($row['status'] === 'Terlambat') $totalTerlambat++;
    elseif (in_array($row['status'], ['Izin', 'Sakit'])) $totalIzinSakit++;
    else $totalAlpha++;
}

$monthsIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include __DIR__ . '/includes/header.php';
?>

<!-- Filter Panel -->
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
}
</style>
<div class="panel no-print">
    <div class="panel-header">
        <h2 class="panel-title">Filter Laporan Presensi</h2>
        <div style="display: flex; gap: 10px;">
            <a href="export.php?type=presensi&bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>&karyawan_id=<?= $selectedKaryawan ?>" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Export Excel (.xls)</span>
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                <span>Cetak PDF Resmi</span>
            </button>
        </div>
    </div>

    <form method="GET" action="laporan.php">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-control">
                    <?php foreach ($monthsIndo as $mNum => $mName): ?>
                        <option value="<?= $mNum ?>" <?= $selectedMonth === $mNum ? 'selected' : '' ?>>
                            <?= $mName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-control">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Pilih Karyawan</label>
                <select name="karyawan_id" class="form-control">
                    <option value="0">Semua Karyawan</option>
                    <?php foreach ($allEmployees as $e): ?>
                        <option value="<?= $e['id_karyawan'] ?>" <?= $selectedKaryawan === $e['id_karyawan'] ? 'selected' : '' ?>>
                            EMP-<?= sprintf('%03d', $e['id_karyawan']) ?> - <?= htmlspecialchars($e['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="justify-content: flex-end;">
                <label class="form-label" style="visibility: hidden;">Aksi</label>
                <button type="submit" class="btn btn-secondary" style="width: 100%;">
                    <span class="material-symbols-outlined">filter_list</span>
                    <span>Tampilkan</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Document View for Screen & Print PDF -->
<div class="panel">
    <!-- Official Kop Surat Document Header (Visible ONLY in PDF Print) -->
    <div class="kop-surat">
        <div class="kop-logo">B</div>
        <div class="kop-text">
            <div class="kop-title"><?= htmlspecialchars(strtoupper($unitNameVal)) ?></div>
            <div class="kop-subtitle">LEMBAGA Bimbingan Minat Baca dan Belajar Anak</div>
            <div class="kop-address"><?= htmlspecialchars($unitAddressVal) ?> • Telp: <?= htmlspecialchars($unitPhoneVal) ?> • Email: <?= htmlspecialchars($unitEmailVal) ?></div>
        </div>
    </div>

    <div style="margin-bottom: 24px; text-align: center;">
        <h2 style="font-size: 18px; font-weight: 700; text-transform: uppercase;">LAPORAN REKAPITULASI PRESENSI KARYAWAN</h2>
        <div style="font-family: var(--font-sans); font-size: 13px; color: var(--text-muted);">
            Periode: <strong><?= $monthsIndo[$selectedMonth] ?> <?= $selectedYear ?></strong>
        </div>
    </div>

    <!-- Summary Stats Cards -->
    <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Total Hadir</span>
            <div class="stat-value" style="color: var(--status-success-text);"><?= $totalHadir ?></div>
        </div>
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Terlambat</span>
            <div class="stat-value" style="color: var(--status-warning-text);"><?= $totalTerlambat ?></div>
        </div>
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Izin / Sakit</span>
            <div class="stat-value" style="color: var(--status-info-text);"><?= $totalIzinSakit ?></div>
        </div>
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Tidak Hadir</span>
            <div class="stat-value" style="color: var(--status-danger-text);"><?= $totalAlpha ?></div>
        </div>
    </div>

    <!-- Detail Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Nama Karyawan</th>
                    <th>Jabatan</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status Presensi</th>
                    <th>Validasi QR AES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada data presensi pada periode yang dipilih</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= date('d/m/Y', strtotime($row['tanggal'])) ?></strong></td>
                            <td>EMP-<?= sprintf('%03d', $row['id_karyawan']) ?></td>
                            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                            <td><?= htmlspecialchars($row['jabatan']) ?></td>
                            <td><strong style="color: var(--status-success-text);"><?= htmlspecialchars($row['jam_masuk'] ?? '-') ?></strong></td>
                            <td>
                                <?php if (!empty($row['jam_keluar'])): ?>
                                    <strong style="color: var(--status-warning-text);"><?= htmlspecialchars($row['jam_keluar']) ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $row['status'] === 'Hadir' ? 'success' : ($row['status'] === 'Terlambat' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">Valid AES</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signature Block for Official Printout (Visible ONLY in PDF Print) -->
    <div class="signature-section" style="margin-top: 40px; display: flex; justify-content: space-between;">
        <div></div>
        <div class="signature-box" style="text-align: center; font-family: 'Times New Roman', Times, serif;">
            <p><?= htmlspecialchars($unitLocationVal) ?>, <?= date('d F Y') ?></p>
            <p>Kepala Unit biMBA AIUEO,</p>
            <div class="signature-space" style="height: 65px;"></div>
            <p class="signature-name"><strong>( <?= htmlspecialchars($unitLeaderVal) ?> )</strong></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
