<?php
// api/qr_generator.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$empCode = trim($_GET['emp_code'] ?? '');

if (empty($empCode)) {
    echo json_encode(['success' => false, 'message' => 'Parameter emp_code wajib diisi']);
    exit;
}

// Extract numeric ID if passed as EMP-001 or EMP001 or integer
$empIdNum = intval(preg_replace('/[^0-9]/', '', $empCode));

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM karyawan WHERE (id_karyawan = ? OR username = ?) AND status_aktif = 1");
$stmt->execute([$empIdNum, $empCode]);
$emp = $stmt->fetch();

if (!$emp) {
    echo json_encode(['success' => false, 'message' => 'Karyawan tidak ditemukan atau status tidak aktif']);
    exit;
}

$validHours = isset($_GET['hours']) ? intval($_GET['hours']) : 0;
$formattedCode = 'EMP-' . sprintf('%03d', $emp['id_karyawan']);
$encryptedPayload = encrypt_qr_payload($formattedCode, $validHours);

if (!$encryptedPayload) {
    echo json_encode(['success' => false, 'message' => 'Gagal membuat QR Code terenkripsi']);
    exit;
}

// Save or Update to qr_code table in DB
$expiresAtDate = ($validHours > 0) ? date('Y-m-d H:i:s', time() + ($validHours * 3600)) : '2099-12-31 23:59:59';
$stmtQr = $pdo->prepare("
    INSERT INTO qr_code (kode_qr, encrypted_data, expired) 
    VALUES (?, ?, ?) 
    ON CONFLICT(kode_qr) DO UPDATE SET encrypted_data = EXCLUDED.encrypted_data, expired = EXCLUDED.expired
");

try {
    $stmtQr->execute([$formattedCode, $encryptedPayload, $expiresAtDate]);
    $idQr = $pdo->lastInsertId();

    if ($idQr) {
        $stmtUpEmp = $pdo->prepare("UPDATE karyawan SET id_qr = ? WHERE id_karyawan = ?");
        $stmtUpEmp->execute([$idQr, $emp['id_karyawan']]);
    }
} catch (Exception $e) {
    // Continue even if SQLite conflict syntax differs
}

echo json_encode([
    'success' => true,
    'emp_code' => $formattedCode,
    'id_karyawan' => $emp['id_karyawan'],
    'nama' => $emp['nama'],
    'jabatan' => $emp['jabatan'],
    'payload' => $encryptedPayload,
    'expires_at' => ($validHours > 0) ? date('d M Y H:i:s', strtotime($expiresAtDate)) : 'Selamanya (Permanen)'
]);
