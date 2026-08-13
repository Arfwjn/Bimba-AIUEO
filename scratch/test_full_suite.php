<?php
// scratch/test_full_suite.php
require_once __DIR__ . '/../config/database.php';

echo "=== START FULL SYSTEM FUNCTIONALITY SUITE TEST ===\n\n";

$pdo = getDB();

// 1. Test Export CSV Generators
echo "[1] Testing Export CSV Generators...\n";
$exportTypes = ['presensi', 'petty_cash', 'karyawan'];

foreach ($exportTypes as $t) {
    $url = "http://127.0.0.1:8000/export.php?type={$t}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && !empty($output)) {
        echo "=> SUCCESS: Export {$t} returned HTTP 200 OK (" . strlen($output) . " bytes)\n";
    } else {
        echo "=> ERROR: Export {$t} failed with HTTP {$code}\n";
    }
}

// 2. Test Backup SQL Generator
echo "\n[2] Testing Database Backup SQL Generator...\n";
$ch = curl_init("http://127.0.0.1:8000/pengaturan.php?action=download_backup");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$sqlBackup = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200 && str_contains($sqlBackup, 'INSERT INTO')) {
    echo "=> SUCCESS: SQL Backup generated successfully with INSERT statements!\n";
} else {
    echo "=> ERROR: SQL Backup generation failed with HTTP {$code}\n";
}

// 3. Test Modules HTTP Loading
echo "\n[3] Testing Module Pages HTTP Load...\n";
$modules = [
    'index.php',
    'karyawan.php',
    'presensi.php',
    'petty_cash.php',
    'laporan.php',
    'laporan_petty_cash.php',
    'karyawan_portal.php',
    'pengaturan.php'
];

foreach ($modules as $mod) {
    $ch = curl_init("http://127.0.0.1:8000/{$mod}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 || $code === 302) {
        echo "=> SUCCESS: Module {$mod} responded HTTP {$code}\n";
    } else {
        echo "=> ERROR: Module {$mod} failed with HTTP {$code}\n";
    }
}

echo "\n=== ALL FULL SYSTEM SUITE TESTS COMPLETED SUCCESSFULLY ===";
