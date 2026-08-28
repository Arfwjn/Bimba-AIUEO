<?php
/**
 * Modul Laporan Rekapitulasi Kas Kecil (Petty Cash Report) biMBA AIUEO
 * 
 * Menyediakan penyaringan transaksi kas kecil (Harian, Bulanan, & Tahunan),
 * perhitungan total Pemasukan, Pengeluaran, Saldo Bersih per periode,
 * serta ekspor data ke format Excel (.xls) dan Cetak PDF Resmi Berkop Surat.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Reports
 * @author      Developer Team biMBA AIUEO
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Laporan Petty Cash';
$pageBreadcrumb = 'Dashboard > Petty Cash > Laporan Petty Cash';

$pdo = getDB();

// Parameter Penyaringan Data Laporan
$selectedDate = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$selectedMonth = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$selectedYear = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$modeTahun = isset($_GET['mode_tahun']) ? intval($_GET['mode_tahun']) : 0;

$monthsIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Fetch Dynamic Institution Settings for PDF Kop & Signature
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');
$unitLeaderVal = get_system_setting('unit_leader', 'Siti Rahmawati, S.Pd');
$unitLocationVal = get_system_setting('unit_location', 'Jakarta');
$unitAddressVal = get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta');
$unitPhoneVal = get_system_setting('unit_phone', '(021) 555-8899');
$unitEmailVal = get_system_setting('unit_email', 'info@bimba-kebanggan.sch.id');

// Fetch Petty Cash for Selected Period with MySQL / SQLite Date Compatibility
if (!empty($selectedDate)) {
    // Mode 1: Harian Spesifik
    $sql = "SELECT * FROM petty_cash WHERE (tanggal = ? OR DATE(tanggal) = ?) ORDER BY id_transaksi ASC";
    $params = [$selectedDate, $selectedDate];
    $periodeLabel = "Harian: " . date('d F Y', strtotime($selectedDate));
} elseif ($modeTahun === 1) {
    // Mode 2: Tahunan
    if (DB_DRIVER === 'mysql') {
        $sql = "SELECT * FROM petty_cash WHERE YEAR(tanggal) = ? ORDER BY tanggal ASC, id_transaksi ASC";
        $params = [$selectedYear];
    } else {
        $sql = "SELECT * FROM petty_cash WHERE strftime('%Y', tanggal) = ? ORDER BY tanggal ASC, id_transaksi ASC";
        $params = [(string)$selectedYear];
    }
    $periodeLabel = "Tahun " . $selectedYear;
} else {
    // Mode 3: Bulanan (Default)
    if (DB_DRIVER === 'mysql') {
        $sql = "SELECT * FROM petty_cash WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY id_transaksi ASC";
        $params = [$selectedMonth, $selectedYear];
    } else {
        $sql = "SELECT * FROM petty_cash WHERE strftime('%m', tanggal) = ? AND strftime('%Y', tanggal) = ? ORDER BY id_transaksi ASC";
        $params = [sprintf('%02d', $selectedMonth), (string)$selectedYear];
    }
    $periodeLabel = $monthsIndo[$selectedMonth] . " " . $selectedYear;
}

// Build Query for All Rows (Summary Counters) & Paginated Table (Max 5 per page)
$stmtAll = $pdo->prepare($sql);
$stmtAll->execute($params);
$allReportData = $stmtAll->fetchAll();

// Summary Counters for whole period
$totalPemasukan = 0;
$totalPengeluaran = 0;
$kategoriStats = [];

foreach ($allReportData as $row) {
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

// Pagination calculation
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 5;
$totalRecords = count($allReportData);
$totalPages = ceil($totalRecords / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT {$perPage} OFFSET {$offset}";

$stmtReport = $pdo->prepare($sql);
$stmtReport->execute($params);
$reportData = $stmtReport->fetchAll();

$saldoNetto = $totalPemasukan - $totalPengeluaran;

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
            <a href="export.php?type=petty_cash&bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>&tanggal=<?= urlencode($selectedDate) ?>&mode_tahun=<?= $modeTahun ?>" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Export Excel (.xls)</span>
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                <span>Cetak PDF Resmi</span>
            </button>
        </div>
    </div>

    <form method="GET" action="laporan_petty_cash.php" id="laporanFilterForm">
        <input type="hidden" name="bulan" id="hiddenBulanInput" value="<?= $selectedMonth ?>">
        <input type="hidden" name="tahun" id="hiddenTahunInput" value="<?= $selectedYear ?>">
        <input type="hidden" name="tanggal" id="hiddenTanggalInput" value="<?= htmlspecialchars($selectedDate) ?>">
        <input type="hidden" name="mode_tahun" id="hiddenModeTahunInput" value="<?= $modeTahun ?>">

        <div class="form-row" style="align-items: flex-end;">
            <!-- Single Unified Custom Date & Month Picker Component -->
            <div class="form-group" style="position: relative; flex: 2; min-width: 260px;">
                <label class="form-label">Periode Laporan Petty Cash</label>
                <div class="custom-date-picker-trigger" onclick="toggleUnifiedPicker(event)">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted);">calendar_month</span>
                    <span id="unifiedPickerText" style="font-weight: 600; font-size: 14px; font-family: var(--font-sans);">
                        <?= $periodeLabel ?>
                    </span>
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted); margin-left: auto;">expand_more</span>
                </div>

                <!-- Unified Popover Panel (3-Mode: Bulanan, Harian, Tahunan) -->
                <div id="unifiedPickerPopover" class="unified-picker-popover" style="display: none; width: 330px;" onclick="event.stopPropagation();">
                    <!-- Tab Headers -->
                    <div class="picker-tabs">
                        <button type="button" class="picker-tab <?= (empty($selectedDate) && $modeTahun === 0) ? 'active' : '' ?>" id="tabBtnMonth" onclick="switchPickerTab('month')">Bulanan</button>
                        <button type="button" class="picker-tab <?= !empty($selectedDate) ? 'active' : '' ?>" id="tabBtnDay" onclick="switchPickerTab('day')">Harian</button>
                        <button type="button" class="picker-tab <?= $modeTahun === 1 ? 'active' : '' ?>" id="tabBtnYear" onclick="switchPickerTab('year')">Tahunan</button>
                    </div>

                    <!-- Tab 1: Bulanan -->
                    <div id="tabContentMonth" style="display: <?= (empty($selectedDate) && $modeTahun === 0) ? 'block' : 'none' ?>;">
                        <div class="month-picker-header">
                            <button type="button" class="btn-picker-nav" onclick="changePickerYear(-1)">&lsaquo;</button>
                            <span id="pickerYearDisplay" style="font-weight: 700; font-size: 15px; font-family: var(--font-sans);"><?= $selectedYear ?></span>
                            <button type="button" class="btn-picker-nav" onclick="changePickerYear(1)">&rsaquo;</button>
                        </div>
                        <div class="month-picker-grid">
                            <?php 
                            $shortMonths = [1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun', 7=>'Jul', 8=>'Agu', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des'];
                            foreach ($shortMonths as $mNum => $mLabel): 
                            ?>
                                <button type="button" 
                                        class="month-chip <?= (empty($selectedDate) && $modeTahun === 0 && $mNum === $selectedMonth) ? 'active' : '' ?>" 
                                        onclick="selectPickerMonth(<?= $mNum ?>)">
                                    <?= $mLabel ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tab 2: Harian Spesifik -->
                    <div id="tabContentDay" style="display: <?= !empty($selectedDate) ? 'block' : 'none' ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <label class="form-label" style="margin: 0;">Pilih Tanggal Spesifik:</label>
                            <?php if (!empty($selectedDate)): ?>
                                <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 8px;" onclick="clearSpecificFilter()">Reset ke Bulanan</button>
                            <?php endif; ?>
                        </div>
                        <input type="date" id="inputNativeDate" class="form-control" style="height: 38px; font-size: 13px; cursor: pointer;" value="<?= htmlspecialchars($selectedDate) ?>" onclick="try{if(typeof this.showPicker==='function')this.showPicker();}catch(e){}" onchange="selectPickerDate(this.value)">
                    </div>

                    <!-- Tab 3: Tahunan Full -->
                    <div id="tabContentYear" style="display: <?= $modeTahun === 1 ? 'block' : 'none' ?>;">
                        <div style="text-align: center; padding: 10px 0;">
                            <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--text-muted);">
                                Laporan Kas Kecil Rekapitulasi Full 1 Tahun
                            </div>
                            <div class="month-picker-header" style="justify-content: center; gap: 20px; border: none; margin-bottom: 14px;">
                                <button type="button" class="btn-picker-nav" onclick="changePickerYear(-1)">&lsaquo;</button>
                                <span id="pickerYearDisplayTab3" style="font-weight: 700; font-size: 18px; font-family: var(--font-sans);"><?= $selectedYear ?></span>
                                <button type="button" class="btn-picker-nav" onclick="changePickerYear(1)">&rsaquo;</button>
                            </div>
                            <button type="button" class="btn btn-primary" style="width: 100%; height: 38px; font-size: 13px; font-weight: 700;" onclick="selectYearlyReport()">
                                Tampilkan Laporan Tahun <span class="lblYearTarget"><?= $selectedYear ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tampilkan Button Layout -->
            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label class="form-label" style="visibility: hidden; margin-bottom: 6px;">Aksi</label>
                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-secondary" style="flex: 1; height: 40px; min-height: 40px; max-height: 40px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; font-weight: 700; padding: 0 14px;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">filter_list</span>
                        <span>Tampilkan</span>
                    </button>
                    <?php if (!empty($selectedDate) || $modeTahun === 1): ?>
                        <a href="laporan_petty_cash.php" class="btn btn-secondary" title="Reset Filter" style="height: 40px; min-height: 40px; max-height: 40px; box-sizing: border-box; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">restart_alt</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentPickerYear = <?= $selectedYear ?>;
let currentPickerMonth = <?= $selectedMonth ?>;

function toggleUnifiedPicker(e) {
    e.stopPropagation();
    const pop = document.getElementById('unifiedPickerPopover');
    if (pop.style.display === 'none' || !pop.style.display) {
        pop.style.display = 'block';
    } else {
        pop.style.display = 'none';
    }
}

function switchPickerTab(tab) {
    const tabs = ['Month', 'Day', 'Year'];
    tabs.forEach(t => {
        const btn = document.getElementById('tabBtn' + t);
        const cnt = document.getElementById('tabContent' + t);
        if (t.toLowerCase() === tab) {
            btn.classList.add('active');
            cnt.style.display = 'block';
        } else {
            btn.classList.remove('active');
            cnt.style.display = 'none';
        }
    });
}

function changePickerYear(delta) {
    currentPickerYear += delta;
    document.getElementById('pickerYearDisplay').textContent = currentPickerYear;
    if (document.getElementById('pickerYearDisplayTab3')) {
        document.getElementById('pickerYearDisplayTab3').textContent = currentPickerYear;
    }
    document.querySelectorAll('.lblYearTarget').forEach(el => el.textContent = currentPickerYear);
}

function selectPickerMonth(mNum) {
    currentPickerMonth = mNum;
    document.getElementById('hiddenBulanInput').value = currentPickerMonth;
    document.getElementById('hiddenTahunInput').value = currentPickerYear;
    document.getElementById('hiddenTanggalInput').value = '';
    document.getElementById('hiddenModeTahunInput').value = '0';
    document.getElementById('laporanFilterForm').submit();
}

function selectPickerDate(val) {
    if (val) {
        document.getElementById('hiddenTanggalInput').value = val;
        document.getElementById('hiddenModeTahunInput').value = '0';
        document.getElementById('laporanFilterForm').submit();
    }
}

function selectYearlyReport() {
    document.getElementById('hiddenTahunInput').value = currentPickerYear;
    document.getElementById('hiddenModeTahunInput').value = '1';
    document.getElementById('hiddenTanggalInput').value = '';
    document.getElementById('laporanFilterForm').submit();
}

function clearSpecificFilter() {
    document.getElementById('hiddenTanggalInput').value = '';
    document.getElementById('hiddenModeTahunInput').value = '0';
    document.getElementById('laporanFilterForm').submit();
}

document.addEventListener('click', function(e) {
    const pop = document.getElementById('unifiedPickerPopover');
    if (pop && pop.style.display === 'block') {
        pop.style.display = 'none';
    }
});
</script>

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
            Periode: <strong><?= htmlspecialchars($periodeLabel) ?></strong>
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

    <!-- Render Reusable Pagination Controls (Max 5 per page) -->
    <?= render_pagination($page, $totalPages, ['bulan' => $selectedMonth, 'tahun' => $selectedYear]) ?>

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
