<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('title') | UPEMOR Evaluador de Corpus</title>
        <style>
            :root {
                --navy:#1c3d6e;
                --navy-dark:#16305a;
                --blue:#2563eb;
                --text:#0f172a;
                --muted:#64748b;
                --field-border:#8fa3c4;
                --danger:#dc2626;
                --success:#15803d;
            }

            * { box-sizing:border-box; }

            body {
                margin:0;
                min-height:100vh;
                display:flex;
                flex-direction:column;
                font-family:"Segoe UI", Arial, Helvetica, sans-serif;
                color:var(--text);
                background:linear-gradient(180deg, #eef2f9 0%, #f8fafc 100%);
            }

            .topbar {
                background:var(--navy);
                color:#fff;
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:16px;
                padding:18px 28px;
            }

            .topbar-title { font-weight:700; font-size:0.95rem; }

            .topbar-badge {
                background:var(--blue);
                color:#fff;
                border-radius:999px;
                padding:8px 20px;
                font-size:0.78rem;
                font-weight:700;
                white-space:nowrap;
            }

            main {
                flex:1;
                display:flex;
                justify-content:center;
                align-items:flex-start;
                padding:40px 16px;
            }

            .card {
                width:100%;
                max-width:380px;
                background:#fff;
                border:1px solid #cfd9ea;
                border-radius:28px;
                box-shadow:0 10px 30px rgba(15, 23, 42, 0.18);
                overflow:hidden;
            }

            .card.wide { max-width:640px; }

            .card-body { padding:28px 32px 32px; }
            .card.wide .card-body { padding:22px 34px 28px; }

            .card-footer {
                background:#f8fafc;
                border-top:1px solid #e2e8f0;
                padding:22px 32px;
                text-align:center;
                font-size:0.78rem;
                color:#475569;
            }

            .card-footer a { font-weight:700; }

            .icon-circle {
                width:48px;
                height:48px;
                margin:0 auto 10px;
                border-radius:50%;
                background:#eef2f9;
                border:1px solid #cfd9ea;
                display:grid;
                place-items:center;
                color:#64748b;
            }

            h1 {
                margin:0 0 6px;
                text-align:center;
                font-size:1.35rem;
                font-weight:800;
                color:var(--text);
            }

            .subtitle {
                margin:0 0 22px;
                text-align:center;
                font-size:0.78rem;
                color:var(--muted);
                line-height:1.5;
            }

            .field { margin-bottom:16px; }

            label.lbl {
                display:block;
                margin-bottom:6px;
                font-size:0.76rem;
                font-weight:700;
                color:#1e293b;
            }

            .input-wrap { position:relative; }

            .input-wrap svg.leading {
                position:absolute;
                left:12px;
                top:50%;
                transform:translateY(-50%);
                width:15px;
                height:15px;
                color:#94a3b8;
                pointer-events:none;
            }

            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="tel"] {
                width:100%;
                height:44px;
                padding:0 14px;
                font-size:0.85rem;
                color:var(--text);
                background:#fff;
                border:1px solid var(--field-border);
                border-radius:8px;
                font-family:inherit;
                transition:border-color .15s, box-shadow .15s;
            }

            .has-icon input { padding-left:36px; }
            .has-toggle input { padding-right:42px; }

            input::placeholder { color:#a3b0c4; }

            input:focus {
                outline:none;
                border-color:var(--blue);
                box-shadow:0 0 0 3px rgba(37, 99, 235, 0.15);
            }

            input.invalid { border-color:var(--danger); }

            .toggle-pass {
                position:absolute;
                right:8px;
                top:50%;
                transform:translateY(-50%);
                background:none;
                border:none;
                cursor:pointer;
                padding:6px;
                color:#64748b;
                display:grid;
                place-items:center;
            }

            .toggle-pass svg { width:18px; height:18px; }

            .btn {
                display:block;
                width:100%;
                height:44px;
                border:none;
                border-radius:8px;
                background:var(--navy);
                color:#fff;
                font-size:0.85rem;
                font-weight:700;
                font-family:inherit;
                cursor:pointer;
                text-align:center;
                text-decoration:none;
                line-height:44px;
                transition:background .15s;
            }

            .btn:hover { background:var(--navy-dark); }

            .btn-outline {
                background:#fff;
                color:#1e293b;
                border:1px solid #cfd9ea;
                line-height:42px;
            }

            .btn-outline:hover { background:#f8fafc; }

            .btn-outline.link { color:var(--blue); }

            .row-between {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:8px;
                margin:4px 0 18px;
                font-size:0.72rem;
            }

            .check {
                display:flex;
                align-items:center;
                gap:6px;
                color:#334155;
                font-size:0.72rem;
            }

            .check input { accent-color:var(--navy); margin:0; }

            a { color:var(--blue); }

            .divider {
                display:flex;
                align-items:center;
                gap:12px;
                margin:18px 0;
                color:#94a3b8;
                font-size:0.72rem;
            }

            .divider::before, .divider::after {
                content:"";
                flex:1;
                height:1px;
                background:#e2e8f0;
            }

            .btn-google svg { width:16px; height:16px; vertical-align:-3px; margin-right:8px; }

            .alert {
                border-radius:8px;
                padding:10px 12px;
                margin-bottom:16px;
                font-size:0.78rem;
                line-height:1.5;
            }

            .alert-error {
                background:rgba(220, 38, 38, 0.08);
                border:1px solid rgba(220, 38, 38, 0.3);
                color:var(--danger);
            }

            .alert-success {
                background:rgba(21, 128, 61, 0.08);
                border:1px solid rgba(21, 128, 61, 0.3);
                color:var(--success);
            }

            .alert-info {
                background:#eef5ff;
                border:1px solid #bfd6f7;
                color:#1e40af;
            }

            .field-error {
                margin-top:4px;
                font-size:0.7rem;
                color:var(--danger);
            }

            .grid-2 {
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:0 20px;
            }

            .hint { font-size:0.7rem; color:var(--muted); margin:-6px 0 14px; }

            @media (max-width:640px) {
                .grid-2 { grid-template-columns:1fr; }
                .topbar { padding:14px 16px; }
                .topbar-title { font-size:0.8rem; }
                .card-body, .card.wide .card-body { padding:24px 20px; }
            }
        </style>
    </head>
    <body>
        <header class="topbar">
            <span class="topbar-title">UPEMOR | Plataforma de Evaluación y Sanitización de Corpus Docente con IA</span>
            <span class="topbar-badge">Control de Acceso</span>
        </header>

        <main>
            @yield('content')
        </main>

        <script>
            document.querySelectorAll('.toggle-pass').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.dataset.target);
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
                });
            });
        </script>
    </body>
</html>
