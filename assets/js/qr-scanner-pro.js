/**
 * Fast QR Scanner Pro Helper Class
 * 
 * Mengelola efek umpan balik audio (Web Audio API Beep Tone 880Hz) 
 * dan efek visual hijau kilat pada kotak scanner saat pemindaian berhasil.
 * 
 * @author Developer Team biMBA AIUEO
 */

class FastQRScanner {
    constructor() {
        /** @type {AudioContext|null} Context audio Web API */
        this.audioCtx = null;
    }

    /**
     * Memainkan Suara Beep Notifikasi Sukses Pemindaian (Web Audio API)
     * Nada 880 Hz (A5 Note) dengan durasi 0.2 detik tanpa membutuhkan file audio eksternal.
     * 
     * @returns {void}
     */
    playBeepSound() {
        try {
            // Inisialisasi AudioContext jika belum ada
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            // Resume AudioContext jika dalam keadaan suspended
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }

            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();

            // Set jenis gelombang Sine Wave & Frekuensi 880 Hz
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, this.audioCtx.currentTime);

            // Volume awal 0.15 dan meluruh lembut ke 0.001 dalam 0.2 detik
            gain.gain.setValueAtTime(0.15, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.2);

            // Sambungkan Oscillator -> Gain Node -> Audio Destination (Speaker)
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.2);
        } catch (e) {
            console.log('Web Audio Beep fallback handler:', e);
        }
    }

    /**
     * Menampilkan Efek Visual Kilat Hijau pada Kontainer Scanner
     * Memberikan indikasi visual yang jelas kepada penggunanya.
     * 
     * @returns {void}
     */
    flashSuccessVisual() {
        const viewport = document.getElementById('scannerViewport');
        if (!viewport) return;

        // Terapkan border hijau kilat dan efek pendar hijau
        viewport.style.border = '4px solid #166534';
        viewport.style.boxShadow = '0 0 20px rgba(22, 101, 52, 0.6)';

        // Kembalikan ke tampilan border semula setelah 1.2 detik
        setTimeout(() => {
            viewport.style.border = '3px dashed var(--border-color)';
            viewport.style.boxShadow = 'none';
        }, 1200);
    }
}

// Global Singleton Instance untuk Dipanggil pada Event Scan Sukses
const fastQR = new FastQRScanner();
