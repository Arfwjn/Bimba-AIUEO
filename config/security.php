<?php
// config/security.php
require_once __DIR__ . '/app.php';

/**
 * Send Essential Security Headers
 */
function send_security_headers() {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
send_security_headers();

/**
 * CSRF Protection Helpers
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate Uploaded File Security (MIME Inspection & Size limit)
 */
function validate_uploaded_file($fileArr, $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'], $maxSizeBytes = 2097152) {
    if (!isset($fileArr['error']) || $fileArr['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Gagal mengunggah file.'];
    }

    if ($fileArr['size'] > $maxSizeBytes) {
        return ['valid' => false, 'message' => 'Ukuran file melebihi batas maksimum 2MB.'];
    }

    $tmpPath = $fileArr['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimes)) {
        return ['valid' => false, 'message' => 'Tipe file tidak diizinkan. Hanya file JPG, PNG, dan PDF yang diperbolehkan.'];
    }

    // Check extension
    $ext = strtolower(pathinfo($fileArr['name'], PATHINFO_EXTENSION));
    $disallowedExts = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'js', 'html', 'htaccess'];
    if (in_array($ext, $disallowedExts)) {
        return ['valid' => false, 'message' => 'Ekstensi file berbahaya ditolak!'];
    }

    return ['valid' => true, 'mime' => $detectedMime, 'ext' => $ext];
}

/**
 * Encrypt QR Code Payload using AES-256-CBC
 * Uses ultra-compact format (EMP-001:NONCE) for Version 1 ultra-clear QR codes
 */
function encrypt_qr_payload($empCode, $validHours = 0) {
    $now = time();
    $expiresAt = ($validHours > 0) ? date('c', $now + ($validHours * 3600)) : 'PERMANENT';
    $nonce = substr(bin2hex(random_bytes(3)), 0, 6);

    // Compact format: EMP-001|PERMANENT|nonce
    $plainStr = "{$empCode}|{$expiresAt}|{$nonce}";

    $key = hash('sha256', AES_KEY, true); // 32 bytes binary key
    $ivLength = openssl_cipher_iv_length(AES_METHOD);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt($plainStr, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return false;
    }

    // Combine IV and ciphertext with separator
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt QR Code Payload using AES-256-CBC
 * Supports compact pipe format, JSON format, and direct employee code fallback
 */
function decrypt_qr_payload($encryptedBase64) {
    $cleanInput = trim($encryptedBase64);

    // Direct Employee Code Fallback (e.g., EMP-001)
    if (preg_match('/^EMP-\d{3,4}$/i', $cleanInput)) {
        return [
            'employee_id' => strtoupper($cleanInput),
            'expires_at' => 'PERMANENT'
        ];
    }

    try {
        $data = base64_decode($cleanInput, true);
        if ($data === false) {
            return false;
        }

        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) {
            return false;
        }

        list($iv, $ciphertext) = $parts;
        $key = hash('sha256', AES_KEY, true);

        $decrypted = openssl_decrypt($ciphertext, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return false;
        }

        // Check if compact pipe format: EMP-001|PERMANENT|nonce
        if (strpos($decrypted, '|') !== false) {
            $pipeParts = explode('|', $decrypted);
            if (count($pipeParts) >= 2) {
                return [
                    'employee_id' => trim($pipeParts[0]),
                    'expires_at' => trim($pipeParts[1])
                ];
            }
        }

        // Check if JSON format
        $payload = json_decode($decrypted, true);
        if (is_array($payload) && isset($payload['employee_id'])) {
            return [
                'employee_id' => $payload['employee_id'],
                'expires_at' => $payload['expires_at'] ?? 'PERMANENT'
            ];
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Auth Guard
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function get_logged_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'Guest',
        'nama' => $_SESSION['nama_lengkap'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'admin'
    ];
}

function is_admin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}
