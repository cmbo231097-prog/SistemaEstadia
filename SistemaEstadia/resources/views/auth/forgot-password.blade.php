@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('enviado'))
                <div class="icon-circle">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                </div>
                <h1>Revisa tu correo</h1>
                <p class="subtitle">Si no lo ves en unos minutos, revisa la carpeta de correo no deseado (Spam).</p>

                <div class="alert alert-success" role="status">{{ session('status') }}</div>

                <a href="{{ route('password.request') }}" class="btn btn-outline link">¿No te llegó? Intentar de nuevo</a>
            @else
                <div class="icon-circle">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d99a06" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/></svg>
                </div>
                <h1>¿Olvidaste tu contraseña?</h1>
                <p class="subtitle">Ingresa tu correo institucional registrado. Te enviaremos un enlace con un token de seguridad para restablecer tu contraseña.</p>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" novalidate>
                    @csrf

                    <div class="field">
                        <label class="lbl" for="email">Correo electrónico institucional *</label>
                        <div class="input-wrap has-icon">
                            <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="usuario@upemor.edu.mx" @class(['invalid' => $errors->has('email')]) required autofocus>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        ℹ️ El token generado expirará a los 15 minutos por seguridad. Revisa tu bandeja de entrada o correo no deseado (Spam).
                    </div>

                    <button type="submit" class="btn">Enviar Enlace de Recuperación</button>
                </form>
            @endif
        </div>

        <div class="card-footer">
            <a href="{{ route('login') }}" class="btn btn-outline link">← Regresar al Inicio de Sesión</a>
        </div>
    </div>
@endsection
