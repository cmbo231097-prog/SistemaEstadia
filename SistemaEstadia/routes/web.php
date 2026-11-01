<?php

use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorpusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// La ruta raíz lleva a cada visitante a la pantalla que corresponde a su sesión.
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Estas rutas solo están disponibles para visitantes que aún no han iniciado sesión.
Route::middleware('guest')->group(function () {
    // Acceso con credenciales y límite de intentos para reducir envíos repetidos.
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    // Registro de cuenta, limitado para controlar solicitudes automatizadas.
    Route::get('/registro', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');

    // Solicitud y aplicación del proceso de restablecimiento de contraseña.
    Route::get('/recuperar-contrasena', [AuthController::class, 'showForgotForm'])->name('password.request');
    Route::post('/recuperar-contrasena', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/restablecer-contrasena', [AuthController::class, 'resetPassword'])->name('password.update');

    // Inicio de sesión federado: redirección al proveedor y retorno de su respuesta.
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// El middleware auth protege todas las funciones internas para usuarios autenticados.
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // FN.2 Gestión de usuarios (solo Líder de Proyecto)
    // Prefijo y nombre comunes para las operaciones administrativas sobre usuarios.
    Route::middleware('rol:lider')->prefix('admin/usuarios')->name('admin.usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::put('/{usuario}', [UsuarioController::class, 'update'])->name('update');
        Route::delete('/{usuario}', [UsuarioController::class, 'destroy'])->name('destroy');
    });

    // FN.3 Gestión del catálogo institucional de asignaturas (solo Líder de Proyecto)
    // Permite consultar, crear, actualizar y eliminar asignaturas del catálogo.
    Route::middleware('rol:lider')->prefix('admin/asignaturas')->name('admin.asignaturas.')->group(function () {
        Route::get('/', [AsignaturaController::class, 'index'])->name('index');
        Route::post('/', [AsignaturaController::class, 'store'])->name('store');
        Route::put('/{asignatura}', [AsignaturaController::class, 'update'])->name('update');
        Route::delete('/{asignatura}', [AsignaturaController::class, 'destroy'])->name('destroy');
    });

    // FN.4 Gestión de grupos (solo Líder de Proyecto)
    // Las operaciones del grupo quedan limitadas al rol de líder.
    Route::middleware('rol:lider')->prefix('admin/grupos')->name('admin.grupos.')->group(function () {
        Route::get('/', [GrupoController::class, 'index'])->name('index');
        Route::post('/', [GrupoController::class, 'store'])->name('store');
        Route::put('/{grupo}', [GrupoController::class, 'update'])->name('update');
        Route::delete('/{grupo}', [GrupoController::class, 'destroy'])->name('destroy');
    });

    // FN.6 Gestión de evidencias (Profesor sobre sus grupos; el Líder supervisa todas)
    // El alcance de los registros visibles depende del rol y de las reglas del controlador.
    Route::middleware('rol:profesor,lider')->prefix('evidencias')->name('evidencias.')->group(function () {
        Route::get('/', [EvidenciaController::class, 'index'])->name('index');
        Route::post('/', [EvidenciaController::class, 'store'])->name('store');
        Route::put('/{evidencia}', [EvidenciaController::class, 'update'])->name('update');
        Route::delete('/{evidencia}', [EvidenciaController::class, 'destroy'])->name('destroy');
    });

    // FN.5 Gestión de equipos (Profesor sobre sus grupos; el Líder supervisa todos)
    // Agrupa las acciones disponibles para administrar equipos de trabajo.
    Route::middleware('rol:profesor,lider')->prefix('equipos')->name('equipos.')->group(function () {
        Route::get('/', [EquipoController::class, 'index'])->name('index');
        Route::post('/', [EquipoController::class, 'store'])->name('store');
        Route::put('/{equipo}', [EquipoController::class, 'update'])->name('update');
        Route::delete('/{equipo}', [EquipoController::class, 'destroy'])->name('destroy');
    });

    // FN.7 Carga y sanitización de corpus (Profesor sobre sus grupos; el Líder supervisa)
    // Incluye carga, sanitización individual o por lote, consulta de PDF y eliminación.
    Route::middleware('rol:profesor,lider')->prefix('corpus')->name('corpus.')->group(function () {
        Route::get('/', [CorpusController::class, 'index'])->name('index');
        Route::post('/', [CorpusController::class, 'store'])->name('store');
        Route::post('/sanitizar', [CorpusController::class, 'sanitizarLote'])->name('sanitizar-lote');
        Route::post('/{documento}/sanitizar', [CorpusController::class, 'sanitizarUno'])->name('sanitizar');
        Route::get('/{documento}/pdf', [CorpusController::class, 'ver'])->name('ver');
        Route::delete('/{documento}', [CorpusController::class, 'destroy'])->name('destroy');
    });

    // FN.12 Respaldo y mantenimiento de base de datos (solo Líder de Proyecto)
    // Reúne creación, restauración, descarga y eliminación de respaldos.
    Route::middleware('rol:lider')->prefix('admin/respaldos')->name('respaldos.')->group(function () {
        Route::get('/', [RespaldoController::class, 'index'])->name('index');
        Route::post('/', [RespaldoController::class, 'store'])->name('store');
        Route::post('/restaurar-archivo', [RespaldoController::class, 'restaurarDesdeArchivo'])->name('restaurar-archivo');
        Route::post('/{respaldo}/restaurar', [RespaldoController::class, 'restaurarDesdeHistorial'])->name('restaurar');
        Route::get('/{respaldo}/descargar', [RespaldoController::class, 'descargar'])->name('descargar');
        Route::delete('/{respaldo}', [RespaldoController::class, 'destroy'])->name('destroy');
    });
});
