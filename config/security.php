<?php
/**
 * Konfigurasi Keamanan dan Fungsi Kriptografi
 * 
 * Berisi helper keamanan sistem seperti header HTTP, generator token CSRF,
 * inspeksi file upload, serta enkripsi dan dekripsi QR Code menggunakan AES-256-CBC.
 */

require_once __DIR__ . '/app.php';

// Kirim header keamanan standar HTTP untuk mencegah serangan web umum
function send_security_headers() {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
send_security_headers();

// Buat token CSRF acak dan simpan di session
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Buat input hidden CSRF untuk dimasukkan ke dalam form HTML
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// Validasi token CSRF dari form POST untuk mencegah pemalsuan request
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Validasi keamanan file upload (cek MIME type asli, batas ukuran, dan ekstensi berbahaya)
function validate_uploaded_file($fileArr, $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'], $maxSizeBytes = 2097152) {
    if (!isset($fileArr['error']) || $fileArr['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Gagal mengunggah file ke server.'];
    }

    if ($fileArr['size'] > $maxSizeBytes) {
        return ['valid' => false, 'message' => 'Ukuran file melebihi batas maksimum 2MB.'];
    }

    // Periksa tipe MIME asli dari isi file, bukan cuma melihat ekstensi
    $tmpPath = $fileArr['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimes)) {
        return ['valid' => false, 'message' => 'Tipe file tidak diizinkan. Hanya file JPG, PNG, dan PDF yang diperbolehkan.'];
    }

    // Pastikan tidak ada ekstensi script eksekusi yang lolos
    $ext = strtolower(pathinfo($fileArr['name'], PATHINFO_EXTENSION));
    $disallowedExts = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'js', 'html', 'htaccess'];
    if (in_array($ext, $disallowedExts)) {
        return ['valid' => false, 'message' => 'Ekstensi file berbahaya ditolak demi keamanan server.'];
    }

    return ['valid' => true, 'mime' => $detectedMime, 'ext' => $ext];
}

/**
 * Enkripsi payload QR Code menggunakan AES-256-CBC
 * 
 * Format data sebelum dienkripsi: EMP-001|PERMANENT|nonce
 * Format ciphertext hasil enkripsi: Base64 dari (IV + '::' + ciphertext)
 */
function encrypt_qr_payload($empCode, $validHours = 0) {
    $now = time();
    $expiresAt = ($validHours > 0) ? date('c', $now + ($validHours * 3600)) : 'PERMANENT';
    $nonce = substr(bin2hex(random_bytes(3)), 0, 6);

    // Susun string ringkas agar QR Code yang dihasilkan tetap berukuran besar dan mudah dibaca
    $plainStr = "{$empCode}|{$expiresAt}|{$nonce}";

    // Siapkan kunci 32-byte dari hash SHA-256 dan IV acak
    $key = hash('sha256', AES_KEY, true); 
    $ivLength = openssl_cipher_iv_length(AES_METHOD);
    $iv = random_bytes($ivLength);

    // Proses enkripsi AES-256-CBC
    $encrypted = openssl_encrypt($plainStr, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return false;
    }

    // Gabungkan IV dan ciphertext dengan pemisah '::' lalu ubah ke format Base64
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Dekripsi payload QR Code menggunakan AES-256-CBC
 * 
 * Fungsi ini mendukung dekripsi format ringkas pipa (EMP-001|PERMANENT|nonce),
 * format JSON lama, dan pembacaan langsung jika input berupa kode karyawan.
 */
function decrypt_qr_payload($encryptedBase64) {
    $cleanInput = trim($encryptedBase64);

    // Jika input langsung berupa kode karyawan seperti EMP-001
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

        // Pisahkan IV dan ciphertext berdasarkan pemisah '::'
        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) {
            return false;
        }

        list($iv, $ciphertext) = $parts;
        $key = hash('sha256', AES_KEY, true);

        // Dekripsi data menggunakan OpenSSL
        $decrypted = openssl_decrypt($ciphertext, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return false;
        }

        // Parse format ringkas: EMP-001|PERMANENT|nonce
        if (strpos($decrypted, '|') !== false) {
            $pipeParts = explode('|', $decrypted);
            if (count($pipeParts) >= 2) {
                return [
                    'employee_id' => trim($pipeParts[0]),
                    'expires_at' => trim($pipeParts[1])
                ];
            }
        }

        // Parse format JSON lama jika ada
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

// Cek apakah pengguna sudah login, jika belum arahkan ke login.php
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Ambil data ringkas pengguna yang sedang aktif di session
function get_logged_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'Guest',
        'nama' => $_SESSION['nama_lengkap'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'admin'
    ];
}

// Cek apakah pengguna memiliki hak akses sebagai admin
function is_admin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}
