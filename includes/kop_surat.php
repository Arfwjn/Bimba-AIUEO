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
 * @package     biMBA_AIUEO
 * @subpackage  Templates
 */

$unitNameVal = $unitNameVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_name', 'Unit Kebanggan') : 'Unit Kebanggan');
$unitAddressVal = $unitAddressVal ?? (function_exists('get_system_setting') ? get_system_setting('unit_address', 'Jl. Raya Kebanggan No. 12, Jakarta') : 'Jl. Raya Kebanggan No. 12, Jakarta');
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

<!-- Header Kop Surat Resmi Lembaga (Tampil Otomatis pada Mode Cetak / Export PDF) -->
<div class="kop-surat kop-surat-resmi">
    <div class="kop-body">
        <div class="kop-logo-box">
            <img src="<?= $logoSrc ?>" alt="Logo Resmi biMBA AIUEO" class="kop-logo-img">
        </div>
        <div class="kop-info-box">
            <div class="kop-yayasan"><?= htmlspecialchars(strtoupper($yayasanVal)) ?></div>
            <div class="kop-lembaga"><?= htmlspecialchars($brandVal) ?></div>
            <div class="kop-unit"><?= htmlspecialchars(strtoupper($unitNameVal)) ?></div>
            <div class="kop-slogan"><?= htmlspecialchars($sloganVal) ?></div>
            <div class="kop-kontak">
                <?= htmlspecialchars($unitAddressVal) ?> &bull; Telp: <?= htmlspecialchars($unitPhoneVal) ?> &bull; Email: <?= htmlspecialchars($unitEmailVal) ?>
            </div>
        </div>
        <div class="kop-logo-spacer" aria-hidden="true"></div>
    </div>
    <div class="kop-divider-double">
        <div class="kop-line-thick"></div>
        <div class="kop-line-thin"></div>
    </div>
</div>
