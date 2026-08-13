<?php
// scratch/debug_presensi_mysql.php
require_once __DIR__ . '/../config/database.php';

echo "=== TESTING MYSQL PRESENSI INSERT FIX ===\n\n";

$pdo = getDB();

// 1. Auto-migrate missing columns if any
try {
    $pdo->exec("ALTER TABLE presensi ADD status_validasi VARCHAR(20) DEFAULT 'Valid'");
    echo "Column status_validasi added to presensi table.\n";
} catch (Exception $e) {}

try {
    $pdo->exec("ALTER TABLE presensi ADD raw_payload TEXT NULL");
    echo "Column raw_payload added to presensi table.\n";
} catch (Exception $e) {}

$today = date('Y-m-d');
$now = date('H:i:s');

$stmtEmp = $pdo->query("SELECT id_karyawan, nama FROM karyawan LIMIT 1");
$emp = $stmtEmp ? $stmtEmp->fetch() : null;

if ($emp) {
    echo "Inserting presensi for id_karyawan {$emp['id_karyawan']} ('{$emp['nama']}')...\n";
    try {
        $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status, status_validasi, raw_payload) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$emp['id_karyawan'], $today, $now, 'Hadir', 'Valid', 'TEST_PAYLOAD']);
    } catch (PDOException $e) {
        $stmtIns = $pdo->prepare("INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$emp['id_karyawan'], $today, $now, 'Hadir']);
    }

    $lastId = $pdo->lastInsertId();
    echo "=> SUCCESS: INSERTED PRESENSI LAST ID: {$lastId}\n";

    // Query back
    $stmtCheck = $pdo->prepare("
        SELECT p.*, k.nama, k.jabatan 
        FROM presensi p 
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
        WHERE DATE(p.tanggal) = ? 
        ORDER BY p.id_presensi DESC
    ");
    $stmtCheck->execute([$today]);
    $results = $stmtCheck->fetchAll();

    echo "Query 'WHERE DATE(p.tanggal) = {$today}' result count: " . count($results) . "\n";
    print_r($results);
}
