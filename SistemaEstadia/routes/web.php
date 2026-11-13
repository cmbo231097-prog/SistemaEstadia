<?php

use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorpusController;
use App\Http\Controllers\ContrasteIndividualController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EvaluacionDocenteController;
use App\Http\Controllers\EvaluacionIaController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\MotorComparativoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RendimientoGrupalController;
use App\Http\Controllers\ReporteDiscrepanciaController;
use App\Http\Controllers\ReporteTiemposController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\RubricaController;
use App\Http\Controllers\RubricaImportController;
use App\Http\Controllers\SolicitudProfesorController;
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

    // El estudiante solicita el rol de profesor; el Líder recibe el aviso por correo.
    Route::post('/solicitar-profesor', [SolicitudProfesorController::class, 'store'])->middleware('rol:estudiante')->name('solicitud-profesor.store');

    // Notificaciones del sistema (campana del encabezado).
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');

    // FN.2 Gestión de usuarios (solo Líder de Proyecto)
    // Prefijo y nombre comunes para las operaciones administrativas sobre usuarios.
    Route::middleware('rol:lider')->prefix('admin/usuarios')->name('admin.usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::put('/{usuario}', [UsuarioController::class, 'update'])->name('update');
        Route::patch('/{usuario}/estatus', [UsuarioController::class, 'cambiarEstatus'])->name('estatus');
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
        // Lee el archivo de rúbricas desde el formulario de evidencias para precargar sus criterios.
        Route::post('/rubrica/leer', [EvidenciaController::class, 'leerRubrica'])->name('rubrica.leer');
        Route::get('/{evidencia}/rubrica', [RubricaController::class, 'index'])->name('rubrica');
        Route::put('/{evidencia}/rubrica', [RubricaController::class, 'update'])->name('rubrica.update');
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
        Route::post('/evaluar', [CorpusController::class, 'evaluar'])->name('evaluar');
        Route::post('/{documento}/sanitizar', [CorpusController::class, 'sanitizarUno'])->name('sanitizar');
        Route::get('/{documento}/pdf', [CorpusController::class, 'ver'])->name('ver');
        Route::delete('/{documento}', [CorpusController::class, 'destroy'])->name('destroy');
    });

    // Evaluación con IA: evalúa un PDF (o un lote, uno por uno) con Gemini y guarda el resultado en la base de datos.
    Route::middleware('rol:profesor,lider')->prefix('evaluacion-ia')->name('evaluacion-ia.')->group(function () {
        Route::get('/', [EvaluacionIaController::class, 'index'])->name('index');
        Route::post('/lote', [EvaluacionIaController::class, 'evaluarLote'])->name('lote');
        Route::get('/lote/{lote}', [EvaluacionIaController::class, 'estadoLote'])->name('lote.estado');
        Route::post('/{documento}', [EvaluacionIaController::class, 'evaluar'])->name('evaluar');
    });

    // Reportes gerenciales (solo Líder de Proyecto).
    Route::middleware('rol:lider')->prefix('reportes/discrepancia')->name('reportes.discrepancia.')->group(function () {
        Route::get('/', [ReporteDiscrepanciaController::class, 'index'])->name('index');
        Route::get('/pdf', [ReporteDiscrepanciaController::class, 'pdf'])->name('pdf');
    });
    Route::middleware('rol:lider')->prefix('reportes/tiempos')->name('reportes.tiempos.')->group(function () {
        Route::get('/', [ReporteTiemposController::class, 'index'])->name('index');
        Route::get('/excel', [ReporteTiemposController::class, 'excel'])->name('excel');
    });

    // Reporte de Contraste Evaluativo Individual (solo Estudiante, sobre el documento de su propio equipo).
    Route::middleware('rol:estudiante')->prefix('mi-contraste')->name('contraste.')->group(function () {
        Route::get('/', [ContrasteIndividualController::class, 'index'])->name('index');
        Route::get('/pdf', [ContrasteIndividualController::class, 'pdf'])->name('pdf');
    });

    // Reporte de Rendimiento Grupal (Profesor sobre sus grupos; el Líder supervisa).
    Route::middleware('rol:profesor,lider')->prefix('rendimiento-grupal')->name('rendimiento-grupal.')->group(function () {
        Route::get('/', [RendimientoGrupalController::class, 'index'])->name('index');
        Route::get('/excel', [RendimientoGrupalController::class, 'excel'])->name('excel');
    });

    // FN.10 Motor comparativo: cruza la calificación de la IA con la del docente y guarda las métricas.
    Route::middleware('rol:profesor,lider')->prefix('motor-comparativo')->name('motor-comparativo.')->group(function () {
        Route::get('/', [MotorComparativoController::class, 'index'])->name('index');
        Route::post('/procesar', [MotorComparativoController::class, 'procesar'])->name('procesar');
    });

    // Captura de evaluación docente (Profesor sobre sus evidencias; el Líder supervisa)
    // Permite revisar el PDF anonimizado y registrar calificación, comentarios y tiempo invertido.
    Route::middleware('rol:profesor,lider')->prefix('evaluacion-docente')->name('evaluacion-docente.')->group(function () {
        Route::get('/', [EvaluacionDocenteController::class, 'index'])->name('index');

        // Importación de rúbricas desde Excel/CSV: un formulario editable por cada evidencia con código coincidente.
        Route::post('/rubricas/importar', [RubricaImportController::class, 'importar'])->name('rubricas.importar');
        Route::get('/rubricas/revision', [RubricaImportController::class, 'revision'])->name('rubricas.revision');
        Route::post('/rubricas/revision/asignar', [RubricaImportController::class, 'asignar'])->name('rubricas.asignar');
        Route::delete('/rubricas/revision', [RubricaImportController::class, 'descartar'])->name('rubricas.descartar');
        Route::post('/rubricas/{evidencia}', [RubricaImportController::class, 'guardar'])->name('rubricas.guardar');

        Route::post('/{documento}', [EvaluacionDocenteController::class, 'store'])->name('store');
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
