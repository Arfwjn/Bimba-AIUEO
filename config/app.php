<?php
// config/app.php
require_once __DIR__ . '/env.php';

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', env('APP_NAME', 'biMBA AIUEO Unit Kebanggan'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_VERSION', '1.0.0');

// AES Encryption Settings
define('AES_KEY', env('AES_KEY', 'biMBA_AIUEO_SecretKey_2026_AES256'));
define('AES_METHOD', env('AES_METHOD', 'AES-256-CBC'));

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Reusable Pagination Controls Helper (Max 5 Items Per Page)
 * 
 * @param int $page Current active page number
 * @param int $totalPages Total number of pages
 * @param array $queryParams Existing GET query parameters to preserve
 * @return string HTML pagination markup
 */
function render_pagination($page, $totalPages, $queryParams = []) {
    if ($totalPages <= 1) {
        return '';
    }

    $page = max(1, min($page, $totalPages));
    
    $buildUrl = function($pageNum) use ($queryParams) {
        $params = $queryParams;
        $params['page'] = $pageNum;
        return '?' . http_build_query($params);
    };

    // Calculate dynamic 3-page window around current page
    $startPage = max(1, $page - 1);
    $endPage = min($totalPages, $page + 1);

    if ($page === 1) {
        $endPage = min($totalPages, 3);
    }
    if ($page === $totalPages) {
        $startPage = max(1, $totalPages - 2);
    }

    $html = '<div class="pagination-container no-print" style="display: flex; align-items: center; justify-content: space-between; margin-top: 16px; flex-wrap: wrap; gap: 10px;">';
    $html .= '<div style="font-size: 12px; color: var(--text-muted); font-family: var(--font-sans);">';
    $html .= 'Halaman <strong>' . $page . '</strong> dari <strong>' . $totalPages . '</strong>';
    $html .= '</div>';
    
    $html .= '<div style="display: flex; gap: 4px; align-items: center;">';
    
    // 1. First Page Jump Button
    if ($page > 1) {
        $html .= '<a href="' . $buildUrl(1) . '" class="btn btn-secondary btn-sm" title="Halaman Pertama" style="padding: 4px 10px; font-size: 12px;">&laquo;&laquo; First</a>';
    } else {
        $html .= '<button class="btn btn-secondary btn-sm" disabled style="padding: 4px 10px; font-size: 12px; opacity: 0.35; cursor: not-allowed;">&laquo;&laquo; First</button>';
    }

    // 2. Prev Button
    if ($page > 1) {
        $html .= '<a href="' . $buildUrl($page - 1) . '" class="btn btn-secondary btn-sm" title="Halaman Sebelumnya" style="padding: 4px 10px; font-size: 12px;">&laquo; Prev</a>';
    } else {
        $html .= '<button class="btn btn-secondary btn-sm" disabled style="padding: 4px 10px; font-size: 12px; opacity: 0.35; cursor: not-allowed;">&laquo; Prev</button>';
    }

    // 3. Compact 3-Page Window Numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $page) {
            $html .= '<span class="btn btn-primary btn-sm" style="padding: 4px 12px; font-size: 12px; font-weight: 700;">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $buildUrl($i) . '" class="btn btn-secondary btn-sm" style="padding: 4px 12px; font-size: 12px;">' . $i . '</a>';
        }
    }

    // 4. Next Button
    if ($page < $totalPages) {
        $html .= '<a href="' . $buildUrl($page + 1) . '" class="btn btn-secondary btn-sm" title="Halaman Selanjutnya" style="padding: 4px 10px; font-size: 12px;">Next &raquo;</a>';
    } else {
        $html .= '<button class="btn btn-secondary btn-sm" disabled style="padding: 4px 10px; font-size: 12px; opacity: 0.35; cursor: not-allowed;">Next &raquo;</button>';
    }

    // 5. Last Page Jump Button
    if ($page < $totalPages) {
        $html .= '<a href="' . $buildUrl($totalPages) . '" class="btn btn-secondary btn-sm" title="Halaman Terakhir" style="padding: 4px 10px; font-size: 12px;">Last &raquo;&raquo;</a>';
    } else {
        $html .= '<button class="btn btn-secondary btn-sm" disabled style="padding: 4px 10px; font-size: 12px; opacity: 0.35; cursor: not-allowed;">Last &raquo;&raquo;</button>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Generates detailed Status Badges & Text for Presensi Masuk and Presensi Keluar
 * Based on business rules:
 * 1. Masuk & Keluar Tepat Waktu -> Masuk: Hadir (Green), Keluar: Hadir (Tepat Waktu) (Green)
 * 2. Masuk Terlambat & Keluar Tepat Waktu -> Masuk: Hadir (Terlambat) (Orange), Keluar: Hadir (Tepat Waktu) (Green)
 * 3. Masuk Tepat Waktu & Keluar Pulang Awal -> Masuk: Hadir (Green), Keluar: Pulang Awal (Orange)
 * 4. Tidak Presensi -> Masuk: Tidak Hadir (Red), Keluar: Tidak Hadir (Red)
 * 5. Izin / Sakit -> Masuk & Keluar: Izin/Sakit (Warning)
 */
function get_attendance_detail_badges($row) {
    $status = $row['status'] ?? 'Hadir';
    $jamMasuk = $row['jam_masuk'] ?? null;
    $jamKeluar = $row['jam_keluar'] ?? null;

    $workOutSetting = '16:00:00';
    if (function_exists('get_system_setting')) {
        $settingVal = get_system_setting('work_out_time', '16:00');
        if (strlen($settingVal) === 5) $settingVal .= ':00';
        $workOutSetting = $settingVal;
    }

    if (in_array($status, ['Izin', 'Sakit'])) {
        return [
            'masuk' => ['text' => $status, 'class' => 'warning'],
            'keluar' => ['text' => $status, 'class' => 'warning']
        ];
    }

    if ($status === 'Tidak Hadir' || (empty($jamMasuk) && empty($jamKeluar))) {
        return [
            'masuk' => ['text' => 'Tidak Hadir', 'class' => 'danger'],
            'keluar' => ['text' => 'Tidak Hadir', 'class' => 'danger']
        ];
    }

    // Check-In Badge
    if ($status === 'Terlambat') {
        $masukBadge = ['text' => 'Hadir (Terlambat)', 'class' => 'warning'];
    } else {
        $masukBadge = ['text' => 'Hadir', 'class' => 'success'];
    }

    // Check-Out Badge
    if (!empty($jamKeluar) && $jamKeluar !== '00:00:00') {
        if (strtotime($jamKeluar) < strtotime($workOutSetting)) {
            $keluarBadge = ['text' => 'Pulang Awal', 'class' => 'warning'];
        } else {
            $keluarBadge = ['text' => 'Hadir (Tepat Waktu)', 'class' => 'success'];
        }
    } else {
        $keluarBadge = ['text' => 'Belum Keluar', 'class' => 'info'];
    }

    return ['masuk' => $masukBadge, 'keluar' => $keluarBadge];
}
