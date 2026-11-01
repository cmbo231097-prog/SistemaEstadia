@extends('layouts.auth')

{{-- Define el título que utiliza la plantilla compartida de autenticación. --}}
@section('title', 'Iniciar sesión')

@section('content')
    {{-- Tarjeta principal que agrupa el acceso y las alternativas de autenticación. --}}
    <div class="card">
        <div class="card-body">
            <div class="icon-circle">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/></svg>
            </div>
            <h1>Iniciar Sesión</h1>
            <p class="subtitle">Ingrese sus credenciales registradas para acceder a su rol</p>

            Muestra confirmaciones de la sesión, como el resultado de una acción previa. --}}
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            {{-- Presenta el primer error de validación o autenticación recibido por Laravel. --}}
            @if ($errors->any())
                <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            {{-- Formulario de acceso; CSRF protege el envío y la ruta dirige al método de autenticación. --}}
            <form method="POST" action="{{ route('login.submit') }}" novalidate>
                @csrf

                {{-- Campo de correo: conserva el valor previo o el usuario recordado. --}}
                <div class="field">
                    <label class="lbl" for="email">Correo institucional *</label>
                    <div class="input-wrap has-icon">
                        <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input id="email" type="email" name="email" value="{{ old('email', $rememberedEmail ?? '') }}" placeholder="usuario@upemor.edu.mx" autocomplete="username" required autofocus>
                    </div>
                </div>

                {{-- Campo de contraseña con botón para alternar su visibilidad. --}}
                <div class="field">
                    <label class="lbl" for="password">Contraseña *</label>
                    <div class="input-wrap has-icon has-toggle">
                        <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password" type="password" name="password" placeholder="••••••••••••" autocomplete="current-password" required>
                        <button type="button" class="toggle-pass" data-target="password" aria-label="Mostrar contraseña">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Opción para recordar el usuario y acceso al flujo de recuperación. --}}
                <div class="row-between">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember', ! empty($rememberedEmail)))>
                        Recordar mi usuario
                    </label>
                    <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                </div>

                {{-- Envía las credenciales para iniciar sesión. --}}
                <button type="submit" class="btn">Ingresar al Sistema</button>
            </form>

            {{-- Alternativa de autenticación mediante la cuenta institucional de Google. --}}
            <div class="divider">o continúa con</div>

            <a href="{{ route('auth.google') }}" class="btn btn-outline btn-google">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.9 2.4 30.4 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.2C12.4 13.6 17.7 9.5 24 9.5Z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5Z"/><path fill="#FBBC05" d="M10.5 28.6a14.5 14.5 0 0 1 0-9.2l-7.9-6.2a24 24 0 0 0 0 21.6l7.9-6.2Z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.9l-7.9 6.2C6.5 42.6 14.6 48 24 48Z"/></svg>
                Iniciar sesión con Google Institucional
            </a>
        </div>

        {{-- Enlace para quienes todavía necesitan crear una cuenta. --}}
        <div class="card-footer">
            ¿Aún no tienes una cuenta? <a href="{{ route('register') }}">Regístrate aquí</a>
        </div>
    </div>
@endsection
