    .page-title { margin:0; font-size:22px; font-weight:800; }
    .page-sub { margin:4px 0 22px; color:var(--muted); font-size:13.5px; }

    .panel { background:var(--card); border:1px solid var(--border); border-radius:12px; }

    /* KPIs */
    .kpis { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:18px; }
    .kpi { padding:16px 18px; }
    .kpi-label { font-size:12.5px; color:var(--muted); }
    .kpi-value { margin:6px 0 8px; font-size:28px; font-weight:800; line-height:1.1; }
    .kpi-note { font-size:12px; font-weight:700; }
    .kpi.alert { border-color:#f1c27d; background:#fffaf0; }
    .c-navy { color:var(--navy); } .c-blue { color:var(--blue); } .c-green { color:#2f9e6e; } .c-purple { color:#7c3aed; }
    .c-warn { color:#d97706; } .c-muted { color:var(--muted); }

    /* Acciones */
    .actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; padding:16px 20px; margin-bottom:26px; }
    .actions-label { font-size:13px; font-weight:700; margin-right:6px; }
    .btn {
        border:0; border-radius:8px; padding:12px 14px;
        color:#fff; font-size:13px; font-weight:700;
        flex:1 1 150px; max-width:240px;
        transition:filter .15s;
    }
    .btn:hover { filter:brightness(1.1); }
    .btn-navy { background:var(--navy); }
    .btn-blue { background:var(--blue); }
    .btn-green { background:var(--green); }
    .btn-orange { background:var(--orange); }
    .btn-indigo { background:var(--indigo); }
    .btn:focus-visible, .btn-outline:focus-visible { outline:3px solid #93b4ff; outline-offset:2px; }
    .btn:disabled { opacity:.5; cursor:not-allowed; filter:none; }
    /* Botón secundario: fondo blanco, texto y borde oscuros para que siempre sea legible. */
    .btn-outline {
        display:inline-flex; align-items:center; justify-content:center; gap:6px;
        background:#fff; color:var(--navy); border:1.5px solid var(--navy); border-radius:8px;
        padding:11px 16px; font-size:13px; font-weight:700; text-decoration:none; cursor:pointer;
        flex:0 0 auto; max-width:none; transition:background .15s;
    }
    .btn-outline:hover { background:var(--info-bg); filter:none; }
    .btn-outline-danger { color:var(--bad-fg); border-color:var(--bad-fg); }
    .btn-outline-danger:hover { background:var(--bad-bg); }

    /* Tabla */
    .section-title { margin:0 0 12px; font-size:16px; font-weight:800; }
    .table-wrap { overflow-x:auto; margin-bottom:26px; border:1px solid var(--border); border-radius:12px; background:var(--card); }
    table { width:100%; border-collapse:collapse; min-width:760px; }
    th {
        background:#e2e8f0; text-align:left; padding:12px 14px;
        font-size:12.5px; font-weight:700; border-bottom:1px solid var(--border);
    }
    td { padding:14px; font-size:12.5px; border-bottom:1px solid var(--border); vertical-align:middle; }
    tbody tr:last-child td { border-bottom:0; }
    tbody tr:hover { background:#f8fafc; }
    th.center, td.center { text-align:center; }
    .ev-name { font-weight:600; }
    .ev-sub { display:block; margin-top:2px; font-weight:400; color:var(--muted); font-size:11.5px; }

    .badge {
        display:inline-block; padding:6px 14px; border-radius:999px;
        font-size:11.5px; font-weight:700; white-space:nowrap;
    }
    .b-ok { background:var(--ok-bg); color:var(--ok-fg); }
    .b-warn { background:var(--warn-bg); color:var(--warn-fg); }
    .b-info { background:var(--info-bg); color:var(--info-fg); }
    .b-bad { background:var(--bad-bg); color:var(--bad-fg); }
    .b-idle { background:var(--idle-bg); color:var(--idle-fg); font-weight:600; }

    .row-btn {
        border:0; border-radius:6px; padding:9px 18px; min-width:130px;
        font-size:12px; font-weight:700; color:#fff;
    }
    .row-btn.navy { background:var(--navy); }
    .row-btn.blue { background:var(--blue); }
    .row-btn.indigo { background:var(--indigo); }
    .row-btn.ghost { background:#e2e8f0; color:#475569; }
    .row-btn:hover { filter:brightness(1.1); }

    .empty { padding:28px; text-align:center; color:var(--muted); font-size:13px; }

    /* Barra de estado */
    .infra {
        display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px 24px;
        padding:16px 22px; font-size:12.5px;
    }
    .infra.center { justify-content:center; }
    .infra-item { display:flex; align-items:center; gap:8px; }
    .infra-ok { color:#15803d; font-weight:700; }
    .infra-warn { color:#b45309; font-weight:700; }
    .infra-notif { color:var(--blue); font-weight:700; background:none; border:0; padding:0; font-size:12.5px; }
    .infra-notif:hover { text-decoration:underline; }
    .dot { width:9px; height:9px; border-radius:50%; background:#22c55e; display:inline-block; }
    .dot.off { background:#ef4444; }

    @media (max-width: 1100px) {
        .kpis { grid-template-columns:repeat(2, 1fr); }
    }
    @media (max-width: 560px) {
        .kpis { grid-template-columns:1fr; }
        .btn { width:100%; max-width:none; }
        .actions-label { width:100%; }
        .infra { flex-direction:column; }
    }
