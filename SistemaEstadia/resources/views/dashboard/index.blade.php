<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard | {{ $roleName }}</title>
        <style>
            :root {
                --bg:#f8fafc;
                --card:#ffffff;
                --primary:#2563eb;
                --primary-soft:#dbeafe;
                --success:#16a34a;
                --warning:#f59e0b;
                --text:#0f172a;
                --muted:#475569;
                --border:#e2e8f0;
            }

            * { box-sizing: border-box; }
            body {
                margin:0;
                font-family: Arial, Helvetica, sans-serif;
                background: var(--bg);
                color: var(--text);
            }

            .container {
                max-width:1100px;
                margin:40px auto;
                padding:0 20px;
            }

            .topbar {
                display:flex;
                justify-content:space-between;
                align-items:center;
                background: var(--card);
                border:1px solid var(--border);
                border-radius:18px;
                padding:18px 22px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            }

            .greeting h1 {
                margin:0;
                font-size:2rem;
            }

            .greeting p {
                margin:6px 0 0;
                color: var(--muted);
            }

            .logout-btn {
                border:none;
                background:#0f172a;
                color:#fff;
                border-radius:10px;
                padding:12px 18px;
                font-weight:700;
                cursor:pointer;
            }

            .content {
                margin-top:28px;
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap:20px;
            }

            .card {
                background:var(--card);
                border:1px solid var(--border);
                border-radius:18px;
                padding:22px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            }

            .pill {
                display:inline-block;
                padding:8px 12px;
                border-radius:999px;
                font-size:0.8rem;
                font-weight:700;
                background: var(--primary-soft);
                color: var(--primary);
                margin-bottom:18px;
            }

            .stats {
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap:16px;
                margin-top:20px;
            }

            .stat {
                background: #f8fafc;
                border:1px solid var(--border);
                border-radius:14px;
                padding:18px;
            }

            .stat .label {
                font-size:0.85rem;
                color: var(--muted);
                margin-bottom:8px;
            }

            .stat .value {
                font-size:1.7rem;
                font-weight:700;
            }

            ul {
                margin:14px 0 0;
                padding-left:18px;
                color: var(--muted);
                line-height:1.8;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <header class="topbar">
                <div class="greeting">
                    <h1>Bienvenido, {{ Auth::user()->name }}</h1>
                    <p>Estás ingresando como {{ $roleName }}.</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">Cerrar sesión</button>
                </form>
            </header>

            <section class="content">
                @switch(Auth::user()->role)
                    @case('lider')
                        <div class="card" style="grid-column: 1 / -1;">
                            <span class="pill">Dashboard: Líder de proyecto</span>
                            <h2>Resumen general del proyecto</h2>
                            <p>Administra avances, tareas y coordinación del equipo.</p>
                        </div>
                        @break

                    @case('profesor')
                        <div class="card" style="grid-column: 1 / -1;">
                            <span class="pill">Dashboard: Profesor</span>
                            <h2>Seguimiento académico</h2>
                            <p>Revisa estudiantes, actividades y resultados del curso.</p>
                        </div>
                        @break

                    @default
                        <div class="card" style="grid-column: 1 / -1;">
                            <span class="pill">Dashboard: Estudiante</span>
                            <h2>Mi progreso académico</h2>
                            <p>Consulta tus tareas, entregas y actividades pendientes.</p>
                        </div>
                @endswitch

                <div class="card">
                    <h3>Acciones rápidas</h3>
                    <ul>
                        @switch(Auth::user()->role)
                            @case('lider')
                                <li>Revisar entregas del equipo</li>
                                <li>Actualizar cronograma</li>
                                <li>Coordinar reuniones</li>
                                @break
                            @case('profesor')
                                <li>Calificar actividades</li>
                                <li>Publicar avisos</li>
                                <li>Consultar reportes de clase</li>
                                @break
                            @default
                                <li>Ver tareas pendientes</li>
                                <li>Entregar actividades</li>
                                <li>Ver mi historial</li>
                        @endswitch
                    </ul>
                </div>

                <div class="card">
                    <h3>Resumen</h3>
                    <div class="stats">
                        <div class="stat">
                            <div class="label">Actividades</div>
                            <div class="value">24</div>
                        </div>
                        <div class="stat">
                            <div class="label">Pendientes</div>
                            <div class="value">8</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
