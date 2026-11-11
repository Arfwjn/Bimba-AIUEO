-- ==============================================================================
-- SQL Schema & Initial Seed biMBA AIUEO untuk Hosting InfinityFree (phpMyAdmin)
-- Import langsung ke dalam database: if0_42771702_bimba_aiueo
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Struktur Tabel `admin`
CREATE TABLE IF NOT EXISTS `admin` (
    `id_admin` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Struktur Tabel `qr_code`
CREATE TABLE IF NOT EXISTS `qr_code` (
    `id_qr` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kode_qr` VARCHAR(255) NOT NULL UNIQUE,
    `encrypted_data` TEXT NOT NULL,
    `expired` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Struktur Tabel `karyawan`
CREATE TABLE IF NOT EXISTS `karyawan` (
    `id_karyawan` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `jabatan` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) UNIQUE NULL,
    `password` VARCHAR(255) NULL,
    `id_qr` INT UNSIGNED NULL,
    `status_aktif` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_karyawan_qr`
        FOREIGN KEY (`id_qr`)
        REFERENCES `qr_code` (`id_qr`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Struktur Tabel `presensi`
CREATE TABLE IF NOT EXISTS `presensi` (
    `id_presensi` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_karyawan` INT UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `jam_masuk` TIME NULL,
    `jam_keluar` TIME NULL,
    `status` VARCHAR(30) NOT NULL,
    `bukti_surat` VARCHAR(255) NULL,
    `keterangan` TEXT NULL,
    `status_validasi` VARCHAR(20) DEFAULT 'Valid',
    `raw_payload` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_presensi_karyawan`
        FOREIGN KEY (`id_karyawan`)
        REFERENCES `karyawan` (`id_karyawan`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Struktur Tabel `petty_cash`
CREATE TABLE IF NOT EXISTS `petty_cash` (
    `id_transaksi` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_admin` INT UNSIGNED NOT NULL DEFAULT 1,
    `tanggal` DATE NOT NULL,
    `jenis` VARCHAR(20) NOT NULL,
    `kategori` VARCHAR(50) NOT NULL DEFAULT 'Operasional Unit',
    `nominal` DECIMAL(15,2) NOT NULL,
    `saldo_setelah` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `keterangan` TEXT NULL,
    `bukti_file` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_petty_cash_admin`
        FOREIGN KEY (`id_admin`)
        REFERENCES `admin` (`id_admin`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Struktur Tabel `settings`
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Data Awal
-- ------------------------------------------------------------------------------

-- Default Admin (Username: admin | Password: admin123)
INSERT INTO `admin` (`id_admin`, `nama`, `username`, `password`) VALUES
(1, 'Administrator Unit', 'admin', '$2y$12$cQ3ibnCnO9Hf9m4bUrrtpuDfF8KCQQG/NalBnTFfsddFsMPqch/b.')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- Sample Karyawan
INSERT INTO `karyawan` (`id_karyawan`, `nama`, `jabatan`, `username`, `status_aktif`) VALUES
(1, 'Siti Rahmawati, S.Pd', 'Kepala Unit', 'siti', 1),
(2, 'Dewi Sartika', 'Motivator Utama', 'dewi', 1),
(3, 'Budi Santoso', 'Motivator Junior', 'budi', 1),
(4, 'Anisa Putri', 'Staf Administrasi', 'anisa', 1)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- Default Settings Lembaga
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('unit_name', 'biMBA AIUEO Unit Kebanggan'),
('unit_leader', 'Syawalla Khoerun Nisa'),
('unit_location', 'Purwokerto'),
('unit_address', 'Jl. Raya Kebanggan No. 12, Purwokerto'),
('unit_phone', '(021) 555-8899'),
('unit_email', 'info@bimba-kebanggan.sch.id'),
('institution_yayasan', 'YAYASAN PENGEMBANGAN ANAK INDONESIA'),
('institution_brand', 'RUMAH BACA biMBA-AIUEO'),
('institution_slogan', 'Pusat Pengembangan MINAT Belajar Anak'),
('work_in_late', '08:15'),
('work_out_time', '16:00')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Sample Saldo Awal Kas Kecil (Petty Cash)
INSERT INTO `petty_cash` (`id_transaksi`, `id_admin`, `tanggal`, `jenis`, `kategori`, `nominal`, `saldo_setelah`, `keterangan`) VALUES
(1, 1, CURRENT_DATE(), 'Pemasukan', 'Saldo Kas Awal', 1500000.00, 1500000.00, 'Penerimaan Kas Awal Bulan')
ON DUPLICATE KEY UPDATE `nominal` = VALUES(`nominal`);

SET FOREIGN_KEY_CHECKS = 1;
