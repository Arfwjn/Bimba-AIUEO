# DEPLOYMENT.md
# Panduan Deployment Produksi Sistem Presensi & Petty Cash biMBA AIUEO

Dokumen ini berisi langkah-langkah komprehensif untuk menyebarkan (deploy) aplikasi **biMBA AIUEO Unit Kebanggan** ke lingkungan server **Produksi (Production)**.

---

## 📋 Checklist Kesiapan Produksi (Production Readiness)

- [x] File `.env` dikonfigurasi dengan `APP_ENV=production` dan `APP_DEBUG=false`.
- [x] Kunci rahasia `AES_KEY` unik 32-karakter dikonfigurasi di server.
- [x] Proteksi CSRF Token aktif pada semua form POST.
- [x] Proteksi Keamanan File Upload (`assets/uploads/.htaccess`) aktif untuk mencegah eksekusi skrip berbahaya (webshell).
- [x] Header Keamanan HTTP (`X-Frame-Options`, `X-XSS-Protection`, `X-Content-Type-Options`) aktif.
- [x] Database MySQL/MariaDB ter-import via `database/bimba_aiueo_phpmyadmin.sql`.

---

## 🌐 OPSI 1: Deploy di Shared Hosting / cPanel (XAMPP / Web Hosting)

1. **Upload File Proyek**:
   - Kompres seluruh isi folder `BIMBA_AIUEO` menjadi file `bimba_aiueo.zip`.
   - Upload dan Ekstrak di direktori `public_html` cPanel hosting Anda.

2. **Buat Database MySQL di cPanel**:
   - Buka **MySQL Database Wizard** di cPanel.
   - Buat database baru (contoh: `u123456_bimba`).
   - Buat user database & password baru (contoh: `u123456_admin` / `PassW0rdAman!`).
   - Berikan hak akses **ALL PRIVILEGES** user ke database tersebut.

3. **Import Skema Database**:
   - Buka **phpMyAdmin** di cPanel.
   - Pilih database `u123456_bimba`.
   - Klik tab **Import** -> Pilih file [`database/bimba_aiueo_phpmyadmin.sql`](file:///C:/Users/ACER/Downloads/Skripsi%20Bimbel/BIMBA_AIUEO/database/bimba_aiueo_phpmyadmin.sql) -> Klik **Go**.

4. **Konfigurasi `.env` Produksi**:
   - Buka File Manager di cPanel -> Edit file `.env` di root proyek:
     ```env
     APP_NAME="biMBA AIUEO Unit Kebanggan"
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://unit-kebanggan.bimba-aiueo.sch.id

     DB_DRIVER=mysql
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=u123456_bimba
     DB_USER=u123456_admin
     DB_PASS=PassW0rdAman!

     AES_KEY=KunciRahasiaRekomendasi32Karakter!
     AES_METHOD=AES-256-CBC
     ```

5. **Pengujian SSL / HTTPS**:
   - Pastikan SSL Let's Encrypt / AutoSSL aktif di cPanel agar pemindaian kamera QR Code dapat mengakses `navigator.mediaDevices.getUserMedia()` di browser smartphone.

---

## 🐧 OPSI 2: Deploy di Linux VPS (Ubuntu 22.04 / Nginx + PHP 8.4-FPM)

1. **Install Dependencies Server**:
   ```bash
   sudo apt update && sudo apt install -y nginx mariadb-server php8.4-fpm php8.4-mysql php8.4-gd php8.4-mbstring php8.4-xml php8.4-curl
   ```

2. **Clone / Upload Kode Proyek**:
   ```bash
   sudo mkdir -p /var/www/BIMBA_AIUEO
   sudo chown -R www-data:www-data /var/www/BIMBA_AIUEO
   ```

3. **Set Up Nginx Virtual Host**:
   - Salin file [`nginx.conf`](file:///C:/Users/ACER/Downloads/Skripsi%20Bimbel/BIMBA_AIUEO/nginx.conf) ke `/etc/nginx/sites-available/bimba`:
     ```bash
     sudo cp /var/www/BIMBA_AIUEO/nginx.conf /etc/nginx/sites-available/bimba
     sudo ln -s /etc/nginx/sites-available/bimba /etc/nginx/sites-enabled/
     sudo nginx -t && sudo systemctl reload nginx
     ```

4. **Install SSL HTTPS dengan Certbot**:
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d unit-kebanggan.bimba-aiueo.sch.id
   ```

---

## 🔒 Pemeliharaan & Pertimbangan Keamanan Tambahan

1. **Backup Database Rutin**:
   - Gunakan Cron Job untuk mem-backup database secara berkala:
     ```bash
     0 2 * * * mysqldump -u u123456_admin -p'PassW0rdAman!' u123456_bimba > /var/backups/bimba_$(date +\%F).sql
     ```
2. **Rotasi Kunci Enkripsi AES**:
   - Jika kunci AES diubah pada `.env`, semua QR Code yang dicetak sebelum rotasi kunci harus dibuatkan QR Code baru agar dapat di-dekripsi oleh validator server.
