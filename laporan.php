<?php
// laporan.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Laporan Rekapitulasi Presensi';
$pageBreadcrumb = 'Dashboard > Laporan Presensi';

$pdo = getDB();

// Otomatisasi Pencatatan Status "Tidak Hadir" (Alpha)
auto_mark_absent_employees($pdo);

$message = '';
$error = '';

// Handle POST Input Izin / Sakit Manual oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan CSRF Token gagal.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'input_izin_sakit') {
            $empId = intval($_POST['id_karyawan'] ?? 0);
            $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
            $status = trim($_POST['status'] ?? 'Izin');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $buktiFile = null;

            if ($empId > 0 && in_array($status, ['Izin', 'Sakit'])) {
                if (isset($_FILES['bukti_surat']) && $_FILES['bukti_surat']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['bukti_surat']['tmp_name'];
                    $fileName = $_FILES['bukti_surat']['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (in_array($ext, $allowedExts)) {
                        $uploadDir = __DIR__ . '/assets/uploads/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $newFileName = 'surat_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                            $buktiFile = $newFileName;
                        }
                    }
                }

                $stmtCheck = $pdo->prepare("SELECT id_presensi FROM presensi WHERE id_karyawan = ? AND (tanggal = ? OR DATE(tanggal) = ?)");
                $stmtCheck->execute([$empId, $tanggal, $tanggal]);
                $existRow = $stmtCheck->fetch();

                if ($existRow) {
                    $stmtUpd = $pdo->prepare("
                        UPDATE presensi 
                        SET status = ?, keterangan = ?, bukti_surat = COALESCE(?, bukti_surat), status_validasi = 'Surat Izin/Sakit' 
                        WHERE id_presensi = ?
                    ");
                    $stmtUpd->execute([$status, $keterangan, $buktiFile, $existRow['id_presensi']]);
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, keterangan, bukti_surat, status_validasi, raw_payload) 
                        VALUES (?, ?, NULL, NULL, ?, ?, ?, 'Surat Izin/Sakit', 'MANUAL_PERMIT')
                    ");
                    $stmtIns->execute([$empId, $tanggal, $status, $keterangan, $buktiFile]);
                }

                $message = "Status {$status} karyawan berhasil dicatat dan diproses!";
            } else {
                $error = 'Harap pilih karyawan dan status yang valid.';
            }
        }
    }
}

// Filter parameters
$selectedDate = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
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
if (!empty($selectedDate)) {
    $sql = "
        SELECT p.*, k.nama, k.jabatan 
        FROM presensi p 
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
        WHERE (p.tanggal = ? OR DATE(p.tanggal) = ?)
    ";
    $params = [$selectedDate, $selectedDate];
} else {
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
}

if ($selectedKaryawan > 0) {
    $sql .= " AND p.id_karyawan = ?";
    $params[] = $selectedKaryawan;
}
$sql .= " ORDER BY p.tanggal DESC, p.jam_masuk ASC";

// Build Query for All Rows (Summary Counters) & Paginated Table (Max 5 per page)
$stmtAll = $pdo->prepare($sql);
$stmtAll->execute($params);
$allReportData = $stmtAll->fetchAll();

// Summary Counters for whole period
$totalHadir = 0;
$totalTerlambat = 0;
$totalIzinSakit = 0;
$totalAlpha = 0;

foreach ($allReportData as $row) {
    if ($row['status'] === 'Hadir') $totalHadir++;
    elseif ($row['status'] === 'Terlambat') $totalTerlambat++;
    elseif (in_array($row['status'], ['Izin', 'Sakit'])) $totalIzinSakit++;
    else $totalAlpha++;
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

$monthsIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success no-print" style="margin-bottom: 20px;">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger no-print" style="margin-bottom: 20px;">
        <span class="material-symbols-outlined">error</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<!-- Filter Panel -->
<style>
@media print {
    .no-print {
        display: none !important;
    }
    .panel {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    body {
        background: white !important;
    }
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
            <button type="button" class="btn btn-secondary" onclick="openModal('modalInputIzinSakit')">
                <span class="material-symbols-outlined">description</span>
                <span>Input Izin / Sakit</span>
            </button>
            <a href="export.php?type=presensi&bulan=<?= $selectedMonth ?>&tahun=<?= $selectedYear ?>&tanggal=<?= urlencode($selectedDate) ?>&karyawan_id=<?= $selectedKaryawan ?>" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Export Excel (.xls)</span>
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                <span>Cetak PDF Resmi</span>
            </button>
        </div>
    </div>

    <form method="GET" action="laporan.php" id="laporanFilterForm">
        <input type="hidden" name="bulan" id="hiddenBulanInput" value="<?= $selectedMonth ?>">
        <input type="hidden" name="tahun" id="hiddenTahunInput" value="<?= $selectedYear ?>">
        <input type="hidden" name="tanggal" id="hiddenTanggalInput" value="<?= htmlspecialchars($selectedDate) ?>">

        <div class="form-row" style="align-items: flex-end;">
            <!-- Single Unified Custom Date & Month Picker Component -->
            <div class="form-group" style="position: relative; flex: 2; min-width: 260px;">
                <label class="form-label">Periode / Tanggal Laporan</label>
                <div class="custom-date-picker-trigger" onclick="toggleUnifiedPicker(event)">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted);">calendar_month</span>
                    <span id="unifiedPickerText" style="font-weight: 600; font-size: 14px; font-family: var(--font-sans);">
                        <?php if (!empty($selectedDate)): ?>
                            <?= date('d F Y', strtotime($selectedDate)) ?>
                        <?php else: ?>
                            <?= $monthsIndo[$selectedMonth] ?> <?= $selectedYear ?>
                        <?php endif; ?>
                    </span>
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted); margin-left: auto;">expand_more</span>
                </div>

                <!-- Unified Popover Panel -->
                <div id="unifiedPickerPopover" class="unified-picker-popover" style="display: none;" onclick="event.stopPropagation();">
                    <!-- Tab Headers -->
                    <div class="picker-tabs">
                        <button type="button" class="picker-tab <?= empty($selectedDate) ? 'active' : '' ?>" id="tabBtnMonth" onclick="switchPickerTab('month')">Bulan & Tahun</button>
                        <button type="button" class="picker-tab <?= !empty($selectedDate) ? 'active' : '' ?>" id="tabBtnDay" onclick="switchPickerTab('day')">Tanggal Harian</button>
                    </div>

                    <!-- Tab 1: Bulan & Tahun -->
                    <div id="tabContentMonth" style="display: <?= empty($selectedDate) ? 'block' : 'none' ?>;">
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
                                        class="month-chip <?= (empty($selectedDate) && $mNum === $selectedMonth) ? 'active' : '' ?>" 
                                        onclick="selectPickerMonth(<?= $mNum ?>)">
                                    <?= $mLabel ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tab 2: Tanggal Harian -->
                    <div id="tabContentDay" style="display: <?= !empty($selectedDate) ? 'block' : 'none' ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <label class="form-label" style="margin: 0;">Pilih Tanggal Spesifik:</label>
                            <?php if (!empty($selectedDate)): ?>
                                <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 8px;" onclick="clearSpecificDate()">Reset ke Bulanan</button>
                            <?php endif; ?>
                        </div>
                        <input type="date" id="inputNativeDate" class="form-control" style="height: 38px; font-size: 13px; cursor: pointer;" value="<?= htmlspecialchars($selectedDate) ?>" onclick="try{if(typeof this.showPicker==='function')this.showPicker();}catch(e){}" onchange="selectPickerDate(this.value)">
                    </div>
                </div>
            </div>

            <!-- Custom Searchable Employee Select Component -->
            <div class="form-group custom-emp-select-wrapper" style="position: relative; flex: 1.5; min-width: 200px;">
                <label class="form-label">Pilih Karyawan</label>
                <input type="hidden" name="karyawan_id" id="laporanEmpIdInput" value="<?= $selectedKaryawan ?>">
                <div class="custom-emp-select-trigger" onclick="toggleEmpSearchPopover('popoverEmpLaporan', event)">
                    <span id="laporanEmpLabel">
                        <?php
                        $curEmpText = 'Semua Karyawan';
                        foreach ($allEmployees as $e) {
                            if ($e['id_karyawan'] === $selectedKaryawan) {
                                $curEmpText = 'EMP-' . sprintf('%03d', $e['id_karyawan']) . ' - ' . htmlspecialchars($e['nama']);
                                break;
                            }
                        }
                        echo $curEmpText;
                        ?>
                    </span>
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted);">expand_more</span>
                </div>

                <!-- Popover Panel with Live Search -->
                <div id="popoverEmpLaporan" class="emp-search-popover" style="display: none;" onclick="event.stopPropagation();">
                    <div class="emp-search-input-wrapper">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" class="emp-search-input" placeholder="Cari nama atau kode..." onkeyup="filterEmpOptions('popoverEmpLaporan', this.value)">
                    </div>
                    <div class="emp-list-options">
                        <div class="emp-option-item <?= $selectedKaryawan === 0 ? 'selected' : '' ?>" 
                             data-search-text="semua karyawan all" 
                             onclick="selectEmpOption('laporanEmpIdInput', 'laporanEmpLabel', 0, 'Semua Karyawan')">
                            <span>Semua Karyawan</span>
                        </div>
                        <?php foreach ($allEmployees as $e): 
                            $eLabel = 'EMP-' . sprintf('%03d', $e['id_karyawan']) . ' - ' . htmlspecialchars($e['nama']);
                        ?>
                            <div class="emp-option-item <?= $selectedKaryawan === $e['id_karyawan'] ? 'selected' : '' ?>" 
                                 data-search-text="<?= htmlspecialchars($e['nama'] . ' ' . $eLabel) ?>" 
                                 onclick="selectEmpOption('laporanEmpIdInput', 'laporanEmpLabel', <?= $e['id_karyawan'] ?>, '<?= addslashes($eLabel) ?>')">
                                <span><?= $eLabel ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="emp-no-result" style="display: none; padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">Karyawan tidak ditemukan</div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="flex: 1; min-width: 130px;">
                <label class="form-label" style="visibility: hidden; margin-bottom: 6px;">Aksi</label>
                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-secondary" style="flex: 1; height: 40px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 700; padding: 0 14px;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">filter_list</span>
                        <span>Tampilkan</span>
                    </button>
                    <?php if (!empty($selectedDate) || $selectedKaryawan > 0): ?>
                        <a href="laporan.php" class="btn btn-secondary" title="Reset Filter" style="height: 40px; box-sizing: border-box; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center;">
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
    if (tab === 'month') {
        document.getElementById('tabBtnMonth').classList.add('active');
        document.getElementById('tabBtnDay').classList.remove('active');
        document.getElementById('tabContentMonth').style.display = 'block';
        document.getElementById('tabContentDay').style.display = 'none';
    } else {
        document.getElementById('tabBtnDay').classList.add('active');
        document.getElementById('tabBtnMonth').classList.remove('active');
        document.getElementById('tabContentDay').style.display = 'block';
        document.getElementById('tabContentMonth').style.display = 'none';
    }
}

function changePickerYear(delta) {
    currentPickerYear += delta;
    document.getElementById('pickerYearDisplay').textContent = currentPickerYear;
}

function selectPickerMonth(mNum) {
    currentPickerMonth = mNum;
    document.getElementById('hiddenBulanInput').value = currentPickerMonth;
    document.getElementById('hiddenTahunInput').value = currentPickerYear;
    document.getElementById('hiddenTanggalInput').value = '';
    document.getElementById('laporanFilterForm').submit();
}

function selectPickerDate(val) {
    if (val) {
        document.getElementById('hiddenTanggalInput').value = val;
        document.getElementById('laporanFilterForm').submit();
    }
}

function clearSpecificDate() {
    document.getElementById('hiddenTanggalInput').value = '';
    document.getElementById('laporanFilterForm').submit();
}

document.addEventListener('click', function(e) {
    const pop = document.getElementById('unifiedPickerPopover');
    if (pop && pop.style.display === 'block') {
        pop.style.display = 'none';
    }
});
</script>

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
            <?php if (!empty($selectedDate)): ?>
                Filter Tanggal Harian: <strong><?= date('d F Y', strtotime($selectedDate)) ?></strong>
            <?php else: ?>
                Periode: <strong><?= $monthsIndo[$selectedMonth] ?> <?= $selectedYear ?></strong>
            <?php endif; ?>
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
                    <th>Nama & Jabatan Karyawan</th>
                    <th>Presensi Masuk</th>
                    <th>Presensi Keluar</th>
                    <th style="text-align: center;">Bukti Surat</th>
                    <th>Validasi System</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada data presensi pada periode yang dipilih</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($reportData as $row): 
                        $b = get_attendance_detail_badges($row);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= date('d/m/Y', strtotime($row['tanggal'])) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($row['nama']) ?></strong><br>
                                <small style="color: var(--text-muted); font-size: 11px;">EMP-<?= sprintf('%03d', $row['id_karyawan']) ?> • <?= htmlspecialchars($row['jabatan']) ?></small>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <strong style="color: var(--status-success-text); font-size: 13px;"><?= htmlspecialchars($row['jam_masuk'] ?? '-') ?></strong>
                                    <span class="badge badge-<?= $b['masuk']['class'] ?>"><?= htmlspecialchars($b['masuk']['text']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <strong style="color: var(--status-warning-text); font-size: 13px;"><?= htmlspecialchars($row['jam_keluar'] ?? '-') ?></strong>
                                    <span class="badge badge-<?= $b['keluar']['class'] ?>"><?= htmlspecialchars($b['keluar']['text']) ?></span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if (!empty($row['bukti_surat'])): ?>
                                    <a href="assets/uploads/<?= htmlspecialchars($row['bukti_surat']) ?>" target="_blank" class="btn btn-secondary btn-square" title="Lihat Surat Izin/Sakit">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">description</span>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array($row['status'], ['Izin', 'Sakit'])): ?>
                                    <span class="badge badge-info">Surat Admin</span>
                                <?php elseif ($row['status'] === 'Tidak Hadir'): ?>
                                    <span class="badge badge-danger">Sistem Auto</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Valid AES</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Render Reusable Pagination Controls (Max 5 per page) -->
    <?= render_pagination($page, $totalPages, ['bulan' => $selectedMonth, 'tahun' => $selectedYear, 'karyawan_id' => $selectedKaryawan]) ?>

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

<!-- Modal Input Izin / Sakit (Surat Dokter / Permohonan Izin) -->
<div class="modal-backdrop" id="modalInputIzinSakit">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="color: #D97706;">description</span>
                <span>Input Status Izin / Sakit Karyawan</span>
            </h3>
            <button class="btn-close" onclick="closeModal('modalInputIzinSakit')">&times;</button>
        </div>
        <form method="POST" action="laporan.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="input_izin_sakit">

            <div class="modal-body">
                <!-- Custom Searchable Employee Select -->
                <div class="form-group custom-emp-select-wrapper" style="position: relative;">
                    <label class="form-label">Pilih Karyawan</label>
                    <input type="hidden" name="id_karyawan" id="modalLaporanEmpIdInput" required>
                    <div class="custom-emp-select-trigger" onclick="toggleEmpSearchPopover('popoverEmpLaporanModal', event)">
                        <span id="modalLaporanEmpLabel" style="color: var(--text-muted);">Pilih Karyawan</span>
                        <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted);">expand_more</span>
                    </div>

                    <!-- Popover Panel with Live Search -->
                    <div id="popoverEmpLaporanModal" class="emp-search-popover" style="display: none;" onclick="event.stopPropagation();">
                        <div class="emp-search-input-wrapper">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" class="emp-search-input" placeholder="Cari nama atau kode..." onkeyup="filterEmpOptions('popoverEmpLaporanModal', this.value)">
                        </div>
                        <div class="emp-list-options">
                            <?php foreach ($allEmployees as $e): 
                                $eLabel = 'EMP-' . sprintf('%03d', $e['id_karyawan']) . ' - ' . htmlspecialchars($e['nama']);
                            ?>
                                <div class="emp-option-item" 
                                     data-search-text="<?= htmlspecialchars($e['nama'] . ' ' . $eLabel) ?>" 
                                     onclick="selectEmpOption('modalLaporanEmpIdInput', 'modalLaporanEmpLabel', <?= $e['id_karyawan'] ?>, '<?= addslashes($eLabel) ?>')">
                                    <span><?= $eLabel ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="emp-no-result" style="display: none; padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">Karyawan tidak ditemukan</div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Presensi</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jenis Status</label>
                        <select name="status" class="form-control" required>
                            <option value="Izin">Izin (Pribadi/Dinas)</option>
                            <option value="Sakit">Sakit (Surat Dokter/DM)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Gambar Surat Izin/Sakit (Foto DM)</label>
                    <input type="file" name="bukti_surat" class="form-control" accept="image/png,image/jpeg,.pdf">
                    <small style="color: var(--text-muted); font-size: 11px;">Format: PNG, JPG, JPEG, PDF (Max 2MB). Tangkapan layar / foto surat dari DM admin.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Keterangan / Alasan</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan alasan permohonan izin atau diagnosa surat dokter..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalInputIzinSakit')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Presensi Izin/Sakit</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
