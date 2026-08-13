-- ==============================================================================
-- Database Schema MySQL untuk Sistem Presensi Karyawan dan Petty Cash biMBA AIUEO
-- Disesuaikan dengan DATABASE_SCHEMA.md (Logika Bisnis & Skripsi ERD)
-- Siap di-import langsung di phpMyAdmin / MySQL
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `bimba_aiueo` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `bimba_aiueo`;

DROP TABLE IF EXISTS `petty_cash`;
DROP TABLE IF EXISTS `presensi`;
DROP TABLE IF EXISTS `karyawan`;
DROP TABLE IF EXISTS `qr_code`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `settings`;

-- --------------------------------------------------------
-- 1. Table structure for table `admin`
-- --------------------------------------------------------
CREATE TABLE `admin` (
    `id_admin` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table structure for table `qr_code`
-- --------------------------------------------------------
CREATE TABLE `qr_code` (
    `id_qr` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kode_qr` VARCHAR(255) NOT NULL UNIQUE,
    `encrypted_data` TEXT NOT NULL,
    `expired` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `karyawan`
-- --------------------------------------------------------
CREATE TABLE `karyawan` (
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

-- --------------------------------------------------------
-- 4. Table structure for table `presensi`
-- --------------------------------------------------------
CREATE TABLE `presensi` (
    `id_presensi` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_karyawan` INT UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `jam_masuk` TIME NULL,
    `jam_keluar` TIME NULL,
    `status` VARCHAR(30) NOT NULL,
    `status_validasi` VARCHAR(20) DEFAULT 'Valid',
    `raw_payload` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_presensi_karyawan`
        FOREIGN KEY (`id_karyawan`)
        REFERENCES `karyawan` (`id_karyawan`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `petty_cash`
-- --------------------------------------------------------
CREATE TABLE `petty_cash` (
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
        ON DELETE RESTRICT,
    CONSTRAINT `chk_petty_cash_nominal`
        CHECK (`nominal` > 0),
    CONSTRAINT `chk_petty_cash_jenis`
        CHECK (`jenis` IN ('Pemasukan', 'Pengeluaran'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `settings`
-- --------------------------------------------------------
CREATE TABLE `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommended Indexes
CREATE INDEX `idx_presensi_karyawan` ON `presensi` (`id_karyawan`);
CREATE INDEX `idx_presensi_tanggal` ON `presensi` (`tanggal`);
CREATE INDEX `idx_presensi_karyawan_tanggal` ON `presensi` (`id_karyawan`, `tanggal`);
CREATE INDEX `idx_petty_cash_tanggal` ON `petty_cash` (`tanggal`);
CREATE INDEX `idx_petty_cash_jenis` ON `petty_cash` (`jenis`);
CREATE INDEX `idx_karyawan_nama` ON `karyawan` (`nama`);

-- Seed Data untuk Prototype & Pengujian
INSERT INTO `admin` (`id_admin`, `nama`, `username`, `password`) VALUES
(1, 'Administrator Unit', 'admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe12.7Nq9wK7.0i2E5.jF4e1f7y3A5aSm');

INSERT INTO `qr_code` (`id_qr`, `kode_qr`, `encrypted_data`, `expired`) VALUES
(1, 'EMP001', 'U2FsdGVkX1+v8l3zN8vX1z...', '2026-12-31 23:59:59'),
(2, 'EMP002', 'U2FsdGVkX1+k7j2m1n0o2p...', '2026-12-31 23:59:59');

INSERT INTO `karyawan` (`id_karyawan`, `nama`, `jabatan`, `username`, `password`, `id_qr`, `status_aktif`) VALUES
(1, 'Siti Rahmawati, S.Pd', 'Kepala Unit', 'siti', NULL, 1, 1),
(2, 'Dewi Sartika', 'Motivator Utama', 'dewi', NULL, 2, 1),
(3, 'Budi Santoso', 'Motivator Junior', 'budi', NULL, NULL, 1),
(4, 'Anisa Putri', 'Staf Administrasi', 'anisa', NULL, NULL, 1);

INSERT INTO `petty_cash` (`id_transaksi`, `id_admin`, `tanggal`, `jenis`, `kategori`, `nominal`, `saldo_setelah`, `keterangan`) VALUES
(1, 1, CURRENT_DATE(), 'Pemasukan', 'Saldo Awal', 1500000.00, 1500000.00, 'Saldo kas kecil awal unit'),
(2, 1, CURRENT_DATE(), 'Pengeluaran', 'ATK & Cetak', 75000.00, 1425000.00, 'Pembelian kertas HVS A4 2 rim');
