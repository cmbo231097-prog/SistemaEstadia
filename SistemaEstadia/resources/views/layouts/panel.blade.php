<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Panel') | UPEMOR Evaluación de Corpus con IA</title>
        <style>
            :root {
                --navy:#1e3a6e;
                --navy-dark:#16294f;
                --side:#111c33;
                --side-hover:#1b2a47;
                --accent:#38bdf8;
                --bg:#f1f5f9;
                --card:#ffffff;
                --border:#d9e1ec;
                --text:#0f172a;
                --muted:#64748b;
                --blue:#2f5fe0;
                --green:#3f9a6b;
                --orange:#d1802f;
                --indigo:#4f46e5;
                --red:#b91c1c;
                --ok-bg:#dcfce7;  --ok-fg:#15803d;
                --warn-bg:#fef3c7; --warn-fg:#b45309;
                --info-bg:#dbeafe; --info-fg:#1d4ed8;
                --bad-bg:#fee2e2; --bad-fg:#b91c1c;
                --idle-bg:#eef2f7; --idle-fg:#64748b;
                --header-h:64px;
                --side-w:250px;
            }

            * { box-sizing:border-box; }
            html, body { margin:0; }
            body {
                font-family:'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background:var(--bg);
                color:var(--text);
                min-height:100vh;
            }
            a { color:inherit; text-decoration:none; }
            button { font-family:inherit; cursor:pointer; }
            :focus-visible { outline:3px solid var(--accent); outline-offset:2px; }

            /* Flecha propia de los desplegables, siempre separada del texto y del borde derecho */
            select, select.field-control {
                -webkit-appearance:none; appearance:none;
                min-width:0; max-width:100%;
                padding-right:44px;
                text-overflow:ellipsis; white-space:nowrap; overflow:hidden;
                background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%23334155' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat:no-repeat;
                background-position:right 16px center;
                background-size:12px 8px;
            }

            /* Cabecera */
            .topbar {
                position:sticky; top:0; z-index:30;
                height:var(--header-h);
                background:var(--navy);
                color:#fff;
                display:flex; align-items:center; justify-content:space-between; gap:16px;
                padding:0 20px;
            }
            .topbar-left { display:flex; align-items:center; gap:12px; min-width:0; }
            .brand { font-size:15px; font-weight:700; line-height:1.25; }
            .brand span { font-weight:500; opacity:.92; }
            .menu-toggle {
                display:none;
                background:transparent; border:1px solid rgba(255,255,255,.4);
                color:#fff; border-radius:8px; width:40px; height:40px; font-size:20px;
            }
            .topbar-right { display:flex; align-items:center; gap:12px; flex-shrink:0; }
            .user-chip {
                background:rgba(255,255,255,.1);
                border-radius:8px; padding:8px 14px;
                font-size:13px; font-weight:600; white-space:nowrap;
            }
            .btn-logout {
                background:var(--red); color:#fff; border:0; border-radius:8px;
                padding:9px 20px; font-size:13px; font-weight:700;
            }
            .btn-logout:hover { background:#991b1b; }

            /* Estructura */
            .shell { display:flex; min-height:calc(100vh - var(--header-h)); }
            .sidebar {
                width:var(--side-w); flex-shrink:0;
                background:var(--side); color:#cbd5e1;
                padding:16px 10px 24px;
                position:sticky; top:var(--header-h);
                height:calc(100vh - var(--header-h));
                overflow-y:auto;
            }
            .nav-title {
                margin:18px 10px 8px;
                font-size:11px; font-weight:700; letter-spacing:.06em;
                text-transform:uppercase; color:#94a3b8;
            }
            .nav-title:first-child { margin-top:4px; }
            .nav-link {
                display:flex; align-items:center; gap:10px;
                padding:11px 12px; margin-bottom:2px;
                border-radius:8px; font-size:13.5px; color:#e2e8f0;
                border:1px solid transparent;
            }
            .nav-link:hover { background:var(--side-hover); }
            .nav-link.active {
                background:var(--side-hover);
                border-color:var(--accent);
                color:var(--accent); font-weight:700;
            }
            .nav-icon { width:20px; text-align:center; }
            .content { flex:1; min-width:0; padding:28px 32px 36px; }
            .backdrop { display:none; }

            /* Responsivo: sidebar tipo drawer */
            @media (max-width: 960px) {
                .menu-toggle { display:inline-flex; align-items:center; justify-content:center; }
                .sidebar {
                    position:fixed; left:0; top:var(--header-h); bottom:0; z-index:40;
                    transform:translateX(-100%); transition:transform .2s ease;
                }
                body.nav-open .sidebar { transform:translateX(0); }
                body.nav-open .backdrop {
                    display:block; position:fixed; inset:var(--header-h) 0 0 0;
                    background:rgba(15,23,42,.5); z-index:35;
                }
                .content { padding:20px 16px 28px; }
            }
            @media (max-width: 640px) {
                .brand span { display:none; }
                .user-chip { display:none; }
            }

            @include('partials.toggle-password-styles')

            /* Paginación compartida por todos los listados */
            .pager { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
            .pg { font-size:12px; color:var(--blue); font-weight:700; padding:2px 4px; text-decoration:none; }
            a.pg:hover { text-decoration:underline; }
            .pg.current { color:var(--text); }
            .pg.disabled, .pg.gap { color:#94a3b8; font-weight:400; }

            [data-autocierre] { transition:opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease; overflow:hidden; }
            [data-autocierre].cerrando { opacity:0; max-height:0; margin-top:0; margin-bottom:0; padding-top:0; padding-bottom:0; border-width:0; }
            @media (prefers-reduced-motion: reduce) { [data-autocierre] { transition:none; } }

            @yield('styles')
        </style>
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">☰</button>
                <div class="brand">UPEMOR <span>| Plataforma de Evaluación y Sanitización de Corpus Docente con IA</span></div>
            </div>
            <div class="topbar-right">
                @include('partials.notificaciones')
                <div class="user-chip">👤 {{ $user->nombre_completo }} [{{ $roleName }}]</div>
                <form method="POST" action="{{ route('logout') }}" id="logoutForm">
                    @csrf
                    <button type="button" class="btn-logout" data-confirm
                        data-title="Cerrar sesión"
                        data-message="¿Deseas cerrar tu sesión actual?"
                        data-submit="#logoutForm">🚪 Salir</button>
                </form>
            </div>
        </header>

        <div class="shell">
            <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
                @yield('sidebar')
            </aside>
            <div class="backdrop" id="backdrop"></div>

            <main class="content">
                @yield('content')
            </main>
        </div>

        @include('partials.confirm-modal')

        <script>
            (function () {
                const body = document.body;
                const toggle = document.getElementById('menuToggle');
                const closeNav = () => { body.classList.remove('nav-open'); toggle.setAttribute('aria-expanded', 'false'); };

                toggle.addEventListener('click', () => {
                    const open = body.classList.toggle('nav-open');
                    toggle.setAttribute('aria-expanded', String(open));
                });
                document.getElementById('backdrop').addEventListener('click', closeNav);
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeNav(); });
            })();
        </script>
        @include('partials.toggle-password-script')
        <script>
            // Si el navegador restaura la pantalla desde su memoria al usar «Atrás», se recarga con datos actuales.
            window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });
        </script>
        <script>
            // Los avisos de éxito (data-autocierre) desaparecen solos a los 3 segundos.
            document.querySelectorAll('[data-autocierre]').forEach((aviso) => {
                setTimeout(() => {
                    aviso.style.maxHeight = aviso.offsetHeight + 'px';
                    aviso.offsetHeight;
                    aviso.classList.add('cerrando');
                    setTimeout(() => aviso.remove(), 450);
                }, 3000);
            });
        </script>
        @yield('scripts')
    </body>
</html>
