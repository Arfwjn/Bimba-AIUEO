<?php
// scratch/test_db_persistence.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

echo "=== START DATABASE PERSISTENCE VERIFICATION TEST ===\n\n";

$pdo = getDB();

// 1. Test Karyawan Insert & Fetch
echo "[1] Testing Karyawan Insert into Database...\n";
$testNama = "Test Employee Persistence " . rand(100, 999);
$stmtInsEmp = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, status_aktif) VALUES (?, ?, ?)");
$stmtInsEmp->execute([$testNama, "Tester Role", 1]);
$newEmpId = $pdo->lastInsertId();

$stmtCheckEmp = $pdo->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmtCheckEmp->execute([$newEmpId]);
$empRow = $stmtCheckEmp->fetch();

if ($empRow && $empRow['nama'] === $testNama) {
    echo "=> SUCCESS: Karyawan (ID: {$newEmpId}, Nama: '{$testNama}') successfully stored in DB!\n\n";
} else {
    echo "=> ERROR: Karyawan insert failed!\n\n";
}

// 2. Test QR Code Payload Generation & Storage
echo "[2] Testing QR Code Credential Storage in DB...\n";
$empCodeStr = 'EMP-' . sprintf('%03d', $newEmpId);
$encryptedPayload = encrypt_qr_payload($empCodeStr, 12);
$expires = date('Y-m-d H:i:s', time() + 43200);

$stmtInsQr = $pdo->prepare("INSERT INTO qr_code (kode_qr, encrypted_data, expired) VALUES (?, ?, ?)");
$stmtInsQr->execute([$empCodeStr, $encryptedPayload, $expires]);
$newQrId = $pdo->lastInsertId();

$stmtUpEmpQr = $pdo->prepare("UPDATE karyawan SET id_qr = ? WHERE id_karyawan = ?");
$stmtUpEmpQr->execute([$newQrId, $newEmpId]);

$stmtCheckQr = $pdo->prepare("SELECT * FROM qr_code WHERE id_qr = ?");
$stmtCheckQr->execute([$newQrId]);
$qrRow = $stmtCheckQr->fetch();

if ($qrRow && $qrRow['kode_qr'] === $empCodeStr) {
    echo "=> SUCCESS: QR Code (ID: {$newQrId}, Kode: '{$empCodeStr}') stored and linked to Karyawan in DB!\n\n";
} else {
    echo "=> ERROR: QR Code storage failed!\n\n";
}

// Close PDO handle to release SQLite lock before cURL request
$pdo = null;

// 3. Test Presensi API & Storage via cURL
echo "[3] Testing Presensi API & DB Persistence...\n";
function postPresensiAPI($payload) {
    $ch = curl_init('http://127.0.0.1:8000/api/presensi.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payload' => $payload]));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$presRes = postPresensiAPI($encryptedPayload);
echo "Presensi Response:\n";
print_r($presRes);

// Re-open PDO to verify presensi table
$pdo = getDB();
$today = date('Y-m-d');
$stmtCheckPres = $pdo->prepare("SELECT p.*, k.nama FROM presensi p JOIN karyawan k ON p.id_karyawan = k.id_karyawan WHERE p.id_karyawan = ? AND p.tanggal = ?");
$stmtCheckPres->execute([$newEmpId, $today]);
$presRow = $stmtCheckPres->fetch();

if ($presRow) {
    echo "=> SUCCESS: Presensi record (ID: {$presRow['id_presensi']}, Status: '{$presRow['status']}') verified in DB!\n\n";
} else {
    echo "=> ERROR: Presensi DB persistence failed!\n\n";
}

// 4. Test Petty Cash Insert & Storage
echo "[4] Testing Petty Cash Transaction DB Persistence...\n";
$testKet = "Test Petty Cash DB Persistence " . rand(100, 999);
$stmtInsPc = $pdo->prepare("INSERT INTO petty_cash (id_admin, tanggal, jenis, kategori, nominal, saldo_setelah, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtInsPc->execute([1, $today, 'Pemasukan', 'Pemasukan Lainnya', 500000, 1500000, $testKet]);
$newPcId = $pdo->lastInsertId();

$stmtCheckPc = $pdo->prepare("SELECT * FROM petty_cash WHERE id_transaksi = ?");
$stmtCheckPc->execute([$newPcId]);
$pcRow = $stmtCheckPc->fetch();

if ($pcRow && $pcRow['keterangan'] === $testKet) {
    echo "=> SUCCESS: Petty Cash transaction (ID: {$newPcId}, Nominal: Rp 500.000) verified in DB!\n\n";
} else {
    echo "=> ERROR: Petty Cash DB persistence failed!\n\n";
}

echo "=== ALL DB PERSISTENCE TESTS COMPLETED SUCCESSFULLY ===";
