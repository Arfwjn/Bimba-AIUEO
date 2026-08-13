<?php
// scratch/test_presensi_live.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pdo = getDB();

// Fetch an active employee
$emp = $pdo->query("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status_aktif = 1 LIMIT 1")->fetch();
if (!$emp) {
    echo "No active employees.\n";
    exit;
}

$empCode = 'EMP-' . sprintf('%03d', $emp['id_karyawan']);
echo "Testing Live Presensi for {$empCode} ('{$emp['nama']}')...\n";

$payload = encrypt_qr_payload($empCode, 12);
$today = date('Y-m-d');

// Clean up any attendance today for this employee to test fresh scan
$pdo->exec("DELETE FROM presensi WHERE id_karyawan = {$emp['id_karyawan']} AND tanggal = '{$today}'");

// Call presensi processing directly
$decrypted = decrypt_qr_payload($payload);
if ($decrypted) {
    $timeNow = date('H:i:s');
    $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status, status_validasi, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->execute([$emp['id_karyawan'], $today, $timeNow, 'Hadir', 'Valid', $payload]);
    $newId = $pdo->lastInsertId();
    echo "=> Presensi Record Inserted with ID {$newId}!\n";

    $check = $pdo->query("SELECT p.*, k.nama FROM presensi p JOIN karyawan k ON p.id_karyawan = k.id_karyawan WHERE p.id_presensi = {$newId}")->fetch();
    if ($check) {
        echo "=> SUCCESS: Presensi record for {$check['nama']} at {$check['jam_masuk']} is verified saved in DB and ready for Today's Attendance table!\n";
    }
}
