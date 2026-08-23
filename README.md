# Sistem Informasi Presensi Karyawan & Kas Kecil (Petty Cash) biMBA AIUEO

Sistem Informasi Manajemen Presensi Karyawan berbasis QR Code dan Pengelolaan Kas Kecil (Petty Cash) berbasis web yang dirancang khusus untuk biMBA AIUEO. Sistem ini dilengkapi dengan fitur enkripsi AES-256 untuk keamanan data presensi, validasi transaksi kas kecil, serta fitur rekapitulasi dan export laporan.

---

## 🚀 Fitur Utama

- **Dashboard Real-time**: Ringkasan jumlah karyawan, statistik presensi hari ini, dan saldo kas kecil unit.
- **Presensi Karyawan**: 
  - Scan ID Card Digital (QR Code) via kamera web/HP.
  - Input presensi manual, izin, sakit, dan upload bukti surat.
- **Portal Karyawan & Digital ID Card**:
  - Cetak ID Card Karyawan dengan QR Code terenkripsi AES-256.
  - Riwayat presensi mandiri karyawan.
- **Pengelolaan Kas Kecil (Petty Cash)**:
  - Pencatatan Pemasukan dan Pengeluaran kas unit.
  - Perhitungan saldo otomatis dan upload bukti kuitansi/nota.
- **Laporan & Export Data**:
  - Export laporan ke format Excel (.xlsx), CSV, dan Cetak PDF Resmi.
- **Pengaturan & Backup Database**:
  - Konfigurasi profil unit.
  - Fitur Download Backup Database (.sql).
  - Mode Fallback Otomatis SQLite jika service MySQL bermasalah.

---

## 🛠️ Persyaratan Sistem (Prerequisites)

Sebelum menjalankan aplikasi, pastikan komputer Anda telah memenuhi persyaratan berikut:

- **PHP**: Versi `7.4` atau `8.x`
- **Database Server**: MySQL / MariaDB (Rekomendasi via **XAMPP**)
- **Web Server**: Apache (XAMPP) atau PHP Built-in Server
- **Ekstensi PHP yang Wajib Aktif**:
  - `pdo_mysql`
  - `pdo_sqlite` (opsional untuk mode fallback)
  - `gd` (untuk pengolahan gambar / QR Code)
  - `mbstring`, `json`, `curl`

---

## 📥 Langkah-Langkah Instalasi & Running Aplikasi

Ikuti panduan langkah demi langkah berikut dari awal clone hingga aplikasi siap digunakan tanpa error:

### Langkah 1: Clone Repository

Buka Terminal / Command Prompt / Git Bash, lalu jalankan perintah berikut:

```bash
git clone https://github.com/Arfwjn/Bimba-AIUEO.git
cd Bimba-AIUEO
```

> **Catatan**: Jika menggunakan XAMPP di Windows, disarankan melakukan clone ke folder `C:\xampp\htdocs\bimba_aiueo`.

---

### Langkah 2: Konfigurasi Environment (`.env`)

1. Salin file `.env.example` menjadi `.env`:
   ```bash
   # Di Windows Command Prompt / Power Shell:
   copy .env.example .env

   # Di Git Bash / Linux / macOS:
   cp .env.example .env
   ```

2. Buka file `.env` menggunakan kode editor (VS Code / Notepad++), lalu sesuaikan konfigurasi database MySQL Anda:
   ```ini
   APP_NAME="biMBA AIUEO Unit Kebanggan"
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost/bimba_aiueo

   # Konfigurasi Database MySQL (XAMPP Default)
   DB_DRIVER=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=bimba_aiueo
   DB_USER=root
   DB_PASS=

   # Kunci Enkripsi AES-256
   AES_KEY=biMBA_AIUEO_SecretKey_2026_AES256
   AES_METHOD=AES-256-CBC
   ```

---

### Langkah 3: Import Database ke MySQL (phpMyAdmin)

1. Jalankan **XAMPP Control Panel**, lalu aktifkan service **Apache** dan **MySQL**.
2. Buka browser dan akses **phpMyAdmin** di `http://localhost/phpmyadmin`.
3. Buat database baru:
   - Nama Database: `bimba_aiueo`
   - Collation: `utf8mb4_unicode_ci` (opsional)
4. Klik pada database `bimba_aiueo` yang baru dibuat, lalu pilih tab **Import**.
5. Pilih file SQL dari folder proyek:
   - Pilih file `database/bimba_aiueo_phpmyadmin.sql` (atau `database/bimba_aiueo.sql`).
6. Klik tombol **Go / Import** di bagian bawah halaman hingga muncul pesan sukses.

---

### Langkah 4: Menjalankan Aplikasi

Anda dapat memilih salah satu cara berikut untuk menjalankan aplikasi:

#### Cara A: Menggunakan XAMPP Apache (Rekomendasi)
1. Pastikan folder proyek berada di dalam directory `htdocs` XAMPP, contoh:
   `C:\xampp\htdocs\bimba_aiueo`
2. Pastikan service **Apache** dan **MySQL** di XAMPP Control Panel dalam kondisi **Running**.
3. Buka browser Anda dan akses URL:
   ```
   http://localhost/bimba_aiueo
   ```

#### Cara B: Menggunakan PHP Built-in CLI Server
1. Buka Terminal / CMD di dalam folder proyek `Bimba-AIUEO`.
2. Jalankan perintah server lokal PHP:
   ```bash
   php -S localhost:8000
   ```
3. Buka browser Anda dan akses URL:
   ```
   http://localhost:8000
   ```

---

## 🔑 Akses & Akun Login Default

1. **Membuat Akun Admin**:
   - Jika ini pertama kali aplikasi dijalankan, buka halaman Login (`/login.php`).
   - Pilih tab atau tombol **Daftar Akun Admin Baru**.
   - Isi Nama Lengkap, Username, dan Password, lalu klik **Daftar**.
   - Setelah pendaftaran berhasil, Anda dapat langsung **Login** dengan akun tersebut.

2. **Portal Karyawan & Presensi QR Code**:
   - **ID Card Digital**: Akses menu **Data Karyawan** -> **Portal ID Card** (`/karyawan_portal.php`).
   - **Scan QR Code**: Akses menu **Presensi Karyawan** (`/presensi.php`) lalu gunakan kamera untuk melakukan scan QR Code pada ID Card.

---

## 📁 Struktur Folder Proyek

```
Bimba-AIUEO/
├── api/                   # Endpoint API backend (chart data, presensi processing, QR generator)
├── assets/                # Asset statis (CSS, JavaScript, library QR Scanner, Uploads)
│   ├── css/
│   ├── js/
│   └── uploads/           # Folder penyimpanan bukti fisik/surat & kuitansi
├── config/                # Konfigurasi aplikasi, koneksi database PDO, & fungsi keamanan
│   ├── app.php
│   ├── database.php
│   ├── env.php
│   └── security.php
├── database/              # Master skema SQL & script seeder data
│   ├── bimba_aiueo_phpmyadmin.sql
│   ├── schema.sql
│   └── seed_clean_data.php
├── includes/              # Komponen layout modular (header, footer, sidebar, auth_check)
├── export.php             # Script generator export Excel & CSV
├── index.php              # Halaman Dashboard Utama
├── karyawan.php           # Manajemen Data Master Karyawan
├── karyawan_portal.php    # Portal Mandiri & Cetak ID Card Digital Karyawan
├── laporan.php            # Rekapitulasi Laporan Presensi Karyawan
├── laporan_petty_cash.php # Rekapitulasi Laporan Kas Kecil (Petty Cash)
├── login.php              # Halaman Autentikasi Admin & Pendaftaran Akun
├── logout.php             # Halaman Session Logout
├── nginx.conf             # Konfigurasi tambahan untuk Nginx server (opsional)
├── pengaturan.php         # Halaman Pengaturan Unit & Backup Database
├── petty_cash.php         # Transaksi Pemasukan & Pengeluaran Kas Kecil
├── presensi.php           # Halaman Presensi Real-time & Scan QR Code
├── .env.example           # Template variabel lingkungan
└── .gitignore             # Aturan ignore repositori Git
```

---

## ❓ Troubleshooting (Penanganan Masalah Umum)

| Masalah / Error | Penyebab | Solusi |
| :--- | :--- | :--- |
| **Pesan `Access denied for user 'root'@'localhost'`** | Username/Password database di `.env` tidak sesuai dengan MySQL Anda. | Buka file `.env` dan pastikan `DB_USER` dan `DB_PASS` sesuai dengan settingan XAMPP/MySQL Anda. |
| **Halaman blank putih atau Error 500** | Ekstensi PHP belum aktif atau file `.env` belum dibuat. | Pastikan file `.env` sudah disalin dari `.env.example` dan ekstensi `pdo_mysql` sudah diaktifkan di `php.ini`. |
| **Kamera QR Code tidak aktif di Browser** | Izin akses kamera diblokir browser atau tidak menggunakan HTTPS/localhost. | Pastikan mengizinkan (*Allow*) akses kamera di browser saat berada di halaman `presensi.php`. |
| **Data uploads gambar kuitansi/surat tidak muncul** | Folder `assets/uploads/` tidak memiliki izin tulis (*write permission*). | Di Linux/macOS, berikan izin folder: `chmod -R 777 assets/uploads`. |

---

## 📝 Lisensi & Hak Cipta

Proyek ini dikembangkan untuk kebutuhan Skripsi / Tugas Akhir Sistem Informasi biMBA AIUEO.
