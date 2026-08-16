<?php
/**
 * API Validator & Processing Server Presensi QR Code AES-256
 * 
 * Endpoint backend untuk menerima payload QR Code dari scanner kamera, upload file, 
 * alat scanner USB, maupun tombol instant presensi.
 * 
 * Alur Proses:
 * 1. Validasi HTTP Request Method (Harus POST)
 * 2. Dekripsi Payload Menggunakan Algoritma Kriptografi AES-256-CBC (`decrypt_qr_payload`)
 * 3. Verifikasi Status Keaktifan & Keberadaan Data Karyawan di Database
 * 4. Pengecekan Waktu Scan (Masuk vs Keluar) & Status Tepat Waktu / Terlambat / Pulang Awal
 * 5. Penyimpanan / Pembaruan Data Presensi dan Pengembalian JSON Response Real-Time
 * 
 * @package     biMBA_AIUEO
 * @subpackage  API
 * @author      Developer Team biMBA AIUEO
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// 1. Validasi HTTP Request Method (Hanya Menerima POST Request)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan (Harus POST).']);
    exit;
}

// 2. Ambil Input Payload dari JSON Body atau Request Variable POST
$input = json_decode(file_get_contents('php://input'), true);
$rawPayload = trim($input['payload'] ?? $_POST['payload'] ?? '');

if (empty($rawPayload)) {
    echo json_encode(['success' => false, 'status' => 'Invalid', 'message' => 'Payload QR Code kosong. Harap pindai ulang.']);
    exit;
}

// 3. Dekripsi Ciphertext Payload QR Code Menggunakan AES-256-CBC
$decrypted = decrypt_qr_payload($rawPayload);

if ($decrypted === false) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'QR Code tidak dapat didekripsi / Kunci Enkripsi AES Salah.'
    ]);
    exit;
}

// Ekstrak Data Hasil Dekripsi
$empCodeStr = $decrypted['employee_id'] ?? '';
$expiresAtStr = $decrypted['expires_at'] ?? 'PERMANENT';

// Ambil ID Angka Karyawan dari Format String (misal: EMP-001 -> 1)
$empIdNum = intval(preg_replace('/[^0-9]/', '', $empCodeStr));

// 4. Verifikasi Masa Berlaku QR Code (Dilewati untuk ID Card Permanent)
if ($expiresAtStr !== 'PERMANENT') {
    $expiresAt = strtotime($expiresAtStr);
    if (!$expiresAt || $expiresAt < time()) {
        echo json_encode([
            'success' => false,
            'status' => 'Expired',
            'message' => 'QR Code telah kedaluwarsa (Expired).',
            'emp_code' => $empCodeStr
        ]);
        exit;
    }
}

// 5. Query Validasi Karyawan pada Database
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM karyawan WHERE (id_karyawan = ? OR username = ?)");
$stmt->execute([$empIdNum, $empCodeStr]);
$employee = $stmt->fetch();

if (!$employee) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'Data Karyawan tidak ditemukan di database.',
        'emp_code' => $empCodeStr
    ]);
    exit;
}

// Cek Status Keaktifan Karyawan (Jika Non-Aktif, Presensi Ditolak)
if (isset($employee['status_aktif']) && intval($employee['status_aktif']) === 0) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'Status Karyawan Non-Aktif. Presensi ditolak oleh sistem.',
        'emp_code' => $empCodeStr
    ]);
    exit;
}

// 6. Penentuan Waktu & Penyiapan Pengaturan Jam Kerja
$today = date('Y-m-d');
$currentTime = date('H:i:s');
$formattedCode = 'EMP-' . sprintf('%03d', $employee['id_karyawan']);

// Ambil Pengaturan Jam Kerja Dinamis dari Database
$workInLateSetting = get_system_setting('work_in_late', '08:15');
if (strlen($workInLateSetting) == 5) $workInLateSetting .= ':00';

$workOutSetting = get_system_setting('work_out_time', '16:00');
if (strlen($workOutSetting) == 5) $workOutSetting .= ':00';

// Cek Apakah Karyawan Sudah Memiliki Catatan Presensi Hari Ini
$stmtDup = $pdo->prepare("SELECT * FROM presensi WHERE id_karyawan = ? AND (tanggal = ? OR DATE(tanggal) = ?)");
$stmtDup->execute([$employee['id_karyawan'], $today, $today]);
$existing = $stmtDup->fetch();

if (!$existing) {
    // -----------------------------------------------------------------
    // ACTION 1: SCAN MASUK (CHECK-IN)
    // -----------------------------------------------------------------
    $isLate = (strtotime($currentTime) > strtotime($workInLateSetting));
    $statusKehadiran = $isLate ? 'Terlambat' : 'Hadir';
    $msgCheckIn = $isLate 
        ? "Presensi Masuk Dicatat (Terlambat - lewat {$workInLateSetting})" 
        : "Presensi Masuk Dicatat (Tepat Waktu)!";

    try {
        $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status, status_validasi, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([
            $employee['id_karyawan'],
            $today,
            $currentTime,
            $statusKehadiran,
            'Valid',
            $rawPayload
        ]);
    } catch (PDOException $e) {
        // Fallback untuk struktur kolom standar
        $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([
            $employee['id_karyawan'],
            $today,
            $currentTime,
            $statusKehadiran
        ]);
    }

    echo json_encode([
        'success' => true,
        'action' => 'check_in',
        'status' => $statusKehadiran,
        'message' => $msgCheckIn,
        'employee' => [
            'emp_code' => $formattedCode,
            'nama' => $employee['nama'],
            'jabatan' => $employee['jabatan']
        ],
        'tanggal' => date('d M Y', strtotime($today)),
        'jam_masuk' => $currentTime,
        'jam_keluar' => '-'
    ]);
    exit;
} else {
    // -----------------------------------------------------------------
    // RULES VERIFIKASI TANGGAL: COCOKKAN TANGGAL, BULAN, DAN TAHUN
    // Mencegah error jika karyawan lupa presensi keluar kemarin dan melakukan scan hari ini
    // -----------------------------------------------------------------
    $existDateStr = date('Y-m-d', strtotime($existing['tanggal']));
    if ($existDateStr !== $today) {
        // Tanggal presensi masuk sebelumnya berbeda dengan hari ini (Lupa presensi keluar kemarin)
        // Maka scan hari ini otomatis dianggap sebagai PRESENSI MASUK BARU HARI INI ($today)
        $isLate = (strtotime($currentTime) > strtotime($workInLateSetting));
        $statusKehadiran = $isLate ? 'Terlambat' : 'Hadir';
        $msgCheckIn = $isLate 
            ? "Presensi Masuk Dicatat (Terlambat - lewat {$workInLateSetting})" 
            : "Presensi Masuk Dicatat (Tepat Waktu)!";

        $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status, status_validasi, raw_payload) VALUES (?, ?, ?, ?, 'Valid', ?)");
        $stmtIns->execute([$employee['id_karyawan'], $today, $currentTime, $statusKehadiran, $rawPayload]);

        echo json_encode([
            'success' => true,
            'action' => 'check_in',
            'status' => $statusKehadiran,
            'message' => $msgCheckIn,
            'employee' => [
                'emp_code' => $formattedCode,
                'nama' => $employee['nama'],
                'jabatan' => $employee['jabatan']
            ],
            'tanggal' => date('d M Y', strtotime($today)),
            'jam_masuk' => $currentTime,
            'jam_keluar' => '-'
        ]);
        exit;
    }

    // -----------------------------------------------------------------
    // ACTION 2: SCAN KELUAR (CHECK-OUT) PADA HARI YANG SAMA ($today)
    // -----------------------------------------------------------------
    if (empty($existing['jam_keluar']) || $existing['jam_keluar'] === '00:00:00' || $existing['jam_keluar'] === null) {
        // Update Jam Keluar hanya jika tanggal, bulan, tahun presensi masuk sama persis dengan hari ini
        $stmtUp = $pdo->prepare("UPDATE presensi SET jam_keluar = ? WHERE id_presensi = ?");
        $stmtUp->execute([$currentTime, $existing['id_presensi']]);

        $isPulangAwal = (strtotime($currentTime) < strtotime($workOutSetting));
        $msgCheckOut = $isPulangAwal 
            ? "Presensi Keluar Dicatat (Pulang Awal - sebelum {$workOutSetting})" 
            : "Presensi Keluar Dicatat (Sesuai Jadwal Pulang)!";

        echo json_encode([
            'success' => true,
            'action' => 'check_out',
            'status' => $isPulangAwal ? 'Pulang Awal' : 'Pulang',
            'message' => $msgCheckOut,
            'employee' => [
                'emp_code' => $formattedCode,
                'nama' => $employee['nama'],
                'jabatan' => $employee['jabatan']
            ],
            'tanggal' => date('d M Y', strtotime($existing['tanggal'])),
            'jam_masuk' => $existing['jam_masuk'],
            'jam_keluar' => $currentTime
        ]);
        exit;
    } else {
        // Karyawan Sudah Melakukan Scan Masuk dan Scan Keluar Hari Ini
        echo json_encode([
            'success' => false,
            'status' => 'Complete',
            'message' => 'Presensi Hari Ini Sudah Lengkap (Masuk & Keluar). Terima Kasih!',
            'employee' => [
                'emp_code' => $formattedCode,
                'nama' => $employee['nama'],
                'jabatan' => $employee['jabatan']
            ],
            'tanggal' => date('d M Y', strtotime($existing['tanggal'])),
            'jam_masuk' => $existing['jam_masuk'],
            'jam_keluar' => $existing['jam_keluar']
        ]);
        exit;
    }
}
