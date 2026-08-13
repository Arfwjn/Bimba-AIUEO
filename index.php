<?php
// index.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard Admin';
$pageBreadcrumb = 'Dashboard > Ringkasan Operasional';

$pdo = getDB();
$today = date('Y-m-d');

// Helper to safely execute query and fetch single row
function safeQueryRow($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return $res ? $res : [];
    } catch (PDOException $e) {
        return [];
    }
}

// 1. Total Karyawan
$resEmp = safeQueryRow($pdo, "SELECT COUNT(*) as total FROM karyawan WHERE status_aktif = 1");
$totalKaryawan = intval($resEmp['total'] ?? 0);

// 2. Presensi Hari Ini
$resPres = safeQueryRow($pdo, "SELECT COUNT(*) as total FROM presensi WHERE tanggal = ? AND status_validasi = 'Valid'", [$today]);
$presensiHariIni = intval($resPres['total'] ?? 0);

// 3. Petty Cash Stats
$resPem = safeQueryRow($pdo, "SELECT COALESCE(SUM(nominal), 0) as total FROM petty_cash WHERE jenis = 'Pemasukan'");
$totalPemasukan = floatval($resPem['total'] ?? 0);

$resPeng = safeQueryRow($pdo, "SELECT COALESCE(SUM(nominal), 0) as total FROM petty_cash WHERE jenis = 'Pengeluaran'");
$totalPengeluaran = floatval($resPeng['total'] ?? 0);

$saldoAkhir = $totalPemasukan - $totalPengeluaran;

// 4. Latest Presensi (5 items)
$recentPresensi = [];
try {
    $stmtRecentPres = $pdo->prepare("
        SELECT p.*, k.nama, k.jabatan 
        FROM presensi p 
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
        ORDER BY p.id_presensi DESC LIMIT 5
    ");
    $stmtRecentPres->execute();
    $recentPresensi = $stmtRecentPres->fetchAll();
} catch (PDOException $e) {
    $recentPresensi = [];
}

// 5. Latest Petty Cash (5 items)
$recentPettyCash = [];
try {
    $stmtRecentPc = $pdo->query("SELECT * FROM petty_cash ORDER BY id_transaksi DESC LIMIT 5");
    if ($stmtRecentPc) {
        $recentPettyCash = $stmtRecentPc->fetchAll();
    }
} catch (PDOException $e) {
    $recentPettyCash = [];
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px;">
    <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">Ringkasan Sistem Hari Ini</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Pemantauan presensi karyawan berbasis QR Code AES dan arus kas kecil unit.</p>
</div>

<!-- 4 Statistic Cards Grid -->
<div class="card-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Karyawan</span>
            <div class="stat-icon">
                <span class="material-symbols-outlined">groups</span>
            </div>
        </div>
        <div class="stat-value"><?= number_format($totalKaryawan) ?></div>
        <div class="stat-sub">Karyawan aktif terdaftar</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Presensi Hari Ini</span>
            <div class="stat-icon">
                <span class="material-symbols-outlined">how_to_reg</span>
            </div>
        </div>
        <div class="stat-value"><?= number_format($presensiHariIni) ?> / <?= number_format($totalKaryawan) ?></div>
        <div class="stat-sub">Karyawan hadir hari ini</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Pemasukan Kas</span>
            <div class="stat-icon">
                <span class="material-symbols-outlined">trending_up</span>
            </div>
        </div>
        <div class="stat-value">Rp <?= number_format($totalPemasukan, 0, ',', '.') ?></div>
        <div class="stat-sub">Akumulasi kas masuk</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Saldo Petty Cash</span>
            <div class="stat-icon">
                <span class="material-symbols-outlined">account_balance_wallet</span>
            </div>
        </div>
        <div class="stat-value" style="color: <?= $saldoAkhir >= 0 ? 'var(--text-primary)' : 'var(--status-danger-text)' ?>;">
            Rp <?= number_format($saldoAkhir, 0, ',', '.') ?>
        </div>
        <div class="stat-sub">Saldo berjalan kas kecil</div>
    </div>
</div>

<!-- Charts Section -->
<div class="form-row" style="margin-bottom: 28px;">
    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <h3 class="panel-title">Grafik Presensi 7 Hari Terakhir</h3>
            <span class="badge badge-info">Valid QR</span>
        </div>
        <canvas id="chartPresensi" style="width: 100%; height: 220px;"></canvas>
    </div>

    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <h3 class="panel-title">Grafik Pengeluaran Petty Cash (7 Hari)</h3>
            <span class="badge badge-warning">Kas Keluar</span>
        </div>
        <canvas id="chartPengeluaran" style="width: 100%; height: 220px;"></canvas>
    </div>
</div>

<!-- Bottom Recent Tables Grid -->
<div class="form-row">
    <!-- Presensi Terbaru -->
    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <h3 class="panel-title">Presensi Terbaru</h3>
            <a href="presensi.php" class="btn btn-secondary btn-sm">Scan QR Code</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Karyawan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPresensi)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Belum ada data presensi hari ini</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPresensi as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($p['tanggal'] ?? 'now')) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($p['jam_masuk'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nama'] ?? '-') ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($p['jabatan'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= ($p['status'] ?? '') === 'Hadir' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($p['status'] ?? 'Hadir') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Petty Cash Terbaru -->
    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <h3 class="panel-title">Transaksi Kas Kecil Terbaru</h3>
            <a href="petty_cash.php" class="btn btn-secondary btn-sm">Input Transaksi</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori & Jenis</th>
                        <th>Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPettyCash)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Belum ada transaksi petty cash</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPettyCash as $pc): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($pc['tanggal'] ?? 'now')) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($pc['kategori'] ?? $pc['keterangan'] ?? 'Transaksi Kas') ?></strong><br>
                                    <span class="badge badge-<?= ($pc['jenis'] ?? '') === 'Pemasukan' ? 'success' : 'danger' ?>" style="font-size: 10px;">
                                        <?= htmlspecialchars($pc['jenis'] ?? 'Pengeluaran') ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?= ($pc['jenis'] ?? '') === 'Pemasukan' ? 'var(--status-success-text)' : 'var(--status-danger-text)' ?>;">
                                        <?= ($pc['jenis'] ?? '') === 'Pemasukan' ? '+' : '-' ?> Rp <?= number_format($pc['nominal'] ?? 0, 0, ',', '.') ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('api/chart_data.php')
        .then(res => res.json())
        .then(data => {
            drawBarChart('chartPresensi', data.labels, data.attendance, 'Kehadiran Karyawan', '#111111');
            drawBarChart('chartPengeluaran', data.labels, data.expenses, 'Pengeluaran (Rp)', '#991B1B');
        })
        .catch(err => console.error('Failed to load chart data:', err));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
