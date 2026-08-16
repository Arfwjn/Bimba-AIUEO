-- ============================================================
-- SQL DUMP SCHEMA & DATASEEDER SKRIPSI BIMBA AIUEO
-- Sistem Presensi Karyawan QR Code AES-256 & Kas Kecil
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Struktur Tabel Admin
CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Struktur Tabel Karyawan
CREATE TABLE IF NOT EXISTS `karyawan` (
  `id_karyawan` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `status_aktif` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Struktur Tabel Presensi
CREATE TABLE IF NOT EXISTS `presensi` (
  `id_presensi` INT AUTO_INCREMENT PRIMARY KEY,
  `id_karyawan` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME NULL,
  `jam_keluar` TIME NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Hadir',
  `bukti_surat` VARCHAR(255) NULL,
  `keterangan` TEXT NULL,
  `status_validasi` VARCHAR(20) DEFAULT 'Valid',
  `raw_payload` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_presensi_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Struktur Tabel Petty Cash
CREATE TABLE IF NOT EXISTS `petty_cash` (
  `id_transaksi` INT AUTO_INCREMENT PRIMARY KEY,
  `id_admin` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `jenis` ENUM('Pemasukan','Pengeluaran') NOT NULL,
  `kategori` VARCHAR(50) DEFAULT 'Operasional Unit',
  `keterangan` TEXT NOT NULL,
  `nominal` DECIMAL(15,2) NOT NULL,
  `saldo_setelah` DECIMAL(15,2) DEFAULT 0,
  `bukti_file` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_petty_cash_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Struktur Tabel Settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('unit_name', 'biMBA AIUEO Unit Kebanggan'),
('unit_leader', 'Siti Rahmawati, S.Pd'),
('unit_location', 'Jakarta'),
('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta'),
('unit_phone', '(021) 555-8899'),
('unit_email', 'info@bimba-kebanggan.sch.id'),
('work_in_late', '08:15'),
('work_out_time', '16:00')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;
