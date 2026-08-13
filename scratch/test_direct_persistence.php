<?php
// scratch/test_direct_persistence.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

echo "=== START DIRECT DATABASE PERSISTENCE TEST ===\n\n";

$pdo = getDB();

// 1. Insert Karyawan
$namaEmp = "Karyawan Unit Test " . rand(100, 999);
$stmtIns = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, status_aktif) VALUES (?, ?, ?)");
$stmtIns->execute([$namaEmp, "Motivator Test", 1]);
$idKaryawan = $pdo->lastInsertId();
echo "[1] Inserted Karyawan ID: {$idKaryawan}, Nama: '{$namaEmp}'\n";

// 2. Insert QR Code
$empCodeStr = 'EMP-' . sprintf('%03d', $idKaryawan);
$payload = encrypt_qr_payload($empCodeStr, 12);
$expiredAt = date('Y-m-d H:i:s', time() + 43200);

$stmtQr = $pdo->prepare("INSERT INTO qr_code (kode_qr, encrypted_data, expired) VALUES (?, ?, ?)");
$stmtQr->execute([$empCodeStr, $payload, $expiredAt]);
$idQr = $pdo->lastInsertId();

$stmtUp = $pdo->prepare("UPDATE karyawan SET id_qr = ? WHERE id_karyawan = ?");
$stmtUp->execute([$idQr, $idKaryawan]);
echo "[2] Inserted QR Code ID: {$idQr}, Kode: '{$empCodeStr}'\n";

// 3. Insert Presensi
$today = date('Y-m-d');
$timeNow = date('H:i:s');
$stmtPres = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status, status_validasi, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
$stmtPres->execute([$idKaryawan, $today, $timeNow, 'Hadir', 'Valid', $payload]);
$idPresensi = $pdo->lastInsertId();
echo "[3] Inserted Presensi ID: {$idPresensi}, Status: 'Hadir'\n";

// 4. Insert Petty Cash
$stmtPc = $pdo->prepare("INSERT INTO petty_cash (id_admin, tanggal, jenis, kategori, nominal, saldo_setelah, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtPc->execute([1, $today, 'Pemasukan', 'Saldo Awal', 2000000, 2000000, 'Test Pemasukan Persistence']);
$idPc = $pdo->lastInsertId();
echo "[4] Inserted Petty Cash ID: {$idPc}, Nominal: Rp 2.000.000\n\n";

// Verify all records exist in DB
$checkEmp = $pdo->query("SELECT * FROM karyawan WHERE id_karyawan = {$idKaryawan}")->fetch();
$checkQr = $pdo->query("SELECT * FROM qr_code WHERE id_qr = {$idQr}")->fetch();
$checkPres = $pdo->query("SELECT * FROM presensi WHERE id_presensi = {$idPresensi}")->fetch();
$checkPc = $pdo->query("SELECT * FROM petty_cash WHERE id_transaksi = {$idPc}")->fetch();

if ($checkEmp && $checkQr && $checkPres && $checkPc) {
    echo "=> SUCCESS: ALL 4 TEST RECORDS CONFIRMED SAVED IN DATABASE!\n";
    echo "   - Karyawan: {$checkEmp['nama']}\n";
    echo "   - QR Code: {$checkQr['kode_qr']}\n";
    echo "   - Presensi: ID {$checkPres['id_presensi']} ({$checkPres['status']})\n";
    echo "   - Petty Cash: ID {$checkPc['id_transaksi']} (Rp " . number_format($checkPc['nominal']) . ")\n";
} else {
    echo "=> ERROR: Database verification failed!\n";
}

echo "\n=== DIRECT DB PERSISTENCE TEST COMPLETED ===";
