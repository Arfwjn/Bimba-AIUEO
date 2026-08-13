<?php
$_GET['type'] = 'presensi';
$_SESSION['user_id'] = 1;

ob_start();
require __DIR__ . '/../export.php';
$csvOutput = ob_get_clean();

echo "Generated CSV Length: " . strlen($csvOutput) . " bytes\n";
echo "CSV First 200 chars:\n" . substr($csvOutput, 0, 200) . "\n";
