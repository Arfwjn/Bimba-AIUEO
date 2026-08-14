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

// Fetch Today's Attendance List
$stmtToday = $pdo->prepare("
    SELECT p.*, k.nama, k.jabatan 
    FROM presensi p 
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan 
    WHERE (p.tanggal = ? OR DATE(p.tanggal) = ?) 
    ORDER BY p.id_presensi DESC
");
$stmtToday->execute([$today, $today]);
$todayList = $stmtToday->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- 1-Click Quick Scan Simulation Bar -->
<div class="panel" style="margin-bottom: 24px; background-color: var(--surface-muted);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">                
                Presensi Cepat
            </h3>
            <p style="font-size: 12px; color: var(--text-muted);">Pilih karyawan dari daftar untuk mencatat Presensi Masuk / Keluar secara instant tanpa perlu kamera.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select id="quickSelectEmp" class="form-control" style="min-width: 240px;">
                <option value="">Pilih Karyawan</option>
                <?php foreach ($activeEmployees as $emp): ?>
                    <option value="EMP-<?= sprintf('%03d', $emp['id_karyawan']) ?>">
                        EMP-<?= sprintf('%03d', $emp['id_karyawan']) ?> - <?= htmlspecialchars($emp['nama']) ?> (<?= htmlspecialchars($emp['jabatan']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" onclick="executeQuickScan()">
                <span class="material-symbols-outlined">qr_code_scanner</span>
                <span>Presensi Instant</span>
            </button>
        </div>
    </div>
</div>

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

        <!-- Scanner Box / Video Container -->
        <div class="scanner-box" id="scannerViewport" style="min-height: 280px; position: relative;">
            <div id="cameraLoadingPlaceholder" style="text-align: center; padding: 40px 20px;">
                <span class="material-symbols-outlined" style="font-size: 64px; margin-bottom: 12px; color: #FFFFFF;">videocam</span>
                <p style="font-family: var(--font-sans); font-size: 14px; color: #FFFFFF;">Mengaktifkan Kamera Validator QR...</p>
            </div>
        </div>

        <!-- Alternative Scan Methods Toolbar -->
        <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <label class="btn btn-secondary btn-sm" style="cursor: pointer; margin: 0;">
                <span class="material-symbols-outlined" style="font-size: 16px;">upload_file</span>
                <span>Upload Gambar QR / Kartu</span>
                <input type="file" id="qrFileInput" accept="image/*" style="display: none;" onchange="scanQRFromFile(this)">
            </label>
            <span style="font-size: 11px; color: var(--text-muted);">Mendukung Hardware Scanner USB & Camera</span>
        </div>

        <!-- Manual Payload Input Fallback -->
        <div class="form-group" style="margin-top: 16px;">
            <label class="form-label">Input / Paste Encrypted Payload (Fallback Manual)</label>
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
    </div>
</div>

<!-- Table Today's Attendance -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">Riwayat Presensi Hari Ini (<?= date('d M Y') ?>)</h3>
        <button class="btn btn-secondary btn-sm" onclick="location.reload()">
            <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span>
            <span>Refresh</span>
        </button>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Kode Karyawan</th>
                    <th>Nama Karyawan</th>
                    <th>Jabatan</th>
                    <th>Status Presensi</th>
                    <th>Validasi Server</th>
                </tr>
            </thead>
            <tbody id="todayAttendanceTable">
                <?php if (empty($todayList)): ?>
                    <tr id="emptyTablePlaceholder"><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada presensi yang dicatat hari ini</td></tr>
                <?php else: ?>
                    <?php foreach ($todayList as $p): ?>
                        <tr id="presRow_<?= $p['id_presensi'] ?>">
                            <td><strong style="color: var(--status-success-text);"><?= htmlspecialchars($p['jam_masuk'] ?? '-') ?></strong></td>
                            <td>
                                <?php if (!empty($p['jam_keluar'])): ?>
                                    <strong style="color: var(--status-warning-text);"><?= htmlspecialchars($p['jam_keluar']) ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">Belum Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td>EMP-<?= sprintf('%03d', $p['id_karyawan']) ?></td>
                            <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
                            <td><?= htmlspecialchars($p['jabatan']) ?></td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'Hadir' ? 'success' : 'warning' ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                    <?= !empty($p['jam_keluar']) ? ' (Selesai)' : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">AES Valid</span>
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
let scannerInstance = null;
let isScanLocked = false;
let lastScannedText = '';

document.addEventListener('DOMContentLoaded', function() {
    initCameraScanner();
});

function initCameraScanner() {
    if (typeof Html5QrcodeScanner === 'undefined') return;

    try {
        const config = { 
            fps: 25, 
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                let qrboxSize = Math.floor(minEdgeSize * 0.85);
                return {
                    width: Math.max(qrboxSize, 180),
                    height: Math.max(qrboxSize, 180)
                };
            },
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            },
            rememberLastUsedCamera: true
        };

        scannerInstance = new Html5QrcodeScanner(
            "scannerViewport", 
            config,
            /* verbose= */ false
        );

        scannerInstance.render(onScanSuccess, onScanError);

        const placeholder = document.getElementById('cameraLoadingPlaceholder');
        if (placeholder) placeholder.style.display = 'none';
    } catch (e) {
        console.error("Camera init error:", e);
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
        badge.className = (res.action === 'check_out') ? 'badge badge-warning' : 'badge badge-success';
        badge.textContent = (res.action === 'check_out') ? 'PRESENSI KELUAR' : res.status.toUpperCase();
        
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

<?php include __DIR__ . '/includes/footer.php'; ?>
