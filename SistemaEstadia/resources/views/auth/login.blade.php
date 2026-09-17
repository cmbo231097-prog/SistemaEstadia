<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login | Sistema de Estadia</title>
        <style>
            :root {
                --bg:#0f172a;
                --panel:#111827;
                --card:#f8fafc;
                --primary:#2563eb;
                --primary-dark:#1d4ed8;
                --text:#0f172a;
                --muted:#475569;
                --shadow: rgba(15, 23, 42, 0.15);
                --danger:#dc2626;
            }

            * { box-sizing: border-box; }
            body {
                margin:0;
                min-height:100vh;
                display:grid;
                place-items:center;
                background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                font-family: Arial, Helvetica, sans-serif;
                color:var(--text);
            }

            .login-shell {
                width:min(1100px, 92vw);
                display:grid;
                grid-template-columns: 1.2fr 0.8fr;
                background: rgba(255,255,255,0.08);
                border:1px solid rgba(255,255,255,0.15);
                border-radius:24px;
                overflow:hidden;
                box-shadow:0 20px 60px var(--shadow);
            }

            .info-panel {
                background:linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
                padding:48px 42px;
                color:#fff;
            }

            .info-panel h1 {
                font-size:clamp(2rem, 3vw, 3rem);
                margin:0 0 16px;
            }

            .info-panel p {
                margin:0 0 24px;
                line-height:1.7;
                color:rgba(255,255,255,0.9);
                max-width:520px;
            }

            .roles {
                display:grid;
                gap:12px;
                margin-top:28px;
            }

            .role-item {
                background: rgba(255,255,255,0.12);
                border:1px solid rgba(255,255,255,0.14);
                border-radius:12px;
                padding:14px 18px;
                font-weight:600;
            }

            .form-panel {
                background:var(--card);
                padding:42px 32px;
            }

            .form-card {
                max-width:380px;
                margin:0 auto;
            }

            .brand {
                font-size:0.8rem;
                letter-spacing:0.14em;
                text-transform:uppercase;
                color:var(--muted);
                font-weight:700;
                margin-bottom:10px;
            }

            h2 {
                margin:0 0 10px;
                font-size:2rem;
            }

            .subtext {
                color:var(--muted);
                margin-bottom:24px;
                line-height:1.5;
            }

            .field { margin-bottom:18px; }
            label {
                display:block;
                margin-bottom:8px;
                font-weight:700;
                color:var(--text);
            }

            input {
                width:100%;
                padding:14px 16px;
                border-radius:12px;
                border:1px solid #cbd5e1;
                font-size:1rem;
                transition:border-color 0.2s ease, box-shadow 0.2s ease;
            }

            input:focus {
                outline:none;
                border-color:var(--primary);
                box-shadow:0 0 0 3px rgba(37, 99, 235, 0.15);
            }

            .btn {
                width:100%;
                border:none;
                background:var(--primary);
                color:#fff;
                border-radius:12px;
                padding:15px 18px;
                font-size:1rem;
                font-weight:700;
                cursor:pointer;
                transition: background 0.2s ease;
            }

            .btn:hover {
                background:var(--primary-dark);
            }

            .remember-row {
                display:flex;
                align-items:center;
                justify-content:space-between;
                margin:16px 0 22px;
                font-size:0.92rem;
                color:var(--muted);
            }

            .remember-row label {
                margin:0;
                display:flex;
                align-items:center;
                gap:8px;
                font-weight:500;
            }

            input[type="checkbox"] {
                width:auto;
                accent-color:var(--primary);
            }

            .error {
                background: rgba(220, 38, 38, 0.08);
                border:1px solid rgba(220, 38, 38, 0.25);
                color: var(--danger);
                border-radius:10px;
                padding:10px 12px;
                margin-bottom:18px;
                font-size:0.92rem;
            }

            .demo-box {
                margin-top:24px;
                border-top:1px solid #e2e8f0;
                padding-top:18px;
                color:var(--muted);
                font-size:0.92rem;
                line-height:1.6;
            }

            .demo-box strong {
                color:var(--text);
            }

            @media (max-width: 800px) {
                .login-shell {
                    grid-template-columns: 1fr;
                }

                .info-panel,
                .form-panel {
                    padding:30px 24px;
                }
            }
        </style>
    </head>
    <body>
        <main class="login-shell">
            <section class="info-panel">
                <div class="brand">Sistema Estadia</div>
                <h1>Plataforma de gestión académica</h1>
                <p>Inicia sesión para acceder al panel correspondiente según tu perfil dentro del sistema.</p>

                <div class="roles">
                    <div class="role-item">Líder de proyecto</div>
                    <div class="role-item">Profesor</div>
                    <div class="role-item">Estudiante</div>
                </div>
            </section>

            <section class="form-panel">
                <div class="form-card">
                    <div class="brand">Acceso</div>
                    <h2>Iniciar sesión</h2>
                    <div class="subtext">Ingresa tus credenciales para continuar.</div>

                    @if ($errors->any())
                        <div class="error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.submit') }}">
                        @csrf

                        <div class="field">
                            <label for="email">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                        </div>

                        <div class="field">
                            <label for="password">Contraseña</label>
                            <input id="password" type="password" name="password" required>
                        </div>

                        <div class="remember-row">
                            <label>
                                <input type="checkbox" name="remember" value="1">
                                Recordarme
                            </label>
                        </div>

                        <button type="submit" class="btn">Ingresar</button>
                    </form>

                    <div class="demo-box">
                        <strong>Usuarios de prueba:</strong><br>
                        Lider: lider@estadia.com / 12345678<br>
                        Profesor: profesor@estadia.com / 12345678<br>
                        Estudiante: estudiante@estadia.com / 12345678
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
