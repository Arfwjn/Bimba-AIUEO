<?php
// karyawan.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Data Karyawan';
$pageBreadcrumb = 'Dashboard > Data Karyawan';

$pdo = getDB();
$message = '';
$error = '';

// Auto-migration check: Ensure status_aktif column exists in karyawan table
try {
    $pdo->exec("ALTER TABLE karyawan ADD status_aktif INT DEFAULT 1");
} catch (Exception $e) {
    // Column already exists, ignore
}

// Handle POST actions (Create, Update, Toggle Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan CSRF Token gagal.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $status = intval($_POST['status_aktif'] ?? 1);

            if (!empty($nama) && !empty($jabatan)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO karyawan (nama, jabatan, status_aktif) VALUES (?, ?, ?)");
                    $stmt->execute([$nama, $jabatan, $status]);
                    $message = 'Karyawan baru berhasil ditambahkan!';
                } catch (PDOException $e) {
                    $error = 'Gagal menambah data karyawan: ' . $e->getMessage();
                }
            } else {
                $error = 'Harap isi Nama dan Jabatan.';
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id_karyawan'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $status = intval($_POST['status_aktif'] ?? 1);

            if ($id > 0 && !empty($nama)) {
                try {
                    $stmt = $pdo->prepare("UPDATE karyawan SET nama = ?, jabatan = ?, status_aktif = ? WHERE id_karyawan = ?");
                    $stmt->execute([$nama, $jabatan, $status, $id]);
                    $message = 'Data karyawan berhasil diperbarui!';
                } catch (PDOException $e) {
                    $error = 'Gagal memperbarui data karyawan: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle') {
            $id = intval($_POST['id_karyawan'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE karyawan SET status_aktif = CASE WHEN COALESCE(status_aktif, 1) = 1 THEN 0 ELSE 1 END WHERE id_karyawan = ?");
                    $stmt->execute([$id]);
                    $message = 'Status aktif karyawan berhasil diubah!';
                } catch (PDOException $e) {
                    $error = 'Gagal mengubah status karyawan: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id_karyawan'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM karyawan WHERE id_karyawan = ?");
                    $stmt->execute([$id]);
                    $message = 'Data karyawan berhasil dihapus!';
                } catch (PDOException $e) {
                    $error = 'Gagal menghapus data karyawan.';
                }
            }
        }
    }
}

// Fetch Karyawan List with Search Filter
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM karyawan";
$params = [];
if (!empty($search)) {
    $sql .= " WHERE nama LIKE ? OR jabatan LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}
$sql .= " ORDER BY id_karyawan DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$karyawanList = $stmt->fetchAll();

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

<div class="panel">
    <div class="panel-header no-print">
        <div>
            <h2 class="panel-title">Daftar Karyawan Unit</h2>
            <p style="font-size: 13px; color: var(--text-muted);">Kelola data staf/motivator dan kredensial QR Code terenkripsi.</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="karyawan_portal.php" class="btn btn-secondary">
                <span class="material-symbols-outlined">badge</span>
                <span>Portal ID Card Karyawan</span>
            </a>
            <a href="export.php?type=karyawan" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Export Excel</span>
            </a>
            <button onclick="openModal('modalAddEmployee')" class="btn btn-primary">
                <span class="material-symbols-outlined">person_add</span>
                <span>Tambah Karyawan</span>
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <form method="GET" action="karyawan.php" style="margin-bottom: 20px;" class="no-print">
        <div style="display: flex; gap: 12px; max-width: 480px;">
            <input type="text" name="search" class="form-control" placeholder="Cari Berdasarkan Nama / Jabatan..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if ($search): ?>
                <a href="karyawan.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table Karyawan -->
    <div class="table-container no-print">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Lengkap</th>
                    <th>Jabatan</th>
                    <th>Status</th>
                    <th>QR Code & Portal</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($karyawanList)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                            Tidak ada data karyawan yang ditemukan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($karyawanList as $emp): ?>
                        <?php $isAktif = isset($emp['status_aktif']) ? intval($emp['status_aktif']) : 1; ?>
                        <tr>
                            <td><strong>EMP-<?= sprintf('%03d', $emp['id_karyawan']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($emp['nama']) ?></strong></td>
                            <td><?= htmlspecialchars($emp['jabatan']) ?></td>
                            <td>
                                <span class="badge badge-<?= $isAktif === 1 ? 'success' : 'danger' ?>">
                                    <?= $isAktif === 1 ? 'Aktif' : 'Non-Aktif' ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: inline-flex; gap: 6px;">
                                    <button class="btn btn-secondary btn-sm" onclick="showQRCode('EMP-<?= sprintf('%03d', $emp['id_karyawan']) ?>', '<?= htmlspecialchars(addslashes($emp['nama'])) ?>', '<?= htmlspecialchars(addslashes($emp['jabatan'])) ?>')">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">qr_code_2</span>
                                        <span>QR Code AES</span>
                                    </button>
                                    <a href="karyawan_portal.php?emp_id=<?= $emp['id_karyawan'] ?>" class="btn btn-secondary btn-sm" title="Buka Portal ID Card Karyawan Ini">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">id_card</span>
                                        <span>Portal ID</span>
                                    </a>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button class="btn btn-secondary btn-sm" onclick="editEmployee(<?= htmlspecialchars(json_encode($emp)) ?>)">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                                    </button>
                                    <form method="POST" action="karyawan.php" style="display: inline;" onsubmit="return confirmAction({ title: 'Ubah Status Karyawan', message: 'Apakah Anda yakin ingin mengubah status keaktifan karyawan ini?', type: 'warning', icon: 'sync_alt', btnText: 'Ya, Ubah Status', onConfirm: this });">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id_karyawan" value="<?= $emp['id_karyawan'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Toggle Status">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">sync_alt</span>
                                        </button>
                                    </form>
                                    <form method="POST" action="karyawan.php" style="display: inline;" onsubmit="return confirmAction({ title: 'Hapus Data Karyawan', message: 'Apakah Anda yakin ingin menghapus karyawan ini secara permanen?', type: 'danger', icon: 'delete_forever', btnText: 'Ya, Hapus Karyawan', onConfirm: this });">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_karyawan" value="<?= $emp['id_karyawan'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus Karyawan">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div id="modalAddEmployee" class="modal-backdrop">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 18px; font-weight: 700;">Tambah Karyawan Baru</h3>
            <button class="modal-close" onclick="closeModal('modalAddEmployee')">&times;</button>
        </div>
        <form method="POST" action="karyawan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Nama Lengkap Karyawan</label>
                <input type="text" name="nama" class="form-control" placeholder="Masukkan nama karyawan" required>
            </div>

            <div class="form-group">
                <label class="form-label">Jabatan</label>
                <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Motivator Utama" required>
            </div>

            <div class="form-group">
                <label class="form-label">Status Aktif</label>
                <select name="status_aktif" class="form-control">
                    <option value="1">Aktif</option>
                    <option value="0">Non-Aktif</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddEmployee')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Karyawan -->
<div id="modalEditEmployee" class="modal-backdrop">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 18px; font-weight: 700;">Edit Data Karyawan</h3>
            <button class="modal-close" onclick="closeModal('modalEditEmployee')">&times;</button>
        </div>
        <form method="POST" action="karyawan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id_karyawan" name="id_karyawan">

            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" id="edit_nama" name="nama" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Jabatan</label>
                <input type="text" id="edit_jabatan" name="jabatan" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Status Aktif</label>
                <select id="edit_status_aktif" name="status_aktif" class="form-control">
                    <option value="1">Aktif</option>
                    <option value="0">Non-Aktif</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditEmployee')">Batal</button>
                <button type="submit" class="btn btn-primary">Update Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal QR Code & ID Card Viewer -->
<style>
@media print {
    @page {
        size: 90mm 140mm;
        margin: 0;
    }
}
</style>
<div id="modalQRCode" class="modal-backdrop">
    <div class="modal-content" style="text-align: center; max-width: 440px;">
        <div class="modal-header">
            <h3 style="font-size: 18px; font-weight: 700;">Preview ID Card Digital</h3>
            <button class="modal-close" onclick="closeModal('modalQRCode')">&times;</button>
        </div>

        <!-- Official Physical ID Card Badge -->
        <div class="id-card-badge">
            <div class="id-card-header">
                <div class="id-card-header-title">biMBA AIUEO</div>
                <div class="id-card-header-sub">KARTU TANDA KARYAWAN & MOTIVATOR</div>
            </div>

            <div class="id-card-body">
                <div class="id-card-avatar" id="qrAvatarCircle">?</div>
                <h2 id="qrEmpNama" class="id-card-name">Nama Karyawan</h2>
                <div id="qrEmpJabatan" class="id-card-role">EMP-001 • Jabatan</div>

                <div id="qrContainer" class="id-card-qr"></div>

                <div class="id-card-payload">
                    <strong>AES-256 Payload:</strong> <span id="qrPayloadText">Generating...</span>
                </div>
            </div>

            <div class="id-card-footer">
                VALIDATED AES-256 ENCRYPTED • biMBA AIUEO UNIT KEBANGGAN
            </div>
        </div>

        <div style="display: flex; justify-content: center; gap: 12px; margin-top: 14px;" class="no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                <span>Cetak ID Card</span>
            </button>
            <button class="btn btn-secondary" onclick="closeModal('modalQRCode')">Tutup</button>
        </div>
    </div>
</div>

<script>
function editEmployee(emp) {
    document.getElementById('edit_id_karyawan').value = emp.id_karyawan;
    document.getElementById('edit_nama').value = emp.nama;
    document.getElementById('edit_jabatan').value = emp.jabatan;
    document.getElementById('edit_status_aktif').value = (emp.status_aktif !== undefined ? emp.status_aktif : 1);
    openModal('modalEditEmployee');
}

function showQRCode(empCode, nama, jabatan) {
    document.getElementById('qrEmpNama').textContent = nama;
    document.getElementById('qrEmpJabatan').textContent = empCode + ' • ' + jabatan;
    document.getElementById('qrAvatarCircle').textContent = nama.charAt(0).toUpperCase();
    document.getElementById('qrPayloadText').textContent = 'Generating encrypted payload...';

    fetch(`api/qr_generator.php?emp_code=${empCode}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('qrPayloadText').textContent = data.payload;
                generateQRCode('qrContainer', data.payload, 140, 140);
                openModal('modalQRCode');
            } else {
                alert(data.message || 'Gagal memuat QR Code');
            }
        })
        .catch(err => alert('Error connecting to QR Generator API'));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
