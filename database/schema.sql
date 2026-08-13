-- Database Schema for biMBA AIUEO Admin System (Unified Schema)

CREATE TABLE IF NOT EXISTS admin (
    id_admin INTEGER PRIMARY KEY AUTOINCREMENT,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS qr_code (
    id_qr INTEGER PRIMARY KEY AUTOINCREMENT,
    kode_qr VARCHAR(255) UNIQUE NOT NULL,
    encrypted_data TEXT NOT NULL,
    expired DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS karyawan (
    id_karyawan INTEGER PRIMARY KEY AUTOINCREMENT,
    nama VARCHAR(100) NOT NULL,
    jabatan VARCHAR(50) NOT NULL,
    username VARCHAR(50) UNIQUE NULL,
    password VARCHAR(255) NULL,
    id_qr INTEGER NULL,
    status_aktif INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_qr) REFERENCES qr_code(id_qr) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS presensi (
    id_presensi INTEGER PRIMARY KEY AUTOINCREMENT,
    id_karyawan INTEGER NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NULL,
    jam_keluar TIME NULL,
    status VARCHAR(30) NOT NULL,
    status_validasi VARCHAR(20) DEFAULT 'Valid',
    raw_payload TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS petty_cash (
    id_transaksi INTEGER PRIMARY KEY AUTOINCREMENT,
    id_admin INTEGER NOT NULL DEFAULT 1,
    tanggal DATE NOT NULL,
    jenis VARCHAR(20) NOT NULL,
    kategori VARCHAR(50) NOT NULL DEFAULT 'Operasional Unit',
    nominal DECIMAL(15,2) NOT NULL,
    saldo_setelah DECIMAL(15,2) NOT NULL DEFAULT 0,
    keterangan TEXT NULL,
    bukti_file VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
);
