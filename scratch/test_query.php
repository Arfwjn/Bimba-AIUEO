<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
echo "Karyawan count: " . $pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn() . "\n";
echo "Presensi count: " . $pdo->query("SELECT COUNT(*) FROM presensi")->fetchColumn() . "\n";
echo "Petty Cash count: " . $pdo->query("SELECT COUNT(*) FROM petty_cash")->fetchColumn() . "\n";
