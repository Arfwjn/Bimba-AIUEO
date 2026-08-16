<?php
// presensi.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Presensi QR Code (Fast Scan)';
$pageBreadcrumb = 'Dashboard > Presensi QR Code';

$pdo = getDB();
$today = date('Y-m-d');

// Fetch Active Employees for Quick Scan Simulator Dropdown
$stmtEmps = $pdo->query("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status_aktif = 1 ORDER BY nama ASC");
$activeEmployees = $stmtEmps->fetchAll();

// Otomatisasi Pencatatan Status "Tidak Hadir" (Alpha)
auto_mark_absent_employees($pdo);

$message = '';
$error = '';

// Handle POST Actions (Input Izin / Sakit Manual oleh Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan CSRF Token gagal.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'input_izin_sakit') {
            $empId = intval($_POST['id_karyawan'] ?? 0);
            $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
            $status = trim($_POST['status'] ?? 'Izin');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $buktiFile = null;

            if ($empId > 0 && in_array($status, ['Izin', 'Sakit'])) {
                if (isset($_FILES['bukti_surat']) && $_FILES['bukti_surat']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['bukti_surat']['tmp_name'];
                    $fileName = $_FILES['bukti_surat']['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (in_array($ext, $allowedExts)) {
                        $uploadDir = __DIR__ . '/assets/uploads/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $newFileName = 'surat_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                            $buktiFile = $newFileName;
                        }
                    }
                }

                $stmtCheck = $pdo->prepare("SELECT id_presensi FROM presensi WHERE id_karyawan = ? AND (tanggal = ? OR DATE(tanggal) = ?)");
                $stmtCheck->execute([$empId, $tanggal, $tanggal]);
                $existRow = $stmtCheck->fetch();

                if ($existRow) {
                    $stmtUpd = $pdo->prepare("
                        UPDATE presensi 
                        SET status = ?, keterangan = ?, bukti_surat = COALESCE(?, bukti_surat), status_validasi = 'Surat Izin/Sakit' 
                        WHERE id_presensi = ?
                    ");
                    $stmtUpd->execute([$status, $keterangan, $buktiFile, $existRow['id_presensi']]);
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, keterangan, bukti_surat, status_validasi, raw_payload) 
                        VALUES (?, ?, NULL, NULL, ?, ?, ?, 'Surat Izin/Sakit', 'MANUAL_PERMIT')
                    ");
                    $stmtIns->execute([$empId, $tanggal, $status, $keterangan, $buktiFile]);
                }

                $message = "Status {$status} karyawan berhasil dicatat dan diproses oleh sistem!";
            } else {
                $error = 'Harap pilih karyawan dan status izin/sakit yang valid.';
            }
        }
    }
}

// Fetch Today's Attendance List with Pagination (Max 5 per page)
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 5;

$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM presensi p 
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
    WHERE (p.tanggal = ? OR DATE(p.tanggal) = ?)
");
$stmtCount->execute([$today, $today]);
$totalRecords = intval($stmtCount->fetchColumn());
$totalPages = ceil($totalRecords / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

$stmtToday = $pdo->prepare("
    SELECT p.*, k.nama, k.jabatan 
    FROM presensi p 
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
    WHERE (p.tanggal = ? OR DATE(p.tanggal) = ?) 
    ORDER BY p.id_presensi DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtToday->execute([$today, $today]);
$todayList = $stmtToday->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="form-row" style="align-items: start; margin-bottom: 28px;">
    <!-- Left Column: Scanner Viewport -->
    <div class="panel" style="flex: 1.2; margin-bottom: 0;">
        <div class="panel-header">
            <h2 class="panel-title">Scanner QR Code Kamera</h2>
            <span class="badge badge-info">AES-256 Validated</span>
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
            Arahkan kamera smartphone/webcam ke QR Code karyawan. Scan 1 = Presensi Masuk, Scan 2 = Presensi Keluar.
        </p>

        <!-- Scanner Box / Video Container (video feed only, no library UI chrome) -->
        <div class="scanner-box" id="scannerViewport" style="min-height: 280px; position: relative;">
            <div id="cameraLoadingPlaceholder" style="text-align: center; padding: 40px 20px;">
                <span class="material-symbols-outlined" style="font-size: 64px; margin-bottom: 12px; color: #FFFFFF;">videocam</span>
                <p style="font-family: var(--font-sans); font-size: 14px; color: #FFFFFF;">Memuat daftar kamera...</p>
            </div>
        </div>

        <!-- Camera Controls (custom, outside the video box, styled per tema situs) -->
        <div id="cameraControls" style="display: none; margin-top: 12px; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select id="cameraSelect" class="form-control" style="flex: 1; min-width: 180px;"></select>
            <button type="button" id="toggleScanBtn" class="btn btn-primary" onclick="toggleScanning()">
                <span class="material-symbols-outlined" style="font-size: 16px;" id="toggleScanIcon">play_circle</span>
                <span id="toggleScanLabel">Mulai Scan</span>
            </button>
        </div>

        <!-- Combined Alternative Scan & Presensi Instant Toolbar -->
        <div style="margin-top: 16px; padding: 12px; background-color: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 8px; display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <!-- Upload File Gambar QR -->
            <label class="btn btn-secondary btn-sm" style="cursor: pointer; margin: 0; white-space: nowrap;">
                <span class="material-symbols-outlined" style="font-size: 16px;">upload_file</span>
                <span>Upload Gambar QR</span>
                <input type="file" id="qrFileInput" accept="image/*" style="display: none;" onchange="scanQRFromFile(this)">
            </label>

            <!-- Presensi Cepat Searchable Custom Employee Combobox -->
            <div style="display: flex; gap: 8px; align-items: center; flex: 1; justify-content: flex-end; min-width: 260px;">
                <input type="hidden" id="quickSelectEmp" value="">
                <div class="custom-emp-select-wrapper" style="position: relative; flex: 1; max-width: 240px;">
                    <div class="custom-emp-select-trigger" style="height: 36px; font-size: 12px; padding: 0 10px;" onclick="toggleEmpSearchPopover('popoverEmpQuickScan', event)">
                        <span id="quickSelectEmpLabel" style="color: var(--text-muted);">Pilih Karyawan</span>
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--text-muted);">expand_more</span>
                    </div>

                    <!-- Popover Panel with Live Search -->
                    <div id="popoverEmpQuickScan" class="emp-search-popover" style="display: none; width: 260px;" onclick="event.stopPropagation();">
                        <div class="emp-search-input-wrapper">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" class="emp-search-input" placeholder="Cari nama atau kode..." onkeyup="filterEmpOptions('popoverEmpQuickScan', this.value)">
                        </div>
                        <div class="emp-list-options">
                            <?php foreach ($activeEmployees as $emp): 
                                $eCode = 'EMP-' . sprintf('%03d', $emp['id_karyawan']);
                                $eLabel = $eCode . ' - ' . htmlspecialchars($emp['nama']);
                            ?>
                                <div class="emp-option-item" 
                                     data-search-text="<?= htmlspecialchars($emp['nama'] . ' ' . $eLabel) ?>" 
                                     onclick="selectEmpOption('quickSelectEmp', 'quickSelectEmpLabel', '<?= $eCode ?>', '<?= addslashes($eLabel) ?>')">
                                    <span><?= $eLabel ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="emp-no-result" style="display: none; padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">Karyawan tidak ditemukan</div>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-sm" style="height: 36px; white-space: nowrap;" onclick="executeQuickScan()">
                    <span class="material-symbols-outlined" style="font-size: 16px;">bolt</span>
                    <span>Instant</span>
                </button>
            </div>
        </div>

        <!-- Manual Payload Input Fallback -->
        <div class="form-group" style="margin-top: 16px;">
            <label class="form-label">Input / Paste AES Encrypted Payload Manual</label>
            <div style="display: flex; gap: 8px;">
                <input type="text" id="manualPayload" class="form-control" placeholder="Tempelkan teks payload AES di sini...">
                <button type="button" class="btn btn-primary" onclick="submitScanPayload()">Submit</button>
            </div>
        </div>
    </div>

    <!-- Right Column: Result Card -->
    <div class="panel" style="flex: 1; margin-bottom: 0;">
        <div class="panel-header">
            <h2 class="panel-title">Hasil Presensi Real-Time</h2>
        </div>

        <div id="resultCard" style="display: none; text-align: center; padding: 12px 0;">
            <div id="resultBadgeContainer" style="margin-bottom: 16px;">
                <span id="resultBadge" class="badge badge-success" style="font-size: 14px; padding: 8px 18px;">HADIR</span>
            </div>

            <div style="width: 72px; height: 72px; background-color: var(--border-color); color: #FFFFFF; font-size: 28px; font-weight: 700; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;" id="resultAvatar">
                ?
            </div>

            <h3 id="resultNama" style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">Nama Karyawan</h3>
            <div id="resultJabatan" style="font-family: var(--font-sans); color: var(--text-muted); font-weight: 600; margin-bottom: 16px;">Jabatan</div>

            <div style="background-color: var(--surface-muted); border: 2px solid var(--border-color); border-radius: 8px; padding: 14px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; font-family: var(--font-sans); font-size: 12px; text-align: center;">
                <div>
                    <span style="color: var(--text-muted);">Tanggal:</span><br>
                    <strong id="resultTanggal"><?= date('d M Y') ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted);">Jam Masuk:</span><br>
                    <strong id="resultJamMasuk" style="color: var(--status-success-text);">--:--:--</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted);">Jam Keluar:</span><br>
                    <strong id="resultJamKeluar" style="color: var(--status-warning-text);">--:--:--</strong>
                </div>
            </div>

            <p id="resultMsg" style="margin-top: 14px; font-size: 13px; color: var(--text-muted);"></p>
        </div>

        <!-- Initial Placeholder State -->
        <div id="resultPlaceholder" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
            <span class="material-symbols-outlined" style="font-size: 48px; margin-bottom: 8px;">verified_user</span>
            <p>Hasil pemindaian presensi akan ditampilkan di sini secara otomatis.</p>
        </div>

        <!-- Render Reusable Pagination Controls (Max 5 per page) -->
        <?= render_pagination($page, $totalPages) ?>
    </div>
</div>

<!-- Table Today's Attendance -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">Riwayat Presensi Hari Ini (<?= date('d M Y') ?>)</h3>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-secondary btn-sm" onclick="openModal('modalInputIzinSakit')">
                <span class="material-symbols-outlined" style="font-size: 16px;">description</span>
                <span>Input Izin / Sakit</span>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode Karyawan</th>
                    <th>Nama & Jabatan</th>
                    <th>Presensi Masuk</th>
                    <th>Presensi Keluar</th>
                    <th style="text-align: center;">Bukti Surat</th>
                    <th>Validasi System</th>
                </tr>
            </thead>
            <tbody id="todayAttendanceTable">
                <?php if (empty($todayList)): ?>
                    <tr id="emptyTablePlaceholder"><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada presensi yang dicatat hari ini</td></tr>
                <?php else: ?>
                    <?php foreach ($todayList as $p): 
                        $b = get_attendance_detail_badges($p);
                    ?>
                        <tr id="presRow_<?= $p['id_presensi'] ?>">
                            <td><strong>EMP-<?= sprintf('%03d', $p['id_karyawan']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                                <small style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars($p['jabatan']) ?></small>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <strong style="color: var(--status-success-text); font-size: 13px;"><?= htmlspecialchars($p['jam_masuk'] ?? '-') ?></strong>
                                    <span class="badge badge-<?= $b['masuk']['class'] ?>"><?= htmlspecialchars($b['masuk']['text']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <strong style="color: var(--status-warning-text); font-size: 13px;"><?= htmlspecialchars($p['jam_keluar'] ?? '-') ?></strong>
                                    <span class="badge badge-<?= $b['keluar']['class'] ?>"><?= htmlspecialchars($b['keluar']['text']) ?></span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if (!empty($p['bukti_surat'])): ?>
                                    <a href="assets/uploads/<?= htmlspecialchars($p['bukti_surat']) ?>" target="_blank" class="btn btn-secondary btn-square" title="Lihat Surat Izin/Sakit">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">description</span>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array($p['status'], ['Izin', 'Sakit'])): ?>
                                    <span class="badge badge-info">Surat Admin</span>
                                <?php elseif ($p['status'] === 'Tidak Hadir'): ?>
                                    <span class="badge badge-danger">Sistem Auto</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Valid AES</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/html5-qrcode.min.js"></script>
<script src="assets/js/qr-scanner-pro.js"></script>
<script>
let html5QrCodeInstance = null;
let isScanLocked = false;
let lastScannedText = '';
let availableCameras = [];

const SCAN_CONFIG = {
    fps: 10,
    qrbox: function(viewfinderWidth, viewfinderHeight) {
        let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
        let qrboxSize = Math.floor(minEdgeSize * 0.7);
        return {
            width: Math.max(qrboxSize, 180),
            height: Math.max(qrboxSize, 180)
        };
    },
    experimentalFeatures: {
        useBarCodeDetectorIfSupported: true
    }
};

document.addEventListener('DOMContentLoaded', function() {
    initCameraScanner();

    const select = document.getElementById('cameraSelect');
    if (select) {
        select.addEventListener('change', async function() {
            if (html5QrCodeInstance && html5QrCodeInstance.isScanning) {
                await stopScanning();
                await startScanning();
            }
        });
    }
});

async function initCameraScanner() {
    if (typeof Html5Qrcode === 'undefined') return;

    const placeholder = document.getElementById('cameraLoadingPlaceholder');
    const controls = document.getElementById('cameraControls');
    const select = document.getElementById('cameraSelect');

    try {
        availableCameras = await Html5Qrcode.getCameras();

        if (!availableCameras || availableCameras.length === 0) {
            if (placeholder) {
                placeholder.innerHTML = '<p style="font-family: var(--font-sans); font-size: 13px; color: #FFFFFF;">Kamera tidak ditemukan.</p>';
            }
            return;
        }

        select.innerHTML = '';
        availableCameras.forEach(function(cam, idx) {
            const opt = document.createElement('option');
            opt.value = cam.id;
            opt.textContent = cam.label || ('Kamera ' + (idx + 1));
            select.appendChild(opt);
        });

        if (placeholder) placeholder.style.display = 'none';
        controls.style.display = 'flex';

        await startScanning();
    } catch (e) {
        console.error("Camera init error:", e);
        if (placeholder) {
            placeholder.innerHTML = '<p style="font-family: var(--font-sans); font-size: 13px; color: #FFFFFF;">Tidak dapat mengakses kamera. Gunakan input payload manual.</p>';
        }
    }
}

async function startScanning() {
    const select = document.getElementById('cameraSelect');
    const cameraId = select.value;
    if (!cameraId) return;

    if (!html5QrCodeInstance) {
        html5QrCodeInstance = new Html5Qrcode("scannerViewport");
    }

    try {
        await html5QrCodeInstance.start(cameraId, SCAN_CONFIG, onScanSuccess, onScanError);
        setToggleButtonState(true);
    } catch (e) {
        console.error("Start scan error:", e);
        alert('Tidak dapat mengaktifkan kamera terpilih: ' + e);
    }
}

async function stopScanning() {
    if (html5QrCodeInstance && html5QrCodeInstance.isScanning) {
        try {
            await html5QrCodeInstance.stop();
        } catch (e) {
            console.error("Stop scan error:", e);
        }
    }
    setToggleButtonState(false);
}

function toggleScanning() {
    if (html5QrCodeInstance && html5QrCodeInstance.isScanning) {
        stopScanning();
    } else {
        startScanning();
    }
}

function setToggleButtonState(isScanning) {
    const icon = document.getElementById('toggleScanIcon');
    const label = document.getElementById('toggleScanLabel');
    const btn = document.getElementById('toggleScanBtn');
    if (isScanning) {
        icon.textContent = 'stop_circle';
        label.textContent = 'Hentikan Scan';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    } else {
        icon.textContent = 'play_circle';
        label.textContent = 'Mulai Scan';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
    }
}

function onScanSuccess(decodedText) {
    if (isScanLocked || decodedText === lastScannedText) {
        return;
    }
    isScanLocked = true;
    lastScannedText = decodedText;

    processPayload(decodedText, function() {
        setTimeout(function() {
            isScanLocked = false;
            lastScannedText = '';
        }, 4000);
    });
}

function onScanError(error) {
    // Ignore frame scan errors
}

function scanQRFromFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    let decoderContainer = document.getElementById('qrFileDecoderContainer');
    if (!decoderContainer) {
        decoderContainer = document.createElement('div');
        decoderContainer.id = 'qrFileDecoderContainer';
        decoderContainer.style.display = 'none';
        document.body.appendChild(decoderContainer);
    }

    const html5QrCode = new Html5Qrcode("qrFileDecoderContainer");

    // Pass 1: Try Direct File Scan
    html5QrCode.scanFile(file, true)
        .then(decodedText => {
            input.value = '';
            onScanSuccess(decodedText);
        })
        .catch(err => {
            // Pass 2: Canvas Downscale Fallback for Large Smartphone Photos
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const maxDim = 800;
                    let w = img.width;
                    let h = img.height;
                    if (w > maxDim || h > maxDim) {
                        if (w > h) {
                            h = Math.round((h * maxDim) / w);
                            w = maxDim;
                        } else {
                            w = Math.round((w * maxDim) / h);
                            h = maxDim;
                        }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    ctx.drawImage(img, 0, 0, w, h);

                    canvas.toBlob(function(blob) {
                        if (!blob) {
                            alert("Gagal membaca QR Code dari file gambar.");
                            input.value = '';
                            return;
                        }
                        const resizedFile = new File([blob], "resized_qr.jpg", { type: "image/jpeg" });
                        html5QrCode.scanFile(resizedFile, true)
                            .then(decoded => {
                                input.value = '';
                                onScanSuccess(decoded);
                            })
                            .catch(err2 => {
                                alert("Gagal membaca QR Code dari file gambar. Pastikan foto QR Code terlihat jelas.");
                                input.value = '';
                            });
                    }, 'image/jpeg', 0.9);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
}

// Global USB Hardware Barcode / QR Scanner Key Listener
let barcodeBuffer = '';
let barcodeTimeout = null;

document.addEventListener('keydown', function(e) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
        return;
    }
    if (e.key === 'Enter') {
        if (barcodeBuffer.length >= 5) {
            processPayload(barcodeBuffer.trim());
        }
        barcodeBuffer = '';
    } else if (e.key.length === 1) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimeout);
        barcodeTimeout = setTimeout(() => { barcodeBuffer = ''; }, 250);
    }
});

function executeQuickScan() {
    const empCode = document.getElementById('quickSelectEmp').value;
    if (!empCode) {
        alert('Harap pilih karyawan terlebih dahulu.');
        return;
    }

    fetch(`api/qr_generator.php?emp_code=${empCode}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                processPayload(data.payload);
            } else {
                alert(data.message || 'Gagal membuat QR payload');
            }
        })
        .catch(err => alert('Error connecting to QR Generator API'));
}

function submitScanPayload() {
    const payload = document.getElementById('manualPayload').value.trim();
    if (!payload) {
        alert('Harap masukkan payload QR Code terlebih dahulu.');
        return;
    }
    processPayload(payload);
}

function processPayload(payloadText, callback) {
    fetch('api/presensi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payload: payloadText })
    })
    .then(res => res.json())
    .then(data => {
        if (typeof fastQR !== 'undefined' && data.success) {
            fastQR.playBeepSound();
            fastQR.flashSuccessVisual();
        }
        displayResult(data);
        if (typeof callback === 'function') callback();
    })
    .catch(err => {
        alert('Gagal menghubungi server validator presensi.');
        if (typeof callback === 'function') callback();
    });
}

function displayResult(res) {
    const card = document.getElementById('resultCard');
    const placeholder = document.getElementById('resultPlaceholder');
    const badge = document.getElementById('resultBadge');
    
    placeholder.style.display = 'none';
    card.style.display = 'block';

    if (res.success) {
        if (res.action === 'check_out') {
            badge.className = 'badge badge-info';
            badge.textContent = 'PRESENSI KELUAR';
        } else {
            let st = (res.status || 'Hadir').toUpperCase();
            badge.textContent = st;
            if (st === 'HADIR') {
                badge.className = 'badge badge-success'; // Hijau: Hadir & Tepat Waktu
            } else if (st === 'TERLAMBAT') {
                badge.className = 'badge badge-warning'; // Kuning/Oranye: Terlambat
            } else if (st === 'TIDAK HADIR' || st === 'ALPHA') {
                badge.className = 'badge badge-danger'; // Merah: Tidak Hadir (setelah jam keluar)
            } else {
                badge.className = 'badge badge-info';
            }
        }
        
        document.getElementById('resultAvatar').textContent = res.employee.nama.charAt(0).toUpperCase();
        document.getElementById('resultNama').textContent = res.employee.nama;
        document.getElementById('resultJabatan').textContent = res.employee.emp_code + ' • ' + res.employee.jabatan;
        document.getElementById('resultTanggal').textContent = res.tanggal;
        document.getElementById('resultJamMasuk').textContent = res.jam_masuk || '--:--:--';
        document.getElementById('resultJamKeluar').textContent = res.jam_keluar || '--:--:--';
        document.getElementById('resultMsg').textContent = res.message;

        // Auto reload page after 2.5s to show updated table state
        setTimeout(() => location.reload(), 2500);
    } else {
        badge.className = (res.status === 'Complete') ? 'badge badge-info' : 'badge badge-danger';
        badge.textContent = (res.status || 'ERROR').toUpperCase();

        document.getElementById('resultAvatar').textContent = '?';
        document.getElementById('resultNama').textContent = (res.status === 'Complete') ? 'Presensi Lengkap' : 'Validasi Gagal';
        document.getElementById('resultJabatan').textContent = res.employee ? (res.employee.emp_code + ' • ' + res.employee.nama) : (res.emp_code || '-');
        document.getElementById('resultTanggal').textContent = res.tanggal || '--';
        document.getElementById('resultJamMasuk').textContent = res.jam_masuk || '--:--:--';
        document.getElementById('resultJamKeluar').textContent = res.jam_keluar || '--:--:--';
        document.getElementById('resultMsg').textContent = res.message;
    }
}
</script>

<!-- Modal Input Izin / Sakit (Surat Dokter / Permohonan Izin) -->
<div class="modal-backdrop" id="modalInputIzinSakit">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="color: #000000ff;">description</span>
                <span>Input Status Izin / Sakit Karyawan</span>
            </h3>
            <button class="btn-close" onclick="closeModal('modalInputIzinSakit')">&times;</button>
        </div>
        <form method="POST" action="presensi.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="input_izin_sakit">

            <div class="modal-body">
                <!-- Custom Searchable Employee Select -->
                <div class="form-group custom-emp-select-wrapper" style="position: relative;">
                    <label class="form-label">Pilih Karyawan</label>
                    <input type="hidden" name="id_karyawan" id="modalPresensiEmpIdInput" required>
                    <div class="custom-emp-select-trigger" onclick="toggleEmpSearchPopover('popoverEmpPresensiModal', event)">
                        <span id="modalPresensiEmpLabel" style="color: var(--text-muted);">-- Pilih Karyawan --</span>
                        <span class="material-symbols-outlined" style="font-size: 20px; color: var(--text-muted);">expand_more</span>
                    </div>

                    <!-- Popover Panel with Live Search -->
                    <div id="popoverEmpPresensiModal" class="emp-search-popover" style="display: none;" onclick="event.stopPropagation();">
                        <div class="emp-search-input-wrapper">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" class="emp-search-input" placeholder="Cari nama atau kode..." onkeyup="filterEmpOptions('popoverEmpPresensiModal', this.value)">
                        </div>
                        <div class="emp-list-options">
                            <?php foreach ($activeEmployees as $e): 
                                $eLabel = 'EMP-' . sprintf('%03d', $e['id_karyawan']) . ' - ' . htmlspecialchars($e['nama']);
                            ?>
                                <div class="emp-option-item" 
                                     data-search-text="<?= htmlspecialchars($e['nama'] . ' ' . $eLabel) ?>" 
                                     onclick="selectEmpOption('modalPresensiEmpIdInput', 'modalPresensiEmpLabel', <?= $e['id_karyawan'] ?>, '<?= addslashes($eLabel) ?>')">
                                    <span><?= $eLabel ?></span>
                                    <small style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars($e['jabatan']) ?></small>
                                </div>
                            <?php endforeach; ?>
                            <div class="emp-no-result" style="display: none; padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">Karyawan tidak ditemukan</div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Presensi</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jenis Status</label>
                        <select name="status" class="form-control" required>
                            <option value="Izin">Izin (Pribadi/Dinas)</option>
                            <option value="Sakit">Sakit (Surat Dokter)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Gambar Surat Izin/Sakit (Foto DM)</label>
                    <input type="file" name="bukti_surat" class="form-control" accept="image/png,image/jpeg,.pdf">
                    <small style="color: var(--text-muted); font-size: 11px;">Format: PNG, JPG, JPEG, PDF (Max 2MB). Tangkapan layar / foto surat karyawan.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Keterangan / Alasan</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan alasan permohonan izin atau diagnosa surat dokter..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalInputIzinSakit')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Presensi Izin/Sakit</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>