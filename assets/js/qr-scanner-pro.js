/**
 * Fast QR Scanner Pro with Web Audio Beep & Auto-Scan Continuous Loop
 */

class FastQRScanner {
    constructor() {
        this.audioCtx = null;
    }

    // Play Audio Beep Notification
    playBeepSound() {
        try {
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, this.audioCtx.currentTime); // 880 Hz tone (A5)
            gain.gain.setValueAtTime(0.15, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.2);

            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.2);
        } catch (e) {
            console.log('Audio beep fallback:', e);
        }
    }

    // Flash Scanner Green Screen Effect
    flashSuccessVisual() {
        const viewport = document.getElementById('scannerViewport');
        if (!viewport) return;
        viewport.style.border = '4px solid #166534';
        viewport.style.boxShadow = '0 0 20px rgba(22, 101, 52, 0.6)';
        setTimeout(() => {
            viewport.style.border = '3px dashed var(--border-color)';
            viewport.style.boxShadow = 'none';
        }, 1200);
    }
}

const fastQR = new FastQRScanner();
