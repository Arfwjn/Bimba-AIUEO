<?php
/**
 * Komponen Core Footer & Reusable Modal Container biMBA AIUEO
 * 
 * Menyediakan penutup HTML body, kontainer modal konfirmasi global,
 * serta pemanggilan pustaka script JavaScript (qrcode.js & app.js).
 * 
 * @package     biMBA_AIUEO
 * @subpackage  Templates
 * @author      Developer Team biMBA AIUEO
 */
?>
        </main>
    </div>
</div>

<!-- Global Reusable CRUD Confirmation Modal (Replaces Browser Native Confirm/Alert) -->
<div id="globalConfirmModal" class="modal-backdrop">
    <div class="modal-content" style="max-width: 420px; text-align: center; padding: 28px 24px;">
        <div id="confirmModalIconBg" style="width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; background-color: var(--surface-muted);">
            <span class="material-symbols-outlined" id="confirmModalIcon" style="font-size: 36px; color: var(--status-warning-text);">help</span>
        </div>
        <h3 id="confirmModalTitle" style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Konfirmasi Aksi</h3>
        <p id="confirmModalMessage" style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px; line-height: 1.5;">Apakah Anda yakin ingin melanjutkan aksi ini?</p>

        <div style="display: flex; gap: 10px; justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('globalConfirmModal')" style="min-width: 100px;">Batal</button>
            <button type="button" class="btn btn-primary" id="confirmModalSubmitBtn" style="min-width: 120px;">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<!-- Global Scripts -->
<script src="assets/js/qrcode.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
