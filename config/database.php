<?php
/**
 * Konfigurasi Database dan Pengelolaan Koneksi PDO
 * 
 * Mengatur koneksi ke database MySQL atau SQLite, serta menangani migrasi otomatis
 * tabel dan kolom baru saat ada pembaruan fitur.
 */

require_once __DIR__ . '/app.php';

// Definisi variabel koneksi database dari file konfigurasi environment
if (!defined('DB_DRIVER')) define('DB_DRIVER', env('DB_DRIVER', 'mysql'));
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'bimba_aiueo'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));

// Mengambil objek koneksi PDO tunggal (pola singleton)
function getDB() {
    static $pdo = null;

    // Gunakan kembali koneksi yang sudah terbuka
    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_DRIVER === 'mysql') {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Periksa dan sesuaikan struktur tabel otomatis
            autoMigrateTables($pdo);

            return $pdo;
        } catch (PDOException $e) {
            // Fallback otomatis ke database SQLite jika service MySQL XAMPP tidak aktif
        }
    }

    // Mode cadangan menggunakan database SQLite
    $dbDir = __DIR__ . '/../database';
    if (!file_exists($dbDir)) {
        mkdir($dbDir, 0777, true);
    }
    
    $dbFile = $dbDir . '/bimba.sqlite';
    $isNew = !file_exists($dbFile);

    try {
        $pdo = new PDO("sqlite:" . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($isNew || filesize($dbFile) === 0) {
            initDatabase($pdo);
        } else {
            autoMigrateTables($pdo);
        }
        return $pdo;
    } catch (PDOException $e) {
        die("SQLite Connection Error: " . $e->getMessage());
    }
}

// Tambahkan tabel atau kolom baru secara otomatis jika belum ada di database
function autoMigrateTables($pdo) {
    try {
        if (DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT NULL
            )");
        }
    } catch (Exception $e) {}

    // Tambah kolom opsional jika belum ada
    try { $pdo->exec("ALTER TABLE karyawan ADD status_aktif INT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE presensi ADD status_validasi VARCHAR(20) DEFAULT 'Valid'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE presensi ADD raw_payload TEXT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE presensi ADD jam_keluar TIME NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE presensi ADD bukti_surat VARCHAR(255) NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE presensi ADD keterangan TEXT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE petty_cash ADD kategori VARCHAR(50) DEFAULT 'Operasional Unit'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE petty_cash ADD saldo_setelah DECIMAL(15,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE petty_cash ADD bukti_file VARCHAR(255) NULL"); } catch (Exception $e) {}
}

/**
 * Otomatisasi Pencatatan Status "Tidak Hadir" (Alpha)
 * Jika hingga batas jam kerja berakhir seorang karyawan aktif tidak melakukan presensi
 * dan tidak ada pengajuan Izin/Sakit, sistem secara otomatis mencatat sebagai "Tidak Hadir".
 */
function auto_mark_absent_employees($pdo) {
    try {
        $todayStr = date('Y-m-d');
        $currentTimeStr = date('H:i:s');
        $workOutTime = get_system_setting('work_out_time', '16:00');

        $stmtEmps = $pdo->query("SELECT id_karyawan FROM karyawan WHERE COALESCE(status_aktif, 1) = 1");
        $activeEmployees = $stmtEmps->fetchAll(PDO::FETCH_COLUMN);

        if (empty($activeEmployees)) return;

        // Ambil rentang tanggal 14 hari ke belakang sampai hari ini
        $datesToCheck = [];
        for ($i = 0; $i <= 14; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            // Hanya periksa hari ini jika sudah melewati jam keluar
            if ($date === $todayStr && $currentTimeStr < $workOutTime) {
                continue;
            }
            $datesToCheck[] = $date;
        }

        foreach ($datesToCheck as $targetDate) {
            foreach ($activeEmployees as $empId) {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM presensi WHERE id_karyawan = ? AND (tanggal = ? OR DATE(tanggal) = ?)");
                $stmtCheck->execute([$empId, $targetDate, $targetDate]);
                if (intval($stmtCheck->fetchColumn()) === 0) {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, status_validasi, raw_payload) 
                        VALUES (?, ?, NULL, NULL, 'Tidak Hadir', 'Sistem Auto', 'AUTO_ABSENT')
                    ");
                    $stmtIns->execute([$empId, $targetDate]);
                }
            }
        }
    } catch (Exception $e) {}
}

// Ambil nilai konfigurasi sistem dari tabel settings
function get_system_setting($key, $default = '') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        if ($res && isset($res['setting_value']) && trim($res['setting_value']) !== '') {
            return $res['setting_value'];
        }
    } catch (Exception $e) {}
    return $default;
}

// Simpan atau perbarui nilai pengaturan ke tabel settings
function save_system_setting($key, $value) {
    try {
        $pdo = getDB();
        if (DB_DRIVER === 'mysql') {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        } else {
            $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Tutup koneksi database
function closeDB() {
    $pdo = getDB();
    $pdo = null;
}

// Inisialisasi struktur tabel awal dan data sampel jika database baru dibuat
function initDatabase($pdo) {
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    // Buat akun admin awal jika belum ada (admin / admin123)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin WHERE username = ?");
    $stmt->execute(['admin']);
    $res = $stmt->fetch();

    if ($res['count'] == 0) {
        $passHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin (nama, username, password) VALUES (?, ?, ?)");
        $stmt->execute(['Administrator Unit', 'admin', $passHash]);
    }

    // Isi data sampel karyawan jika masih kosong
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM karyawan");
    $stmt->execute();
    $resEmp = $stmt->fetch();

    if ($resEmp['count'] == 0) {
        $employees = [
            ['Siti Rahmawati, S.Pd', 'Kepala Unit', 'siti', 1],
            ['Dewi Sartika', 'Motivator Utama', 'dewi', 1],
            ['Budi Santoso', 'Motivator Junior', 'budi', 1],
            ['Anisa Putri', 'Staf Administrasi', 'anisa', 1]
        ];
        $stmt = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, username, status_aktif) VALUES (?, ?, ?, ?)");
        foreach ($employees as $emp) {
            $stmt->execute($emp);
        }
    }

    // Isi data sampel transaksi kas kecil jika masih kosong
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM petty_cash");
    $stmt->execute();
    $resPc = $stmt->fetch();

    if ($resPc['count'] == 0) {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO petty_cash (id_admin, tanggal, jenis, kategori, nominal, saldo_setelah, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, $today, 'Pemasukan', 'Saldo Awal', 1500000, 1500000, 'Saldo kas kecil awal unit']);
        $stmt->execute([1, $today, 'Pengeluaran', 'ATK & Cetak', 75000, 1425000, 'Pembelian kertas HVS A4 2 rim']);
    }
}
