<?php

namespace App\Http\Controllers;

use App\Models\CorpusDocumento;
use App\Models\CriterioRubrica;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\LoteEvaluacionIa;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\EvaluacionIaService;
use App\Services\GeminiEvaluationService;
use App\Services\LoteEvaluacionIaService;
use App\Services\RubricaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

// Módulo "Evaluación con IA": evalúa un PDF sanitizado con Gemini y guarda el resultado en la base de datos
// (evaluaciones_ia), de donde lo toma el motor comparativo. No se descarga ningún archivo.
class EvaluacionIaController extends Controller
{
    // Subir el PDF y esperar a Gemini puede superar el límite habitual de 30 s de PHP.
    private const LIMITE_SEGUNDOS = EvaluacionIaService::LIMITE_SEGUNDOS;

    public function __construct(
        private readonly GeminiEvaluationService $gemini,
        private readonly EvaluacionIaService $guardado,
        private readonly RubricaService $rubricas,
        private readonly LoteEvaluacionIaService $lotes,
    ) {}

    public function index(Request $request)
    {
        $grupos = $this->gruposDisponibles();
        $asignaturas = $grupos->pluck('asignatura')->filter()->unique('id_asignatura')->values();

        $asignatura = $asignaturas->firstWhere('id_asignatura', (int) $request->query('asignatura')) ?? $asignaturas->first();
        $gruposAsignatura = $grupos->where('id_asignatura', $asignatura?->id_asignatura)->values();
        $grupo = $gruposAsignatura->firstWhere('id_grupo', (int) $request->query('grupo')) ?? $gruposAsignatura->first();

        $evidencias = $grupo
            ? $this->evidenciasVisibles()->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))->orderBy('titulo')->get()
            : collect();
        $evidencia = $evidencias->firstWhere('id_evidencia', (int) $request->query('evidencia')) ?? $evidencias->first();

        $criterios = $evidencia ? $evidencia->criteriosRubrica()->orderBy('id_criterio')->get() : collect();

        $documentos = collect();
        if ($evidencia && $grupo) {
            $documentos = CorpusDocumento::with(['equipo', 'ultimaEvaluacionIa.criterios'])
                ->where('id_evidencia', $evidencia->id_evidencia)
                ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
                ->orderBy('id_corpus')
                ->get();
        }

        $pendientes = $evidencia && $grupo ? $this->lotes->pendientes($evidencia, $grupo)->count() : 0;
        // Se muestra el lote en curso o, tras terminar, el resumen del lote que se solicitó por `?lote=`.
        $lote = null;
        if ($evidencia && $grupo) {
            $lote = $this->lotes->activo($evidencia->id_evidencia, $grupo->id_grupo)
                ?? LoteEvaluacionIa::where('id_lote', (int) $request->query('lote'))
                    ->where('id_evidencia', $evidencia->id_evidencia)->where('id_grupo', $grupo->id_grupo)
                    ->when(! $this->esLider(), fn ($q) => $q->where('id_usuario', Auth::id()))->first();
        }

        return view('evaluacion-ia.index', [
            'pendientesLote' => $pendientes,
            'lote' => $lote,
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => $asignaturas,
            'grupos' => $gruposAsignatura,
            'evidencias' => $evidencias,
            'asignatura' => $asignatura,
            'grupo' => $grupo,
            'evidencia' => $evidencia,
            'criteriosIa' => $criterios->where('evaluable_ia', true)->values(),
            'criteriosDocente' => $criterios->where('evaluable_ia', false)->values(),
            'documentos' => $documentos,
            'nombresCriterios' => $criterios->pluck('nombre_criterio', 'id_criterio'),
            'maximosCriterios' => $criterios->pluck('puntaje_maximo', 'id_criterio'),
            'rubricaBloqueada' => $evidencia ? $this->rubricas->estaBloqueada($evidencia) : false,
            'geminiConfigurado' => filled(config('services.gemini.api_key')),
            'escala' => CriterioRubrica::PUNTAJE_TOTAL,
        ]);
    }

    public function evaluar(Request $request, CorpusDocumento $documento): JsonResponse|RedirectResponse
    {
        $this->autorizar($documento);
        $evidencia = $documento->evidencia;

        if (! filled(config('services.gemini.api_key'))) {
            return $this->fallo($request, $documento, 'Configura GEMINI_API_KEY en el archivo .env antes de evaluar con IA.', 503);
        }
        if (! $documento->estaSanitizado() || ! $documento->ruta_archivo_sanitizado || ! Storage::exists($documento->ruta_archivo_sanitizado)) {
            return $this->fallo($request, $documento, 'El PDF debe estar sanitizado y disponible antes de evaluarlo. Hazlo en «Carga y Sanitización».', 422);
        }

        $criterios = $evidencia->criteriosRubrica()->orderBy('id_criterio')->get();
        $criteriosIa = $criterios->where('evaluable_ia', true)->values();
        if ($criteriosIa->isEmpty()) {
            return $this->fallo($request, $documento, 'La evidencia no tiene una rúbrica con criterios evaluables desde el producto. Configúrala antes de evaluar con IA.', 422);
        }

        // Evita enviar dos veces el mismo documento a Gemini (doble clic o dos pestañas).
        $candado = Cache::lock('evaluacion-ia-'.$documento->id_corpus, self::LIMITE_SEGUNDOS + 60);
        if (! $candado->get()) {
            return $this->fallo($request, $documento, 'Este documento ya se está evaluando. Espera a que termine.', 409);
        }

        try {
            set_time_limit(self::LIMITE_SEGUNDOS);
            $inicio = microtime(true);
            $resultado = $this->gemini->evaluar(
                Storage::path($documento->ruta_archivo_sanitizado),
                $evidencia->titulo,
                $criteriosIa->all()
            );
            $duracion = round(microtime(true) - $inicio, 2);
            // El candado se mantiene hasta guardar para que dos evaluaciones no se pisen.
            $evaluacion = $this->guardado->guardar($documento, $resultado, $duracion);
        } catch (Throwable $e) {
            report($e);
            $mensaje = $this->mensajeDeError($e);
            $this->avisar($documento, 'Evaluación con IA fallida', "No se pudo evaluar {$this->descripcion($documento)}. {$mensaje}");

            return $this->fallo($request, $documento, $mensaje, 502);
        } finally {
            $candado->release();
        }

        $mensaje = 'Evaluación con IA guardada: '.rtrim(rtrim(number_format((float) $evaluacion->calificacion_global, 2, '.', ''), '0'), '.')
            .' puntos en los criterios que evalúa la IA.';
        $this->avisar($documento, 'Evaluación con IA terminada', "Se evaluó {$this->descripcion($documento)}: {$mensaje}");

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return $this->volver($documento)->with('status', $mensaje);
    }

    /** Envía a la IA, de uno en uno, todos los PDFs sanitizados de la evidencia que aún no tienen evaluación. */
    public function evaluarLote(Request $request): JsonResponse|RedirectResponse
    {
        $datos = $request->validate(['id_evidencia' => ['required', 'integer'], 'id_grupo' => ['required', 'integer']]);

        $grupo = $this->gruposDisponibles()->firstWhere('id_grupo', (int) $datos['id_grupo']);
        abort_unless($grupo, 403, 'No puedes evaluar documentos de este grupo.');
        $evidencia = $this->evidenciasVisibles()
            ->whereKey($datos['id_evidencia'])
            ->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))
            ->firstOrFail();

        $rechazo = match (true) {
            ! filled(config('services.gemini.api_key')) => ['Configura GEMINI_API_KEY en el archivo .env antes de evaluar con IA.', 503],
            ! $evidencia->tieneRubricaParaIa() => ['La evidencia no tiene una rúbrica con criterios evaluables desde el producto. Configúrala antes de evaluar con IA.', 422],
            $this->lotes->activo($evidencia->id_evidencia, $grupo->id_grupo) !== null => ['Ya hay un lote de esta evidencia evaluándose. Espera a que termine.', 409],
            default => null,
        };
        $documentos = $rechazo ? collect() : $this->lotes->pendientes($evidencia, $grupo);
        if (! $rechazo && $documentos->isEmpty()) {
            $rechazo = ['No hay PDFs sanitizados pendientes de evaluar. Los que ya tienen evaluación se reenvían uno por uno con «Volver a evaluar».', 422];
        }
        if ($rechazo) {
            return $request->expectsJson()
                ? response()->json(['message' => $rechazo[0]], $rechazo[1])
                : back()->with('error', $rechazo[0]);
        }

        $lote = $this->lotes->crear((int) Auth::id(), $evidencia, $grupo, $documentos);
        $this->lotes->iniciar($lote);

        $mensaje = "Se envió el lote: {$documentos->count()} PDF(s) se evaluarán de uno en uno. Te avisaremos cuando termine.";
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje, 'lote' => $lote->id_lote], 202);
        }

        return redirect()->route('evaluacion-ia.index', ['asignatura' => $grupo->id_asignatura, 'grupo' => $grupo->id_grupo, 'evidencia' => $evidencia->id_evidencia])
            ->with('status', $mensaje);
    }

    /** Avance del lote para la barra de progreso de la pantalla. */
    public function estadoLote(LoteEvaluacionIa $lote): JsonResponse
    {
        abort_unless($this->esLider() || (int) $lote->id_usuario === (int) Auth::id(), 403);

        return response()->json([
            'estado' => $lote->estado,
            'total' => $lote->total,
            'procesados' => $lote->procesados(),
            'exitosos' => $lote->exitosos,
            'fallidos' => $lote->fallidos,
            'documentos' => $lote->documentos,
        ]);
    }

    private function descripcion(CorpusDocumento $documento): string
    {
        return ($documento->equipo?->nombre_equipo ?? 'el documento').' · «'.($documento->evidencia?->titulo ?? 'evidencia').'»';
    }

    private function avisar(CorpusDocumento $documento, string $titulo, string $mensaje): void
    {
        Notificacion::avisar((int) Auth::id(), $titulo, $mensaje, Notificacion::TIPO_EVALUACION_IA);
    }

    private function mensajeDeError(Throwable $e): string
    {
        return $this->guardado->mensajeDeError($e);
    }

    private function fallo(Request $request, CorpusDocumento $documento, string $mensaje, int $estado): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], $estado);
        }

        return $this->volver($documento)->with('error', $mensaje);
    }

    private function volver(CorpusDocumento $documento): RedirectResponse
    {
        $grupo = $documento->equipo?->grupo;

        return redirect()->route('evaluacion-ia.index', array_filter([
            'asignatura' => $grupo?->id_asignatura,
            'grupo' => $grupo?->id_grupo,
            'evidencia' => $documento->id_evidencia,
        ]));
    }

    private function esLider(): bool
    {
        return Auth::user()?->role === User::ROLE_LIDER;
    }

    private function evidenciasVisibles()
    {
        return Evidencia::query()->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()));
    }

    private function gruposDisponibles()
    {
        return Grupo::query()
            ->with('asignatura')
            ->when(! $this->esLider(), fn ($q) => $q->where('id_docente', Auth::id()))
            ->orderBy('clave_grupo')
            ->get();
    }

    private function autorizar(CorpusDocumento $documento): void
    {
        $evidencia = $documento->evidencia;
        abort_unless(
            $evidencia && ($this->esLider() || (int) $evidencia->id_docente === (int) Auth::id()),
            403,
            'Solo puedes evaluar documentos de tus propias evidencias.'
        );
    }
}
