@extends('layouts.auth')

@section('title', 'Crear cuenta')

@section('content')
    @php
        $mailIcon = '<svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
        $lockIcon = '<svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
        $idIcon = '<svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M14 10h4M14 14h4M6 16c.6-1.4 1.6-2 3-2s2.4.6 3 2"/></svg>';
    @endphp

    <div class="card wide">
        <div class="card-body">
            <h1>Crear Cuenta de Usuario</h1>
            <p class="subtitle">Completa el formulario para acceder al sistema y consultar tus evaluaciones</p>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">{{ $errors->first('registro') ?: 'Revisa los campos marcados e inténtalo de nuevo.' }}</div>
            @endif

            <form method="POST" action="{{ route('register.submit') }}" novalidate>
                @csrf

                <div class="grid-2">
                    <div class="field">
                        <label class="lbl" for="nombres">Nombre(s) *</label>
                        <input id="nombres" type="text" name="nombres" value="{{ old('nombres') }}" placeholder="Ej. Braulio Yair" @class(['invalid' => $errors->has('nombres')]) required autofocus>
                        @error('nombres')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label class="lbl" for="apellidos">Apellidos *</label>
                        <input id="apellidos" type="text" name="apellidos" value="{{ old('apellidos') }}" placeholder="Ej. Cuevas Mendoza" @class(['invalid' => $errors->has('apellidos')]) required>
                        @error('apellidos')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="field">
                    <label class="lbl" for="matricula">Matrícula escolar *</label>
                    <div class="input-wrap has-icon">
                        {!! $idIcon !!}
                        <input id="matricula" type="text" name="matricula" value="{{ old('matricula') }}" placeholder="Ej. ITI2023001" @class(['invalid' => $errors->has('matricula')]) required>
                    </div>
                    @error('matricula')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label class="lbl" for="email">Correo electrónico institucional *</label>
                    <div class="input-wrap has-icon">
                        {!! $mailIcon !!}
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="usuario@upemor.edu.mx" autocomplete="username" @class(['invalid' => $errors->has('email')]) required>
                    </div>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="lbl" for="password">Contraseña *</label>
                        <div class="input-wrap has-icon has-toggle">
                            {!! $lockIcon !!}
                            <input id="password" type="password" name="password" placeholder="••••••••••••" autocomplete="new-password" @class(['invalid' => $errors->has('password')]) required>
                            @include('partials.toggle-password', ['target' => 'password'])
                        </div>
                        @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label class="lbl" for="password_confirmation">Confirmar contraseña *</label>
                        <div class="input-wrap has-icon has-toggle">
                            {!! $lockIcon !!}
                            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="••••••••••••" autocomplete="new-password" required>
                            @include('partials.toggle-password', ['target' => 'password_confirmation'])
                        </div>
                    </div>
                </div>
                <p class="hint">* Mínimo 8 caracteres, combinando mayúsculas, números y símbolos.</p>

                <label class="check" style="margin-bottom:16px;">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    Acepto los términos de uso y el Aviso de Privacidad Institucional de evidencias
                </label>
                @error('terms')<div class="field-error" style="margin-top:-10px;margin-bottom:12px;">{{ $message }}</div>@enderror

                <button type="submit" class="btn">Registrar Cuenta</button>
            </form>
        </div>

        <div class="card-footer">
            ¿Ya tienes una cuenta registrada? <a href="{{ route('login') }}">Inicia sesión aquí</a>
        </div>
    </div>
@endsection
