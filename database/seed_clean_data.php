<?php
/**
 * Script Seeder Database - Mengisi Data Dummy Rapi untuk Pengujian & Demo Skripsi
 * 
 * Menghapus data acak sebelumnya dan mengisinya dengan data sampel yang bersih,
 * terstruktur, dan realistis untuk presensi karyawan dan kas kecil.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

echo "Mulai pembersihan dan pengisian data dummy baru...\n";

// 1. Pembersihan Tabel
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE presensi;");
$pdo->exec("TRUNCATE TABLE petty_cash;");
$pdo->exec("TRUNCATE TABLE qr_code;");
$pdo->exec("TRUNCATE TABLE karyawan;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 2. Insert Data Master Karyawan
$employees = [
    [1, 'Siti Rahmawati, S.Pd', 'Kepala Unit', 'siti', 1],
    [2, 'Dewi Sartika', 'Motivator Utama', 'dewi', 1],
    [3, 'Budi Santoso', 'Motivator Junior', 'budi', 1],
    [4, 'Anisa Putri', 'Staf Administrasi', 'anisa', 1],
    [5, 'Fariz Trisnaldi', 'Staf IT & Media', 'fariz', 1]
];

$stmtEmp = $pdo->prepare("INSERT INTO karyawan (id_karyawan, nama, jabatan, username, status_aktif) VALUES (?, ?, ?, ?, ?)");
foreach ($employees as $e) {
    $stmtEmp->execute($e);
}
echo "✓ Master data karyawan berhasil dibuat (5 Karyawan)\n";

// 3. Insert Data Sample Presensi untuk 7 Hari Terakhir
$today = date('Y-m-d');
$dates = [];
for ($i = 6; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

$stmtPres = $pdo->prepare("
    INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, bukti_surat, keterangan, status_validasi, raw_payload) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Skenario Presensi Rinci
$scenarios = [
    0 => [
        1 => ['07:45:10', '16:05:22', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['07:50:15', '16:02:11', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        3 => ['07:55:00', '16:01:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        4 => ['08:00:12', '16:10:45', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        5 => ['07:40:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
    ],
    1 => [
        1 => ['07:48:20', '16:00:12', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['08:25:10', '16:05:00', 'Terlambat', null, 'Macet di jalan', 'Valid'],
        3 => ['07:52:00', '16:02:30', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        4 => ['07:58:11', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        5 => ['08:30:45', '16:15:00', 'Terlambat', null, 'Kendala jaringan', 'Valid'],
    ],
    2 => [
        1 => ['07:45:00', '16:05:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => [null, null, 'Sakit', 'surat_sakit_sample.png', 'Demam tinggi (Surat Dokter)', 'Surat Admin'],
        3 => ['07:55:12', '16:01:10', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        4 => ['07:50:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        5 => ['07:42:00', '16:08:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
    ],
    3 => [
        1 => ['07:46:00', '16:02:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['07:49:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        3 => ['07:50:00', '14:30:00', 'Hadir', null, 'Izin Urusan Keluarga', 'Valid'],
        4 => ['07:55:00', '16:05:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        5 => ['07:40:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
    ],
    4 => [
        1 => ['07:45:00', '16:05:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['07:51:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        3 => ['07:54:00', '16:02:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        4 => [null, null, 'Tidak Hadir', null, 'Tanpa Keterangan', 'Sistem Auto'],
        5 => ['07:41:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
    ],
    5 => [
        1 => ['07:44:00', '16:03:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['08:20:00', '16:05:00', 'Terlambat', null, 'Hujan deras', 'Valid'],
        3 => ['07:53:00', '16:01:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        4 => ['07:56:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        5 => ['07:45:00', '16:10:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
    ],
    6 => [
        1 => ['07:45:00', '16:00:00', 'Hadir', null, 'Presensi Rutin', 'Valid'],
        2 => ['07:50:00', null, 'Hadir', null, 'Presensi Rutin', 'Valid'],
        3 => ['08:22:00', null, 'Terlambat', null, 'Presensi Masuk', 'Valid'],
        4 => [null, null, 'Izin', null, 'Izin Dinas Luar', 'Surat Admin'],
        5 => ['07:40:00', null, 'Hadir', null, 'Presensi Masuk', 'Valid'],
    ]
];

foreach ($dates as $idx => $dateStr) {
    if (isset($scenarios[$idx])) {
        foreach ($scenarios[$idx] as $empId => $pData) {
            $stmtPres->execute([
                $empId,
                $dateStr,
                $pData[0],
                $pData[1],
                $pData[2],
                $pData[3],
                $pData[4],
                $pData[5],
                'SAMPLE_PAYLOAD_AES'
            ]);
        }
    }
}
echo "✓ Data presensi sampel 7 hari berhasil diisi\n";

// Ensure Admin Account Exists
$stmtAdm = $pdo->query("SELECT id_admin FROM admin LIMIT 1");
$adminRow = $stmtAdm->fetch();
$adminId = 1;

if (!$adminRow) {
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmtInsAdm = $pdo->prepare("INSERT INTO admin (id_admin, nama, username, password) VALUES (1, ?, ?, ?)");
    $stmtInsAdm->execute(['Administrator Unit', 'admin', $passHash]);
} else {
    $adminId = intval($adminRow['id_admin']);
}

// 4. Insert Data Sample Petty Cash
$pettyTransactions = [
    [date('Y-m-d', strtotime('-5 days')), 'Pemasukan', 'Penerimaan Kas Awal Bulan', 1500000.00, 1500000.00, 'Penerimaan Kas', null],
    [date('Y-m-d', strtotime('-4 days')), 'Pengeluaran', 'Pembelian Kertas HVS A4 & Tinta Printer', 125000.00, 1375000.00, 'ATK & Cetak Dokumen', null],
    [date('Y-m-d', strtotime('-3 days')), 'Pengeluaran', 'Pembelian Sabun Cuci & Karbol Kebersihan Unit', 45000.00, 1330000.00, 'Operasional Unit & Kebersihan', null],
    [date('Y-m-d', strtotime('-2 days')), 'Pengeluaran', 'Konsumsi Rapat Bulanan Motivator', 150000.00, 1180000.00, 'Konsumsi & Rapat', null],
    [date('Y-m-d', strtotime('-1 days')), 'Pengeluaran', 'Ongkos Kurir Pengiriman Berkas Cabang', 35000.00, 1145000.00, 'Transportasi & Kurir', null],
    [date('Y-m-d'), 'Pengeluaran', 'Pembelian Map Plastik & Spidol Whiteboard', 55000.00, 1090000.00, 'ATK & Cetak Dokumen', null],
];

$stmtPc = $pdo->prepare("
    INSERT INTO petty_cash (id_admin, tanggal, jenis, keterangan, nominal, saldo_setelah, kategori, bukti_file) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($pettyTransactions as $pt) {
    $stmtPc->execute([
        $adminId,
        $pt[0],
        $pt[1],
        $pt[2],
        $pt[3],
        $pt[4],
        $pt[5],
        $pt[6]
    ]);
}
echo "✓ Data transaksi Petty Cash berhasil diisi\n";

echo "Selesai! Database berhasil diperbarui dengan data dummy yang rapi.\n";
