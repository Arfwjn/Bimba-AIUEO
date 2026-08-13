<?php
// scratch/test_toggle_status.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Ensure status_aktif exists
try {
    $pdo->exec("ALTER TABLE karyawan ADD status_aktif INT DEFAULT 1");
    echo "Column status_aktif added to karyawan table.\n";
} catch (Exception $e) {
    echo "Column status_aktif already exists.\n";
}

// Fetch employee ID 1
$emp = $pdo->query("SELECT id_karyawan, nama, status_aktif FROM karyawan LIMIT 1")->fetch();
if (!$emp) {
    echo "No employees found.\n";
    exit;
}

$id = $emp['id_karyawan'];
$oldStatus = intval($emp['status_aktif'] ?? 1);
echo "Employee ID {$id} ('{$emp['nama']}') Current Status: {$oldStatus}\n";

// Toggle status
$stmt = $pdo->prepare("UPDATE karyawan SET status_aktif = CASE WHEN COALESCE(status_aktif, 1) = 1 THEN 0 ELSE 1 END WHERE id_karyawan = ?");
$stmt->execute([$id]);

$newEmp = $pdo->query("SELECT id_karyawan, nama, status_aktif FROM karyawan WHERE id_karyawan = {$id}")->fetch();
$newStatus = intval($newEmp['status_aktif']);
echo "Employee ID {$id} New Status: {$newStatus}\n";

if ($oldStatus !== $newStatus) {
    echo "=> SUCCESS: STATUS SUCCESSFULLY TOGGLED IN DATABASE FROM {$oldStatus} TO {$newStatus}!\n";
} else {
    echo "=> ERROR: STATUS DID NOT CHANGE!\n";
}
