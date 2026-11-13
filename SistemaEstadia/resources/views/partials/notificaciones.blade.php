<style>
    .notif { position:relative; }
    .notif-btn {
        position:relative; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.4); color:#fff;
        border-radius:8px; width:40px; height:40px; font-size:18px; cursor:pointer; line-height:1;
    }
    .notif-btn:hover, .notif-btn[aria-expanded="true"] { background:rgba(255,255,255,.22); }
    .notif-badge {
        position:absolute; top:-6px; right:-6px; min-width:20px; height:20px; padding:0 5px; border-radius:999px;
        background:#dc2626; color:#fff; font-size:11px; font-weight:800; line-height:20px; text-align:center; border:2px solid var(--navy, #1e3a5f);
    }
    .notif-panel {
        position:absolute; right:0; top:calc(100% + 10px); z-index:60; width:min(380px, 92vw); max-height:70vh; overflow:auto;
        background:#fff; color:var(--text, #0f172a); border:1px solid var(--border, #dbe3ef); border-radius:12px; box-shadow:0 14px 34px rgba(15,23,42,.25);
    }
    .notif-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-bottom:1px solid var(--border, #dbe3ef); position:sticky; top:0; background:#fff; }
    .notif-head strong { font-size:14px; }
    .notif-link { background:none; border:0; color:var(--blue, #2563eb); font-size:12px; font-weight:700; cursor:pointer; padding:4px; }
    .notif-link:hover { text-decoration:underline; }
    .notif-list { list-style:none; margin:0; padding:0; }
    .notif-item { display:block; width:100%; text-align:left; background:#fff; border:0; border-bottom:1px solid var(--border, #dbe3ef); padding:12px 14px 12px 28px; cursor:pointer; position:relative; font:inherit; color:inherit; }
    .notif-item:hover, .notif-item:focus-visible { background:#f1f5f9; }
    .notif-item.nueva { background:#eff6ff; }
    .notif-item.nueva::before { content:''; position:absolute; left:11px; top:18px; width:8px; height:8px; border-radius:50%; background:var(--blue, #2563eb); }
    .notif-item b { display:block; font-size:13px; margin-bottom:2px; }
    .notif-item span { display:block; font-size:12.5px; color:#334155; }
    .notif-item small { display:block; margin-top:4px; font-size:11px; color:var(--muted, #64748b); }
    .notif-vacio { padding:22px 14px; text-align:center; font-size:12.5px; color:var(--muted, #64748b); }
    .notif-toasts { position:fixed; right:16px; bottom:16px; z-index:80; display:flex; flex-direction:column; gap:10px; width:min(360px, calc(100vw - 32px)); }
    .notif-toast {
        background:#fff; border:1px solid var(--border, #dbe3ef); border-left:5px solid var(--green, #16a34a); border-radius:10px; padding:12px 14px;
        box-shadow:0 10px 26px rgba(15,23,42,.25); cursor:pointer; text-align:left; font:inherit; color:var(--text, #0f172a);
    }
    .notif-toast.error { border-left-color:var(--red, #dc2626); }
    .notif-toast b { display:block; font-size:13px; margin-bottom:2px; }
    .notif-toast span { font-size:12.5px; color:#334155; }
    @media (max-width: 760px) { .notif-panel { position:fixed; left:8px; right:8px; top:calc(var(--header-h, 64px) + 6px); width:auto; } }
</style>

<div class="notif" id="notif">
    <button type="button" class="notif-btn" id="notifBtn" aria-label="Notificaciones" aria-haspopup="true" aria-expanded="false" aria-controls="notifPanel">
        🔔<span class="notif-badge" id="notifBadge" hidden>0</span>
    </button>
    <div class="notif-panel" id="notifPanel" role="region" aria-label="Notificaciones del sistema" hidden>
        <div class="notif-head">
            <strong>Notificaciones</strong>
            <span>
                <button type="button" class="notif-link" id="notifEscritorio" hidden>Activar avisos de escritorio</button>
                <button type="button" class="notif-link" id="notifLeerTodas">Marcar todas como leídas</button>
            </span>
        </div>
        <ul class="notif-list" id="notifLista"></ul>
        <p class="notif-vacio" id="notifVacio">No tienes notificaciones.</p>
    </div>
</div>

<div class="notif-toasts" id="notifToasts" role="status" aria-live="polite"></div>

<script>
(() => {
    const URL_LISTA = @json(route('notificaciones.index'));
    const URL_LEER = @json(route('notificaciones.leer'));
    const TOKEN = @json(csrf_token());
    const CLAVE = 'notif_ultimo_visto_' + @json((int) auth()->id());
    const btn = document.getElementById('notifBtn');
    const panel = document.getElementById('notifPanel');
    const badge = document.getElementById('notifBadge');
    const lista = document.getElementById('notifLista');
    const vacio = document.getElementById('notifVacio');
    const escritorio = document.getElementById('notifEscritorio');
    let ultimoVisto = parseInt(localStorage.getItem(CLAVE) || '0', 10);

    const marcar = (id) => fetch(URL_LEER, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
        body: JSON.stringify(id ? { id } : {}),
    }).then(consultar);

    const abrir = (n) => { (n.leido ? Promise.resolve() : marcar(n.id)).then(() => { if (n.url) window.location.href = n.url; }); };

    function mostrarToast(n) {
        const toast = document.createElement('button');
        toast.type = 'button';
        toast.className = 'notif-toast' + (/error|fallid/i.test(n.titulo) ? ' error' : '');
        const t = document.createElement('b'); t.textContent = n.titulo;
        const m = document.createElement('span'); m.textContent = n.mensaje;
        toast.append(t, m);
        toast.addEventListener('click', () => { toast.remove(); abrir(n); });
        document.getElementById('notifToasts').append(toast);
        setTimeout(() => toast.remove(), 10000);

        if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
            new Notification(n.titulo, { body: n.mensaje });
        }
    }

    function pintar(datos) {
        badge.hidden = datos.no_leidas === 0;
        badge.textContent = datos.no_leidas > 99 ? '99+' : String(datos.no_leidas);
        btn.setAttribute('aria-label', 'Notificaciones' + (datos.no_leidas ? ' (' + datos.no_leidas + ' sin leer)' : ''));
        lista.replaceChildren();
        vacio.hidden = datos.notificaciones.length > 0;
        datos.notificaciones.forEach((n) => {
            const li = document.createElement('li');
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'notif-item' + (n.leido ? '' : ' nueva');
            const t = document.createElement('b'); t.textContent = n.titulo;
            const m = document.createElement('span'); m.textContent = n.mensaje;
            const f = document.createElement('small'); f.textContent = n.fecha || '';
            item.append(t, m, f);
            item.addEventListener('click', () => abrir(n));
            li.append(item);
            lista.append(li);
        });
    }

    async function consultar() {
        try {
            const respuesta = await fetch(URL_LISTA, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!respuesta.ok) return;
            const datos = await respuesta.json();
            pintar(datos);

            // Se avisa una sola vez de lo que llegó desde la última revisión (también si ocurrió con otra pantalla abierta).
            const nuevas = datos.notificaciones.filter((n) => !n.leido && n.id > ultimoVisto).reverse();
            if (datos.notificaciones.length) {
                ultimoVisto = Math.max(ultimoVisto, datos.notificaciones[0].id);
                localStorage.setItem(CLAVE, String(ultimoVisto));
            }
            nuevas.slice(-3).forEach(mostrarToast);
        } catch (e) { /* sin conexión: se reintenta en el siguiente ciclo */ }
    }

    btn.addEventListener('click', () => {
        const abierto = panel.hidden;
        panel.hidden = !abierto;
        btn.setAttribute('aria-expanded', String(abierto));
        if (abierto) consultar();
    });
    document.addEventListener('click', (e) => {
        if (!panel.hidden && !document.getElementById('notif').contains(e.target)) { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    });
    document.getElementById('notifLeerTodas').addEventListener('click', () => marcar(null));

    if ('Notification' in window && Notification.permission === 'default') {
        escritorio.hidden = false;
        escritorio.addEventListener('click', () => Notification.requestPermission().then(() => { escritorio.hidden = true; }));
    }

    consultar();
    setInterval(() => { if (!document.hidden) consultar(); }, 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) consultar(); });
})();
</script>
