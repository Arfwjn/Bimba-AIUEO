<?php
// laporan_petty_cash.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Laporan Petty Cash';
$pageBreadcrumb = 'Dashboard > Petty Cash > Laporan Petty Cash';

$pdo = getDB();

// Filter parameters
$selectedMonth = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$selectedYear = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Fetch Dynamic Institution Settings for PDF Kop & Signature
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');
$unitLeaderVal = get_system_setting('unit_leader', 'Siti Rahmawati, S.Pd');
$unitLocationVal = get_system_setting('unit_location', 'Jakarta');
$unitAddressVal = get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta');
$unitPhoneVal = get_system_setting('unit_phone', '(021) 555-8899');
$unitEmailVal = get_system_setting('unit_email', 'info@bimba-kebanggan.sch.id');

// Fetch Petty Cash for Selected Period with MySQL / SQLite Date Compatibility
if (DB_DRIVER === 'mysql') {
    $sql = "
        SELECT * FROM petty_cash 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        ORDER BY id_transaksi ASC
    ";
    $params = [$selectedMonth, $selectedYear];
} else {
    $sql = "
        SELECT * FROM petty_cash 
        WHERE strftime('%m', tanggal) = ? AND strftime('%Y', tanggal) = ?
        ORDER BY id_transaksi ASC
    ";
    $params = [sprintf('%02d', $selectedMonth), (string)$selectedYear];
}

$stmtReport = $pdo->prepare($sql);
$stmtReport->execute($params);
$reportData = $stmtReport->fetchAll();

// Summary Counters
$totalPemasukan = 0;
$totalPengeluaran = 0;
$kategoriStats = [];

foreach ($reportData as $row) {
    $nom = floatval($row['nominal'] ?? 0);
    $kat = $row['kategori'] ?? $row['keterangan'] ?? 'Lain-lain';
    
    if (($row['jenis'] ?? '') === 'Pemasukan') {
        $totalPemasukan += $nom;
    } else {
        $totalPengeluaran += $nom;
        if (!isset($kategoriStats[$kat])) {
            $kategoriStats[$kat] = 0;
        }
        $kategoriStats[$kat] += $nom;
    }
}

$saldoNetto = $totalPemasukan - $totalPengeluaran;

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
        <h2 class="panel-title">Filter Laporan Petty Cash</h2>
        <div style="display: flex; gap: 10px;">
            <a href="petty_cash.php" class="btn btn-secondary">
                <span class="material-symbols-outlined">arrow_back</span>
                <span>Kembali ke Petty Cash</span>
            </a>
            <a href="export.php?type=petty_cash&bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Export Excel (.xls)</span>
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                <span>Cetak PDF Resmi</span>
            </button>
        </div>
    </div>

    <form method="GET" action="laporan_petty_cash.php">
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

<!-- Report Document View -->
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
        <h2 style="font-size: 18px; font-weight: 700; text-transform: uppercase;">LAPORAN ARUS KAS KECIL (PETTY CASH)</h2>
        <div style="font-family: var(--font-sans); font-size: 13px; color: var(--text-muted);">
            Periode: <strong><?= $monthsIndo[$selectedMonth] ?> <?= $selectedYear ?></strong>
        </div>
    </div>

    <!-- Summary Stats Cards -->
    <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Total Pemasukan</span>
            <div class="stat-value" style="color: var(--status-success-text);">Rp <?= number_format($totalPemasukan, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Total Pengeluaran</span>
            <div class="stat-value" style="color: var(--status-danger-text);">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="padding: 16px;">
            <span class="stat-label">Saldo Netto Periode</span>
            <div class="stat-value" style="color: <?= $saldoNetto >= 0 ? 'var(--text-primary)' : 'var(--status-danger-text)' ?>;">
                Rp <?= number_format($saldoNetto, 0, ',', '.') ?>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Table -->
    <?php if (!empty($kategoriStats)): ?>
        <div style="margin-bottom: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 12px;">Rincian Pengeluaran per Kategori:</h3>
            <div class="table-container">
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Kategori Transaksi</th>
                            <th style="text-align: right;">Total Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategoriStats as $katName => $katTotal): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($katName) ?></strong></td>
                                <td style="text-align: right; color: var(--status-danger-text);"><strong>Rp <?= number_format($katTotal, 0, ',', '.') ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detail Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Kategori & Keterangan</th>
                    <th style="text-align: right;">Nominal</th>
                    <th style="text-align: right;">Saldo Akhir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada transaksi petty cash pada periode yang dipilih</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= date('d/m/Y', strtotime($row['tanggal'] ?? 'now')) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= ($row['jenis'] ?? '') === 'Pemasukan' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($row['jenis'] ?? 'Pengeluaran') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['kategori'] ?? $row['keterangan'] ?? 'Transaksi Kas') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></small>
                            </td>
                            <td style="text-align: right; color: <?= ($row['jenis'] ?? '') === 'Pemasukan' ? 'var(--status-success-text)' : 'var(--status-danger-text)' ?>;">
                                <strong><?= ($row['jenis'] ?? '') === 'Pemasukan' ? '+' : '-' ?> Rp <?= number_format($row['nominal'] ?? 0, 0, ',', '.') ?></strong>
                            </td>
                            <td style="text-align: right;">
                                <strong>Rp <?= number_format($row['saldo_setelah'] ?? $row['nominal'] ?? 0, 0, ',', '.') ?></strong>
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
            <p>Bendahara / Kepala Unit biMBA AIUEO,</p>
            <div class="signature-space" style="height: 65px;"></div>
            <p class="signature-name"><strong>( <?= htmlspecialchars($unitLeaderVal) ?> )</strong></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
