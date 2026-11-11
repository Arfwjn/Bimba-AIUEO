<?php
/**
 * Template Header Kop Surat Resmi biMBA-AIUEO
 * 
 * Sesuai referensi resmi kedinasan/yayasan pendidikan:
 * - Logo biMBA AIUEO di sebelah kiri
 * - Identitas Yayasan Pengembangan Anak Indonesia & Rumah Baca biMBA-AIUEO
 * - Slogan Pusat Pengembangan MINAT Belajar Anak & Identitas Unit
 * - Alamat lengkap, nomor telepon, dan email resmi
 * - Garis pembatas ganda resmi (double border: 1 tebal di atas, 1 tipis di bawah)
 * 
 * Dilengkapi inline-styles dan embedded print CSS mandiri agar tampilan kop surat
 * selalu 100% konsisten dan rapi baik di localhost maupun di web hosting, bebas dari kendala cache CSS browser.
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Templates
 */

$unitNameVal = $unitNameVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_name', 'Unit Kebanggan') : 'Unit Kebanggan');
$unitAddressVal = $unitAddressVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Banyumas') : 'Jl. Raya Kebanggan No. 12, Banyumas');
$unitPhoneVal = $unitPhoneVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_phone', '(021) 555-8899') : '(021) 555-8899');
$unitEmailVal = $unitEmailVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_email', 'info@bimba-kebanggan.sch.id') : 'info@bimba-kebanggan.sch.id');

$yayasanVal = function_exists('get_system_setting') ? get_system_setting('institution_yayasan', 'YAYASAN PENGEMBANGAN ANAK INDONESIA') : 'YAYASAN PENGEMBANGAN ANAK INDONESIA';
$brandVal = function_exists('get_system_setting') ? get_system_setting('institution_brand', 'RUMAH BACA biMBA-AIUEO') : 'RUMAH BACA biMBA-AIUEO';
$sloganVal = function_exists('get_system_setting') ? get_system_setting('institution_slogan', 'Pusat Pengembangan MINAT Belajar Anak') : 'Pusat Pengembangan MINAT Belajar Anak';

$logoSrc = function_exists('get_bimba_logo_base64') ? get_bimba_logo_base64() : '';
if (empty($logoSrc)) {
    $logoSrc = 'assets/img/logo-bimba.png';
}
?>

<style>
/* Embedded Print Engine untuk Kop Surat Resmi biMBA AIUEO */
@media screen {
    .kop-surat-resmi {
        display: none !important;
    }
}
@media print {
    @page {
        size: A4 portrait;
        margin: 15mm 15mm 15mm 15mm !important;
    }
    .kop-surat-resmi {
        display: block !important;
        width: 100% !important;
        margin: 0 0 16px 0 !important;
        padding: 0 !important;
        page-break-inside: avoid !important;
        background: transparent !important;
    }
    .kop-surat-resmi .kop-body {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        border: none !important;
        padding-bottom: 4px !important;
    }
    .kop-surat-resmi .kop-logo-box {
        flex: 0 0 85px !important;
        width: 85px !important;
        min-width: 85px !important;
        text-align: center !important;
    }
    .kop-surat-resmi .kop-logo-img {
        max-width: 80px !important;
        max-height: 85px !important;
        width: auto !important;
        height: auto !important;
        display: block !important;
        margin: 0 auto !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .kop-surat-resmi .kop-info-box {
        flex: 1 !important;
        text-align: center !important;
        padding: 0 10px !important;
    }
    .kop-surat-resmi .kop-yayasan {
        font-family: Arial, "Times New Roman", sans-serif !important;
        font-size: 13pt !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        color: #000000 !important;
        line-height: 1.25 !important;
        margin-bottom: 2px !important;
    }
    .kop-surat-resmi .kop-lembaga {
        font-family: Arial, "Helvetica Neue", sans-serif !important;
        font-size: 17pt !important;
        font-weight: 800 !important;
        color: #1E3A8A !important;
        line-height: 1.2 !important;
        letter-spacing: 0.5px !important;
        margin-bottom: 2px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .kop-surat-resmi .kop-unit {
        font-family: Arial, sans-serif !important;
        font-size: 12pt !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        color: #000000 !important;
        line-height: 1.2 !important;
        margin-bottom: 2px !important;
    }
    .kop-surat-resmi .kop-slogan {
        font-family: Arial, sans-serif !important;
        font-size: 10pt !important;
        font-weight: 700 !important;
        color: #DC2626 !important;
        line-height: 1.2 !important;
        margin-bottom: 4px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .kop-surat-resmi .kop-kontak {
        font-family: Arial, sans-serif !important;
        font-size: 8.5pt !important;
        color: #374151 !important;
        font-style: italic !important;
        line-height: 1.3 !important;
    }
    .kop-surat-resmi .kop-logo-spacer {
        flex: 0 0 85px !important;
        width: 85px !important;
        min-width: 85px !important;
    }
    .kop-surat-resmi .kop-divider-double {
        width: 100% !important;
        margin-top: 6px !important;
        margin-bottom: 16px !important;
        border: none !important;
    }
    .kop-surat-resmi .kop-line-thick {
        border-top: 3.5px solid #000000 !important;
        width: 100% !important;
        margin-bottom: 2px !important;
    }
    .kop-surat-resmi .kop-line-thin {
        border-top: 1px solid #000000 !important;
        width: 100% !important;
    }
}
</style>

<!-- Header Kop Surat Resmi Lembaga (Tampil Otomatis pada Mode Cetak / Export PDF) -->
<div class="kop-surat kop-surat-resmi">
    <div class="kop-body" style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; width: 100%;">
        <div class="kop-logo-box" style="flex: 0 0 85px; width: 85px; min-width: 85px; text-align: center;">
            <img src="<?= $logoSrc ?>" alt="Logo Resmi biMBA AIUEO" class="kop-logo-img" style="max-width: 80px; max-height: 85px; width: auto; height: auto; display: block; margin: 0 auto;">
        </div>
        <div class="kop-info-box" style="flex: 1; text-align: center; padding: 0 10px;">
            <div class="kop-yayasan" style="font-family: Arial, 'Times New Roman', sans-serif; font-size: 13pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #000000; line-height: 1.25; margin-bottom: 2px;">
                <?= htmlspecialchars(strtoupper($yayasanVal)) ?>
            </div>
            <div class="kop-lembaga" style="font-family: Arial, 'Helvetica Neue', sans-serif; font-size: 17pt; font-weight: 800; color: #1E3A8A; line-height: 1.2; letter-spacing: 0.5px; margin-bottom: 2px;">
                <?= htmlspecialchars($brandVal) ?>
            </div>
            <div class="kop-unit" style="font-family: Arial, sans-serif; font-size: 12pt; font-weight: 700; text-transform: uppercase; color: #000000; line-height: 1.2; margin-bottom: 2px;">
                <?= htmlspecialchars(strtoupper($unitNameVal)) ?>
            </div>
            <div class="kop-slogan" style="font-family: Arial, sans-serif; font-size: 10pt; font-weight: 700; color: #DC2626; line-height: 1.2; margin-bottom: 4px;">
                <?= htmlspecialchars($sloganVal) ?>
            </div>
            <div class="kop-kontak" style="font-family: Arial, sans-serif; font-size: 8.5pt; color: #374151; font-style: italic; line-height: 1.3;">
                <?= htmlspecialchars($unitAddressVal) ?> &bull; Telp: <?= htmlspecialchars($unitPhoneVal) ?> &bull; Email: <?= htmlspecialchars($unitEmailVal) ?>
            </div>
        </div>
        <div class="kop-logo-spacer" style="flex: 0 0 85px; width: 85px; min-width: 85px;" aria-hidden="true"></div>
    </div>
    <div class="kop-divider-double" style="width: 100%; margin-top: 6px; margin-bottom: 16px;">
        <div class="kop-line-thick" style="border-top: 3.5px solid #000000; width: 100%; margin-bottom: 2px;"></div>
        <div class="kop-line-thin" style="border-top: 1px solid #000000; width: 100%;"></div>
    </div>
</div>

<script>
// Bersihkan judul halaman secara otomatis saat dialog print aktif
window.addEventListener('beforeprint', function() {
    window._docTitleBackup = document.title;
    document.title = " ";
});
window.addEventListener('afterprint', function() {
    if (window._docTitleBackup) {
        document.title = window._docTitleBackup;
    }
});
</script>
