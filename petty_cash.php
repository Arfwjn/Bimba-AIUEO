<?php
// petty_cash.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Petty Cash Management';
$pageBreadcrumb = 'Dashboard > Petty Cash > Daftar Transaksi';

$pdo = getDB();
$message = '';
$error = '';

// Calculate current total balance
$stmtPemTotal = $pdo->query("SELECT COALESCE(SUM(nominal), 0) as total FROM petty_cash WHERE jenis = 'Pemasukan'");
$totalPemasukan = $stmtPemTotal ? $stmtPemTotal->fetch()['total'] : 0;

$stmtPengTotal = $pdo->query("SELECT COALESCE(SUM(nominal), 0) as total FROM petty_cash WHERE jenis = 'Pengeluaran'");
$totalPengeluaran = $stmtPengTotal ? $stmtPengTotal->fetch()['total'] : 0;

$currentBalance = $totalPemasukan - $totalPengeluaran;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan CSRF Token gagal.';
    } else {
        $action = $_POST['action'] ?? 'add';

        if ($action === 'add') {
            $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
            $jenis = trim($_POST['jenis'] ?? 'Pengeluaran');
            $kategori = trim($_POST['kategori'] ?? 'Operasional');
            $nominalRaw = trim($_POST['nominal'] ?? '0');
            $nominal = floatval(str_replace('.', '', str_replace(',', '', $nominalRaw)));
            $keterangan = trim($_POST['keterangan'] ?? '');

            // Receipt Upload Handling
            $buktiFile = null;
            if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
                $valRes = validate_uploaded_file($_FILES['bukti']);
                if (!$valRes['valid']) {
                    $error = $valRes['message'];
                } else {
                    $uploadDir = __DIR__ . '/assets/uploads/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newFileName = 'receipt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $valRes['ext'];
                    move_uploaded_file($_FILES['bukti']['tmp_name'], $uploadDir . $newFileName);
                    $buktiFile = $newFileName;
                }
            }

            if (empty($error)) {
                if ($nominal <= 0) {
                    $error = 'Nominal transaksi harus lebih besar dari Rp 0.';
                } elseif ($jenis === 'Pengeluaran' && $nominal > $currentBalance) {
                    $error = 'Gagal: Nominal pengeluaran (Rp ' . number_format($nominal, 0, ',', '.') . ') melebihi saldo kas yang tersedia (Rp ' . number_format($currentBalance, 0, ',', '.') . ').';
                } else {
                    $saldoSebelum = $currentBalance;
                    $saldoSetelah = ($jenis === 'Pemasukan') ? ($saldoSebelum + $nominal) : ($saldoSebelum - $nominal);
                    $adminId = $_SESSION['user_id'] ?? 1;

                    try {
                        $stmtIns = $pdo->prepare("INSERT INTO petty_cash (id_admin, tanggal, jenis, kategori, nominal, saldo_setelah, keterangan, bukti_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtIns->execute([$adminId, $tanggal, $jenis, $kategori, $nominal, $saldoSetelah, $keterangan, $buktiFile]);
                    } catch (PDOException $e) {
                        $stmtIns = $pdo->prepare("INSERT INTO petty_cash (id_admin, tanggal, jenis, nominal, keterangan) VALUES (?, ?, ?, ?, ?)");
                        $stmtIns->execute([$adminId, $tanggal, $jenis, $nominal, $keterangan]);
                    }

                    $message = 'Transaksi petty cash berhasil disimpan ke database!';
                    $currentBalance = $saldoSetelah;
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id_transaksi'] ?? 0);
            if ($id > 0) {
                $stmtDel = $pdo->prepare("DELETE FROM petty_cash WHERE id_transaksi = ?");
                $stmtDel->execute([$id]);
                $message = 'Transaksi berhasil dihapus!';
            }
        }
    }
}

// Fetch Filtered Petty Cash List with Pagination (Max 5 per page)
$filterJenis = trim($_GET['jenis'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 5;

$countSql = "SELECT COUNT(*) FROM petty_cash WHERE 1=1";
$sql = "SELECT * FROM petty_cash WHERE 1=1";
$params = [];

if (!empty($filterJenis)) {
    $whereJ = " AND jenis = ?";
    $countSql .= $whereJ;
    $sql .= $whereJ;
    $params[] = $filterJenis;
}
if (!empty($search)) {
    $whereS = " AND (keterangan LIKE ? OR jenis LIKE ?)";
    $countSql .= $whereS;
    $sql .= $whereS;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = intval($stmtCount->fetchColumn());
$totalPages = ceil($totalRecords / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

$sql .= " ORDER BY id_transaksi DESC LIMIT {$perPage} OFFSET {$offset}";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$transactions = $stmtList->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span class="material-symbols-outlined">error</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<!-- Balance Overview Cards -->
<div class="card-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Pemasukan</span>
            <div class="stat-icon"><span class="material-symbols-outlined">add_circle</span></div>
        </div>
        <div class="stat-value" style="color: var(--status-success-text);">
            Rp <?= number_format($totalPemasukan, 0, ',', '.') ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Pengeluaran</span>
            <div class="stat-icon"><span class="material-symbols-outlined">remove_circle</span></div>
        </div>
        <div class="stat-value" style="color: var(--status-danger-text);">
            Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Saldo Berjalan Kas Kecil</span>
            <div class="stat-icon"><span class="material-symbols-outlined">account_balance</span></div>
        </div>
        <div class="stat-value">
            Rp <?= number_format($currentBalance, 0, ',', '.') ?>
        </div>
    </div>
</div>

<div class="form-row" style="align-items: start; margin-bottom: 28px;">
    <!-- Form Input Petty Cash -->
    <div class="panel" style="flex: 1; min-width: 300px; margin-bottom: 0;">
        <div class="panel-header">
            <h2 class="panel-title">Input Transaksi Petty Cash</h2>
        </div>

        <form method="POST" action="petty_cash.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label class="form-label">Tanggal Transaksi</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Jenis Transaksi</label>
                <select name="jenis" class="form-control" id="jenisSelect" onchange="toggleCategoryOptions()">
                    <option value="Pengeluaran">Pengeluaran (Kas Keluar)</option>
                    <option value="Pemasukan">Pemasukan (Kas Masuk)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Kategori Transaksi</label>
                <select name="kategori" id="kategoriSelect" class="form-control">
                    <option value="ATK & Cetak Dokumen">ATK & Cetak Dokumen</option>
                    <option value="Operasional Unit & Kebersihan">Operasional Unit & Kebersihan</option>
                    <option value="Konsumsi & Rapat">Konsumsi & Rapat</option>
                    <option value="Transportasi & Kurir">Transportasi & Kurir</option>
                    <option value="Lain-lain">Lain-lain</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Nominal (Rp)</label>
                <input type="text" name="nominal" class="form-control" placeholder="Contoh: 50.000" onkeyup="formatRupiahInput(this)" required>
            </div>

            <div class="form-group">
                <label class="form-label">Keterangan Transaksi</label>
                <textarea name="keterangan" class="form-control" rows="3" placeholder="Rincian kebutuhan pengeluaran/pemasukan kas..." required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Upload Bukti Transaksi (Opsional)</label>
                <input type="file" name="bukti" class="form-control" accept="image/png,image/jpeg,.pdf" style="padding: 8px 14px;">
                <small style="color: var(--text-muted); font-size: 11px;">Format: PNG, JPG, JPEG, PDF (Max 2MB)</small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="reset" class="btn btn-secondary">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>

    <!-- Table Daftar Transaksi (Fit 100% Horizontal) -->
    <div class="panel" style="flex: 1.8; min-width: 0; margin-bottom: 0;">
        <div class="panel-header" style="flex-wrap: wrap; gap: 10px;">
            <h2 class="panel-title" style="font-size: 16px;">Daftar Transaksi Petty Cash</h2>
            <div style="display: flex; gap: 6px;">
                <a href="laporan_petty_cash.php" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;">
                    <span class="material-symbols-outlined" style="font-size: 14px;">analytics</span>
                    <span>Laporan</span>
                </a>
                <a href="export.php?type=petty_cash&jenis=<?= $filterJenis ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;">
                    <span class="material-symbols-outlined" style="font-size: 14px;">download</span>
                    <span>Export Excel</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="petty_cash.php" style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center;">
            <select name="jenis" class="form-control" style="width: 155px; min-width: 155px; height: 38px; padding: 0 30px 0 12px; font-size: 13px;" onchange="this.form.submit()">
                <option value="">Semua Jenis</option>
                <option value="Pemasukan" <?= $filterJenis === 'Pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                <option value="Pengeluaran" <?= $filterJenis === 'Pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
            </select>
            <input type="text" name="search" class="form-control" style="flex: 1; height: 38px; padding: 0 12px; font-size: 13px;" placeholder="Cari keterangan..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">Filter</button>
            <?php if ($filterJenis || $search): ?>
                <a href="petty_cash.php" class="btn btn-secondary" style="height: 38px; padding: 0 14px; font-size: 13px; display: inline-flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-container" style="overflow-x: hidden;">
            <table class="table table-compact" style="width: 100%; table-layout: auto;">
                <thead>
                    <tr>
                        <th class="table-nowrap">Tanggal</th>
                        <th>Kategori & Keterangan</th>
                        <th class="table-nowrap">Nominal</th>
                        <th class="table-nowrap">Saldo Akhir</th>
                        <th class="table-nowrap" style="text-align: center;">Bukti</th>
                        <th class="table-nowrap" style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada data transaksi kas kecil</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="table-nowrap"><strong><?= date('d/m/Y', strtotime($t['tanggal'] ?? 'now')) ?></strong></td>
                                <td>
                                    <strong style="display: block; line-height: 1.2; font-size: 12px;"><?= htmlspecialchars($t['kategori'] ?? $t['keterangan'] ?? 'Transaksi Kas') ?></strong>
                                    <small style="color: var(--text-muted); font-size: 11px; display: block; line-height: 1.2; margin-top: 2px;"><?= htmlspecialchars($t['keterangan'] ?? '-') ?></small>
                                </td>
                                <td class="table-nowrap">
                                    <strong style="color: <?= ($t['jenis'] ?? '') === 'Pemasukan' ? 'var(--status-success-text)' : 'var(--status-danger-text)' ?>; font-size: 12px;">
                                        <?= ($t['jenis'] ?? '') === 'Pemasukan' ? '+' : '-' ?> Rp <?= number_format($t['nominal'] ?? 0, 0, ',', '.') ?>
                                    </strong>
                                </td>
                                <td class="table-nowrap">
                                    <strong style="font-size: 12px;">Rp <?= number_format($t['saldo_setelah'] ?? $t['nominal'] ?? 0, 0, ',', '.') ?></strong>
                                </td>
                                <td class="table-nowrap" style="text-align: center;">
                                    <?php if (!empty($t['bukti_file'])): ?>
                                        <a href="assets/uploads/<?= htmlspecialchars($t['bukti_file']) ?>" target="_blank" class="btn btn-secondary btn-square" title="Lihat Bukti Transaksi">
                                            <span class="material-symbols-outlined" style="font-size: 18px;">receipt_long</span>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 11px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-nowrap" style="text-align: right;">
                                    <form method="POST" action="petty_cash.php" style="display: inline;" onsubmit="return confirmAction({ title: 'Hapus Transaksi Kas', message: 'Apakah Anda yakin ingin menghapus catatan transaksi kas kecil ini secara permanen?', type: 'danger', icon: 'delete_forever', btnText: 'Ya, Hapus Transaksi', onConfirm: this });">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_transaksi" value="<?= $t['id_transaksi'] ?>">
                                        <button type="submit" class="btn btn-danger btn-square" title="Hapus Transaksi">
                                            <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Render Reusable Pagination Controls (Max 5 per page) -->
        <?= render_pagination($page, $totalPages, ['jenis' => $filterJenis, 'search' => $search]) ?>
    </div>
</div>

<script>
function toggleCategoryOptions() {
    const jenis = document.getElementById('jenisSelect').value;
    const kategori = document.getElementById('kategoriSelect');

    kategori.innerHTML = '';
    if (jenis === 'Pemasukan') {
        kategori.add(new Option('Saldo Awal', 'Saldo Awal'));
        kategori.add(new Option('Dana Operasional Unit', 'Dana Operasional Unit'));
        kategori.add(new Option('Pemasukan Lainnya', 'Pemasukan Lainnya'));
    } else {
        kategori.add(new Option('ATK & Cetak Dokumen', 'ATK & Cetak Dokumen'));
        kategori.add(new Option('Operasional Unit & Kebersihan', 'Operasional Unit & Kebersihan'));
        kategori.add(new Option('Konsumsi & Rapat', 'Konsumsi & Rapat'));
        kategori.add(new Option('Transportasi & Kurir', 'Transportasi & Kurir'));
        kategori.add(new Option('Lain-lain', 'Lain-lain'));
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
