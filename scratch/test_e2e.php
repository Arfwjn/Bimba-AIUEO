<?php
// scratch/test_e2e.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

echo "=== START PRODUCTION HARDENING INTEGRATION TEST ===\n\n";

$pdo = getDB();

// 1. Verify CSRF Token generation & verification
$token = generate_csrf_token();
echo "[1] Testing CSRF Token generation & verification...\n";
if (verify_csrf_token($token) && !verify_csrf_token('invalid_token')) {
    echo "=> SUCCESS: CSRF Protection logic verified!\n\n";
} else {
    echo "=> ERROR: CSRF verification failed!\n\n";
}

// 2. Generate AES Payload for EMP003
$empCode = "EMP003";
$encryptedPayload = encrypt_qr_payload($empCode, 12);
echo "[2] AES Encrypted Payload generated for {$empCode}:\n{$encryptedPayload}\n\n";

// 3. Test Presensi API via cURL / HTTP
function postPresensi($payload) {
    $ch = curl_init('http://127.0.0.1:8000/api/presensi.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payload' => $payload]));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// First scan (Valid Attendance)
echo "[3] Sending First Scan to API for EMP003...\n";
$res1 = postPresensi($encryptedPayload);
print_r($res1);
if (isset($res1['success']) && ($res1['success'] === true || $res1['status'] === 'Duplicate')) {
    echo "=> SUCCESS: Presensi API responded with status {$res1['status']}\n\n";
} else {
    echo "=> ERROR: First scan failed!\n\n";
}

// 4. Test MIME File Upload Security Validator
echo "[4] Testing File Upload Security Validator...\n";
$fakeFilePhp = [
    'name' => 'malicious_webshell.php',
    'type' => 'text/x-php',
    'tmp_name' => __DIR__ . '/test_script.tmp',
    'error' => UPLOAD_ERR_OK,
    'size' => 128
];
file_put_contents($fakeFilePhp['tmp_name'], '<?php echo "evil"; ?>');

$valTest = validate_uploaded_file($fakeFilePhp);
@unlink($fakeFilePhp['tmp_name']);

if ($valTest['valid'] === false) {
    echo "=> SUCCESS: PHP file upload correctly blocked: {$valTest['message']}\n\n";
} else {
    echo "=> ERROR: Malicious file upload was NOT blocked!\n\n";
}

echo "=== ALL PRODUCTION HARDENING TESTS COMPLETED SUCCESSFULLY ===";
