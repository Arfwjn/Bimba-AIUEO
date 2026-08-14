// assets/js/app.js

document.addEventListener('DOMContentLoaded', function() {
    initClock();
    initSidebarToggle();
});

// Live Server/Local Time Indicator
function initClock() {
    const clockEl = document.getElementById('liveClock');
    if (!clockEl) return;

    function update() {
        const now = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dayName = days[now.getDay()];
        const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        clockEl.textContent = `${dayName}, ${dateStr} - ${timeStr} WIB`;
    }

    update();
    setInterval(update, 1000);
}

// Sidebar Drawer for Mobile
function initSidebarToggle() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
}

// Modal Helpers
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Custom CRUD Confirmation Modal Handler (Replaces native browser confirm())
 */
function confirmAction(opts) {
    const modal = document.getElementById('globalConfirmModal');
    if (!modal) {
        if (confirm(opts.message || 'Lanjutkan aksi ini?')) {
            if (typeof opts.onConfirm === 'function') opts.onConfirm();
            else if (opts.onConfirm && opts.onConfirm.submit) opts.onConfirm.submit();
        }
        return false;
    }

    const titleEl = document.getElementById('confirmModalTitle');
    const msgEl = document.getElementById('confirmModalMessage');
    const iconEl = document.getElementById('confirmModalIcon');
    const submitBtn = document.getElementById('confirmModalSubmitBtn');

    if (titleEl) titleEl.textContent = opts.title || 'Konfirmasi Aksi';
    if (msgEl) msgEl.textContent = opts.message || 'Apakah Anda yakin ingin melanjutkan aksi ini?';
    
    if (iconEl) {
        iconEl.textContent = opts.icon || (opts.type === 'danger' ? 'delete_forever' : 'warning');
        iconEl.style.color = opts.type === 'danger' ? '#DC2626' : '#D97706';
    }

    if (submitBtn) {
        submitBtn.textContent = opts.btnText || (opts.type === 'danger' ? 'Ya, Hapus' : 'Ya, Lanjutkan');
        submitBtn.className = 'btn ' + (opts.btnClass || (opts.type === 'danger' ? 'btn-danger' : 'btn-primary'));
        
        // Clone button to replace event listener
        const newBtn = submitBtn.cloneNode(true);
        submitBtn.parentNode.replaceChild(newBtn, submitBtn);

        newBtn.addEventListener('click', function() {
            closeModal('globalConfirmModal');
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            } else if (opts.onConfirm && typeof opts.onConfirm.submit === 'function') {
                opts.onConfirm.submit();
            }
        });
    }

    openModal('globalConfirmModal');
    return false;
}

// Format Rupiah Input
function formatRupiahInput(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value) {
        input.value = new Intl.NumberFormat('id-ID').format(value);
    } else {
        input.value = '';
    }
}

// Simple Lightweight HTML5 Canvas Chart Renderer
function drawBarChart(canvasId, labels, dataValues, chartTitle = '', barColor = '#111111') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Resize for high DPI
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * 2;
    canvas.height = (rect.height || 220) * 2;
    ctx.scale(2, 2);
    
    const w = rect.width;
    const h = rect.height || 220;
    const padding = 40;
    
    ctx.clearRect(0, 0, w, h);
    
    if (!dataValues || dataValues.length === 0) {
        ctx.font = '14px "Work Sans"';
        ctx.fillStyle = '#666666';
        ctx.fillText('Belum ada data', w / 2 - 40, h / 2);
        return;
    }
    
    const maxVal = Math.max(...dataValues, 10);
    const chartW = w - padding * 2;
    const chartH = h - padding * 2;
    const barWidth = (chartW / dataValues.length) * 0.55;
    const step = chartW / dataValues.length;
    
    // Axes line
    ctx.strokeStyle = '#111111';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, h - padding);
    ctx.lineTo(w - padding / 2, h - padding);
    ctx.stroke();

    // Bars & Labels
    dataValues.forEach((val, i) => {
        const barH = (val / maxVal) * chartH;
        const x = padding + (i * step) + (step - barWidth) / 2;
        const y = h - padding - barH;

        ctx.fillStyle = barColor;
        ctx.fillRect(x, y, barWidth, barH);
        ctx.strokeStyle = '#111111';
        ctx.strokeRect(x, y, barWidth, barH);

        // Value text on top
        ctx.font = 'bold 11px "Work Sans"';
        ctx.fillStyle = '#111111';
        ctx.textAlign = 'center';
        ctx.fillText(val, x + barWidth / 2, y - 6);

        // Label below
        ctx.font = '11px "Work Sans"';
        ctx.fillStyle = '#666666';
        ctx.fillText(labels[i] || '', x + barWidth / 2, h - padding + 18);
    });
}
