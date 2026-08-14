<?php
/**
 * API Generator Payload Terenkripsi QR Code AES-256
 * 
 * Endpoint backend untuk menghasilkan ciphertext AES-256 terenkripsi
 * berdasarkan Kode Karyawan. Utilisasi oleh fitur QR Code Modal pada Halaman Karyawan 
 * dan Fitur Instant Scan Presensi.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  API
 * @author      Developer Team biMBA AIUEO
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// 1. Ambil Parameter emp_code dari URL Query String
$empCodeStr = trim($_GET['emp_code'] ?? '');

if (empty($empCodeStr)) {
    echo json_encode(['success' => false, 'message' => 'Parameter emp_code wajib diisi']);
    exit;
}

// 2. Ekstrak Angka ID Karyawan (misal: EMP-001 -> 1)
$empIdNum = intval(preg_replace('/[^0-9]/', '', $empCodeStr));

// 3. Verifikasi Keberadaan Data Karyawan pada Database
$pdo = getDB();
$stmt = $pdo->prepare("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE (id_karyawan = ? OR username = ?)");
$stmt->execute([$empIdNum, $empCodeStr]);
$emp = $stmt->fetch();

if (!$emp) {
    echo json_encode(['success' => false, 'message' => 'Data Karyawan tidak ditemukan']);
    exit;
}

// 4. Generate Encrypted AES-256 Payload (Masa Berlaku: PERMANENT / 0 Jam)
$formattedCode = 'EMP-' . sprintf('%03d', $emp['id_karyawan']);
$encryptedPayload = encrypt_qr_payload($formattedCode, 0);

if ($encryptedPayload === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menginkripsi QR Code payload']);
    exit;
}

// 5. Kembalikan Response JSON Sukses
echo json_encode([
    'success' => true,
    'emp_code' => $formattedCode,
    'nama' => $emp['nama'],
    'jabatan' => $emp['jabatan'],
    'payload' => $encryptedPayload,
    'expires_at' => 'PERMANENT'
]);
