@extends('layouts.auth')

@section('title', 'Restablecer contraseña')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="icon-circle">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d99a06" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/></svg>
            </div>
            <h1>Nueva contraseña</h1>
            <p class="subtitle">Define una nueva contraseña para tu cuenta institucional.</p>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label class="lbl" for="email">Correo electrónico institucional *</label>
                    <div class="input-wrap has-icon">
                        <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" placeholder="usuario@upemor.edu.mx" readonly required>
                    </div>
                </div>

                <div class="field">
                    <label class="lbl" for="password">Nueva contraseña *</label>
                    <div class="input-wrap has-icon has-toggle">
                        <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password" type="password" name="password" autocomplete="new-password" required>
                        @include('partials.toggle-password', ['target' => 'password'])
                    </div>
                </div>

                <div class="field">
                    <label class="lbl" for="password_confirmation">Confirmar contraseña *</label>
                    <div class="input-wrap has-icon has-toggle">
                        <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                        @include('partials.toggle-password', ['target' => 'password_confirmation'])
                    </div>
                </div>
                <p class="hint">* Mínimo 8 caracteres, combinando mayúsculas, números y símbolos.</p>

                <button type="submit" class="btn">Restablecer Contraseña</button>
            </form>
        </div>

        <div class="card-footer">
            <a href="{{ route('login') }}" class="btn btn-outline link">← Regresar al Inicio de Sesión</a>
        </div>
    </div>
@endsection
