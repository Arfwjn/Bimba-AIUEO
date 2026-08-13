<?php
// api/presensi.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$rawPayload = trim($input['payload'] ?? $_POST['payload'] ?? '');

if (empty($rawPayload)) {
    echo json_encode(['success' => false, 'status' => 'Invalid', 'message' => 'Payload QR Code kosong']);
    exit;
}

// 1. Decrypt AES Payload
$decrypted = decrypt_qr_payload($rawPayload);

if ($decrypted === false) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'QR Code tidak dapat didekripsi / Kunci Enkripsi AES Salah'
    ]);
    exit;
}

$empCodeStr = $decrypted['employee_id'] ?? '';
$expiresAtStr = $decrypted['expires_at'] ?? '';

// Extract numeric ID if passed as EMP-001 or 1
$empIdNum = intval(preg_replace('/[^0-9]/', '', $empCodeStr));

// 2. Validate Expiration
$expiresAt = strtotime($expiresAtStr);
if (!$expiresAt || $expiresAt < time()) {
    echo json_encode([
        'success' => false,
        'status' => 'Expired',
        'message' => 'QR Code telah kedaluwarsa (Expired)',
        'emp_code' => $empCodeStr
    ]);
    exit;
}

// 3. Validate Employee in DB
$pdo = getDB();

// Auto-migration check: Ensure supplementary columns exist in presensi table
try {
    $pdo->exec("ALTER TABLE presensi ADD status_validasi VARCHAR(20) DEFAULT 'Valid'");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE presensi ADD raw_payload TEXT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE presensi ADD jam_keluar TIME NULL");
} catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT * FROM karyawan WHERE (id_karyawan = ? OR username = ?)");
$stmt->execute([$empIdNum, $empCodeStr]);
$employee = $stmt->fetch();

if (!$employee) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'Data Karyawan tidak ditemukan di database',
        'emp_code' => $empCodeStr
    ]);
    exit;
}

// Check active status if status_aktif column exists
if (isset($employee['status_aktif']) && intval($employee['status_aktif']) === 0) {
    echo json_encode([
        'success' => false,
        'status' => 'Invalid',
        'message' => 'Status Karyawan Non-Aktif. Presensi ditolak.',
        'emp_code' => $empCodeStr
    ]);
    exit;
}

// 4. Check Existing Attendance for Today
$today = date('Y-m-d');
$currentTime = date('H:i:s');
$formattedCode = 'EMP-' . sprintf('%03d', $employee['id_karyawan']);

$stmtDup = $pdo->prepare("SELECT * FROM presensi WHERE id_karyawan = ? AND (tanggal = ? OR DATE(tanggal) = ?)");
$stmtDup->execute([$employee['id_karyawan'], $today, $today]);
$existing = $stmtDup->fetch();

if (!$existing) {
    // -------------------------------------------------------------
    // ACTION: SCAN MASUK (CHECK-IN)
    // -------------------------------------------------------------
    $statusKehadiran = (strtotime($currentTime) > strtotime('08:15:00')) ? 'Terlambat' : 'Hadir';

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
        'message' => 'Presensi Masuk Berhasil Dicatat!',
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
    // Record already exists for today
    if (empty($existing['jam_keluar']) || $existing['jam_keluar'] === '00:00:00' || $existing['jam_keluar'] === null) {
        // -------------------------------------------------------------
        // ACTION: SCAN KELUAR (CHECK-OUT)
        // -------------------------------------------------------------
        $stmtUp = $pdo->prepare("UPDATE presensi SET jam_keluar = ? WHERE id_presensi = ?");
        $stmtUp->execute([$currentTime, $existing['id_presensi']]);

        echo json_encode([
            'success' => true,
            'action' => 'check_out',
            'status' => 'Pulang',
            'message' => 'Presensi Keluar (Check-Out) Berhasil Dicatat!',
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
        // Both jam_masuk and jam_keluar already recorded today
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
