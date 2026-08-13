<?php
// api/chart_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$labels = [];
$attendanceData = [];
$expenseData = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayLabel = date('d M', strtotime($date));
    $labels[] = $displayLabel;

    // Attendance Count
    $stmtAtt = $pdo->prepare("SELECT COUNT(*) as cnt FROM presensi WHERE tanggal = ? AND status_validasi = 'Valid'");
    $stmtAtt->execute([$date]);
    $resAtt = $stmtAtt->fetch();
    $attendanceData[] = intval($resAtt['cnt']);

    // Petty Cash Expense Sum
    $stmtExp = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) as total FROM petty_cash WHERE tanggal = ? AND jenis = 'Pengeluaran'");
    $stmtExp->execute([$date]);
    $resExp = $stmtExp->fetch();
    $expenseData[] = floatval($resExp['total']);
}

echo json_encode([
    'labels' => $labels,
    'attendance' => $attendanceData,
    'expenses' => $expenseData
]);
