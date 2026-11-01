<style>
    .modal-overlay {
        position:fixed; inset:0; z-index:100;
        background:rgba(15,23,42,.55);
        display:none; align-items:center; justify-content:center; padding:16px;
    }
    .modal-overlay.open { display:flex; }
    .modal {
        background:#fff; border-radius:14px; width:100%; max-width:440px;
        padding:24px; box-shadow:0 20px 50px rgba(15,23,42,.35);
    }
    .modal h3 { margin:0 0 8px; font-size:18px; }
    .modal p { margin:0 0 22px; color:#475569; font-size:14px; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
    .modal-actions button { border:0; border-radius:8px; padding:10px 18px; font-size:13px; font-weight:700; }
    .modal-cancel { background:#e2e8f0; color:#334155; }
    .modal-ok { background:#1e3a6e; color:#fff; }
    .modal-ok:hover { background:#16294f; }

    .toast {
        position:fixed; right:20px; bottom:20px; z-index:110;
        background:#0f172a; color:#fff; padding:12px 18px; border-radius:10px;
        font-size:13px; box-shadow:0 10px 30px rgba(15,23,42,.35);
        opacity:0; transform:translateY(10px); pointer-events:none;
        transition:opacity .2s, transform .2s;
    }
    .toast.show { opacity:1; transform:translateY(0); }
</style>

<div class="modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
    <div class="modal">
        <h3 id="confirmTitle"></h3>
        <p id="confirmMessage"></p>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" id="confirmCancel">Cancelar</button>
            <button type="button" class="modal-ok" id="confirmOk">Confirmar</button>
        </div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
    (function () {
        const overlay = document.getElementById('confirmModal');
        const title = document.getElementById('confirmTitle');
        const message = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        const toast = document.getElementById('toast');
        let lastFocus = null;
        let onConfirm = null;
        let toastTimer = null;

        window.showToast = function (text) {
            toast.textContent = text;
            toast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
        };

        function close() {
            overlay.classList.remove('open');
            onConfirm = null;
            if (lastFocus) lastFocus.focus();
        }

        function open(trigger) {
            lastFocus = trigger;
            title.textContent = trigger.dataset.title || 'Confirmar acción';
            message.textContent = trigger.dataset.message || '¿Deseas continuar?';
            okBtn.textContent = trigger.dataset.okLabel || 'Confirmar';
            onConfirm = () => {
                const target = trigger.dataset.submit;
                if (target) {
                    document.querySelector(target).submit();
                } else {
                    showToast(trigger.dataset.success || 'Acción registrada. Funcionalidad disponible próximamente.');
                }
            };
            overlay.classList.add('open');
            okBtn.focus();
        }

        okBtn.addEventListener('click', () => { const fn = onConfirm; overlay.classList.remove('open'); if (fn) fn(); });
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('open')) close();
        });

        document.addEventListener('click', (e) => {
            const confirmTrigger = e.target.closest('[data-confirm]');
            if (confirmTrigger) { e.preventDefault(); open(confirmTrigger); return; }

            const soon = e.target.closest('[data-soon]');
            if (soon) { e.preventDefault(); showToast('«' + soon.dataset.soon + '» estará disponible próximamente.'); }
        });
    })();
</script>
