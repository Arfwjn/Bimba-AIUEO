<?php
/**
 * Modul Export Data Laporan ke File Microsoft Excel (.xls)
 * 
 * Menghasilkan file lembar kerja Microsoft Excel berformat Native XML/HTML Spreadsheet 
 * dengan header resmi lembaga, format mata uang Rupiah, dan blok tanda tangan Kepala Unit.
 * 
 * Tipe Export yang Didukung:
 * 1. type = presensi    : Export Rekapitulasi Presensi Karyawan
 * 2. type = petty_cash  : Export Arus Kas Kecil (Petty Cash)
 * 3. type = karyawan    : Export Master Data Karyawan
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Exports
 * @author      Developer Team biMBA AIUEO
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

// 1. Ambil Parameter Tipe Export dari URL Query String
$type = trim($_GET['type'] ?? 'presensi');
$pdo = getDB();

// 2. Set Response Header untuk Mendownload File Microsoft Excel (.xls)
$filename = "export_{$type}_" . date('Ymd_His') . ".xls";
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// 3. Ambil Identitas Lembaga & Kepala Unit dari Pengaturan Sistem
$unitNameVal = get_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan');
$unitLeaderVal = get_system_setting('unit_leader', 'Siti Rahmawati, S.Pd');
$unitLocationVal = get_system_setting('unit_location', 'Jakarta');

// 4. Struktur HTML XML Microsoft Excel
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name><?= ucfirst($type) ?></x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    .title { font-size: 16pt; font-weight: bold; text-align: center; }
    .subtitle { font-size: 11pt; color: #555555; text-align: center; margin-bottom: 15px; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th { background-color: #2563EB; color: #FFFFFF; font-weight: bold; border: 1px solid #000000; padding: 10px; text-align: center; font-size: 11pt; }
    td { border: 1px solid #D1D5DB; padding: 8px 10px; color: #111111; vertical-align: middle; }
    tr:nth-child(even) td { background-color: #F9FAFB; }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .badge-success { background-color: #DCFCE7; color: #166534; font-weight: bold; text-align: center; }
    .badge-warning { background-color: #FEF3C7; color: #92400E; font-weight: bold; text-align: center; }
    .badge-danger { background-color: #FEE2E2; color: #991B1B; font-weight: bold; text-align: center; }
</style>
</head>
<body>

<?php if ($type === 'presensi'): ?>
    <?php
    // Filtering Rekapitulasi Presensi Berdasarkan Tanggal Harian, Bulan, Tahun, dan ID Karyawan
    $selectedDate = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
    $month = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
    $year = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
    $empId = isset($_GET['karyawan_id']) ? intval($_GET['karyawan_id']) : 0;

    $monthsIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

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
            $params = [$month, $year];
        } else {
            $sql = "
                SELECT p.*, k.nama, k.jabatan 
                FROM presensi p 
                JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
                WHERE strftime('%m', p.tanggal) = ? AND strftime('%Y', p.tanggal) = ?
            ";
            $params = [sprintf('%02d', $month), (string)$year];
        }
    }

    if ($empId > 0) {
        $sql .= " AND p.id_karyawan = ?";
        $params[] = $empId;
    }
    $sql .= " ORDER BY p.tanggal DESC, p.jam_masuk ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    ?>

    <!-- Judul Header Laporan Presensi -->
    <div class="title"><?= htmlspecialchars(strtoupper($unitNameVal)) ?></div>
    <div class="subtitle">LAPORAN REKAPITULASI PRESENSI KARYAWAN - Periode: <?= $monthsIndo[$month] ?> <?= $year ?></div>

    <!-- Tabel Data Rekapitulasi Presensi -->
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kode Karyawan</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Jam Masuk</th>
                <th>Status Presensi Masuk</th>
                <th>Jam Keluar</th>
                <th>Status Presensi Keluar</th>
                <th>Bukti Surat Izin/Sakit</th>
                <th>Validasi System</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11" class="center">Tidak ada data presensi pada periode ini</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($rows as $r): 
                    $b = get_attendance_detail_badges($r);
                ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td class="center"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td class="center">EMP-<?= sprintf('%03d', $r['id_karyawan']) ?></td>
                        <td class="bold"><?= htmlspecialchars($r['nama']) ?></td>
                        <td><?= htmlspecialchars($r['jabatan']) ?></td>
                        <td class="center"><?= htmlspecialchars($r['jam_masuk'] ?? '-') ?></td>
                        <td class="center <?= $b['masuk']['class'] === 'success' ? 'badge-success' : ($b['masuk']['class'] === 'warning' ? 'badge-warning' : 'badge-danger') ?>">
                            <?= htmlspecialchars($b['masuk']['text']) ?>
                        </td>
                        <td class="center"><?= !empty($r['jam_keluar']) ? htmlspecialchars($r['jam_keluar']) : '-' ?></td>
                        <td class="center <?= $b['keluar']['class'] === 'success' ? 'badge-success' : ($b['keluar']['class'] === 'warning' ? 'badge-warning' : 'badge-danger') ?>">
                            <?= htmlspecialchars($b['keluar']['text']) ?>
                        </td>
                        <td class="center">
                            <?= !empty($r['bukti_surat']) ? 'Ada Surat (' . htmlspecialchars($r['bukti_surat']) . ')' : '-' ?>
                        </td>
                        <td class="center">
                            <?= in_array($r['status'], ['Izin', 'Sakit']) ? 'Surat Admin' : ($r['status'] === 'Tidak Hadir' ? 'Sistem Auto' : 'Valid (AES-256)') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($type === 'petty_cash'): ?>
    <?php
    // Filtering Transaksi Petty Cash Berdasarkan Harian, Bulanan, Tahunan, Jenis, dan Kata Kunci
    $selectedDate = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
    $month = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
    $year = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
    $modeTahun = isset($_GET['mode_tahun']) ? intval($_GET['mode_tahun']) : 0;
    $filterJenis = trim($_GET['jenis'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $monthsIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $sql = "SELECT * FROM petty_cash WHERE 1=1";
    $params = [];

    if (!empty($selectedDate)) {
        $sql .= " AND (tanggal = ? OR DATE(tanggal) = ?)";
        $params[] = $selectedDate;
        $params[] = $selectedDate;
        $periodeLabel = "Harian: " . date('d F Y', strtotime($selectedDate));
    } elseif ($modeTahun === 1) {
        if (DB_DRIVER === 'mysql') {
            $sql .= " AND YEAR(tanggal) = ?";
        } else {
            $sql .= " AND strftime('%Y', tanggal) = ?";
        }
        $params[] = (string)$year;
        $periodeLabel = "Tahun " . $year;
    } else {
        if (DB_DRIVER === 'mysql') {
            $sql .= " AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
            $params[] = $month;
            $params[] = $year;
        } else {
            $sql .= " AND strftime('%m', tanggal) = ? AND strftime('%Y', tanggal) = ?";
            $params[] = sprintf('%02d', $month);
            $params[] = (string)$year;
        }
        $periodeLabel = $monthsIndo[$month] . " " . $year;
    }

    if (!empty($filterJenis)) {
        $sql .= " AND jenis = ?";
        $params[] = $filterJenis;
    }
    if (!empty($search)) {
        $sql .= " AND (keterangan LIKE ? OR jenis LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY tanggal ASC, id_transaksi ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    ?>

    <!-- Judul Header Laporan Petty Cash -->
    <div class="title"><?= htmlspecialchars(strtoupper($unitNameVal)) ?></div>
    <div class="subtitle">LAPORAN ARUS KAS KECIL (PETTY CASH) - Periode: <?= htmlspecialchars($periodeLabel) ?></div>

    <!-- Tabel Data Transaksi Kas Kecil -->
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jenis Transaksi</th>
                <th>Kategori / Keterangan</th>
                <th>Nominal (Rp)</th>
                <th>Saldo Akhir (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="center">Tidak ada data transaksi petty cash</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($rows as $r): ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td class="center"><?= date('d/m/Y', strtotime($r['tanggal'] ?? 'now')) ?></td>
                        <td class="center bold"><?= htmlspecialchars($r['jenis'] ?? 'Pengeluaran') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['kategori'] ?? $r['keterangan'] ?? 'Transaksi Kas') ?></strong><br>
                            <small><?= htmlspecialchars($r['keterangan'] ?? '-') ?></small>
                        </td>
                        <td class="right bold">Rp <?= number_format($r['nominal'] ?? 0, 0, ',', '.') ?></td>
                        <td class="right bold">Rp <?= number_format($r['saldo_setelah'] ?? $r['nominal'] ?? 0, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($type === 'karyawan'): ?>
    <?php
    // Export Master Data Karyawan
    $stmt = $pdo->query("SELECT * FROM karyawan ORDER BY id_karyawan DESC");
    $rows = $stmt->fetchAll();
    ?>

    <!-- Judul Header Daftar Karyawan -->
    <div class="title"><?= htmlspecialchars(strtoupper($unitNameVal)) ?></div>
    <div class="subtitle">DAFTAR KARYAWAN & STAF MOTIVATOR</div>

    <!-- Tabel Master Data Karyawan -->
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Karyawan</th>
                <th>Nama Lengkap Karyawan</th>
                <th>Jabatan</th>
                <th>Status Aktif</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="center">Tidak ada data karyawan</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($rows as $r): ?>
                    <?php $isAktif = isset($r['status_aktif']) ? intval($r['status_aktif']) : 1; ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td class="center bold">EMP-<?= sprintf('%03d', $r['id_karyawan']) ?></td>
                        <td class="bold"><?= htmlspecialchars($r['nama']) ?></td>
                        <td><?= htmlspecialchars($r['jabatan']) ?></td>
                        <td class="<?= $isAktif === 1 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $isAktif === 1 ? 'Aktif' : 'Non-Aktif' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Blok Tanda Tangan Resmi Kepala Unit -->
<br><br>
<table style="border: none;">
    <tr style="border: none;">
        <td style="border: none;"></td>
        <td style="border: none; text-align: center;" colspan="3">
            <?= htmlspecialchars($unitLocationVal) ?>, <?= date('d F Y') ?><br>
            Kepala Unit biMBA AIUEO,<br><br><br><br>
            <strong>( <?= htmlspecialchars($unitLeaderVal) ?> )</strong>
        </td>
    </tr>
</table>

</body>
</html>
