<?php

namespace App\Http\Controllers;

use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionDocente;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use App\Services\ComparativaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Captura de evaluación docente: el profesor o líder revisa el PDF anonimizado y registra su evaluación humana.
class EvaluacionDocenteController extends Controller
{
    public function __construct(private readonly ComparativaService $comparativas) {}

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

        $documentos = collect();
        if ($evidencia && $grupo) {
            // Solo se listan documentos ya anonimizados: es lo mismo que recibe la IA y evita sesgos por la portada.
            $documentos = CorpusDocumento::with(['equipo', 'evaluacionDocente.criterios'])
                ->where('id_evidencia', $evidencia->id_evidencia)
                ->where('estado_sanitizacion', CorpusDocumento::SANITIZADO)
                ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
                ->orderBy('id_corpus')
                ->get();
        }
        $documento = $documentos->firstWhere('id_corpus', (int) $request->query('documento')) ?? $documentos->first();
        $criterios = $evidencia
            ? $evidencia->criteriosRubrica()->orderBy('id_criterio')->get()
            : collect();

        return view('evaluacion-docente.index', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => $asignaturas,
            'grupos' => $gruposAsignatura,
            'evidencias' => $evidencias,
            'asignatura' => $asignatura,
            'grupo' => $grupo,
            'evidencia' => $evidencia,
            'documentos' => $documentos,
            'documento' => $documento,
            'criterios' => $criterios,
            'escala' => EvaluacionDocente::CALIFICACION_MAXIMA,
        ]);
    }

    public function store(Request $request, CorpusDocumento $documento): RedirectResponse
    {
        $this->autorizar($documento);
        abort_unless($documento->estaSanitizado(), 422, 'Solo se pueden evaluar documentos sanitizados.');

        $sinRubrica = 'Carga la rúbrica de esta evidencia antes de evaluar la práctica.';
        // Sin rúbrica no hay criterios con los cuales calificar la práctica.
        if (! $documento->evidencia->criteriosRubrica()->exists()) {
            return back()->with('error', $sinRubrica);
        }

        $faltaRubrica = false;
        DB::transaction(function () use ($request, $documento, &$faltaRubrica) {
            // El bloqueo evita que la rúbrica se reemplace mientras se valida y guarda la evaluación.
            Evidencia::whereKey($documento->id_evidencia)->lockForUpdate()->firstOrFail();
            $criterios = $documento->evidencia->criteriosRubrica()->orderBy('id_criterio')->get();
            if ($criterios->isEmpty()) {
                $faltaRubrica = true;

                return;
            }

            $datos = $request->validate(...$this->reglas($criterios));
            $calificaciones = $datos['criterios'];
            $total = round($criterios->sum(fn ($c) => (float) $calificaciones[$c->id_criterio]['calificacion']), 2);

            // Una sola evaluación por documento: guardar de nuevo corrige la registrada.
            $evaluacion = EvaluacionDocente::updateOrCreate(
                ['id_corpus' => $documento->id_corpus],
                [
                    'calificacion_global' => min($total, EvaluacionDocente::CALIFICACION_MAXIMA),
                    'comentarios_cualitativos' => $datos['comentarios_cualitativos'] ?? null,
                    'tiempo_evaluacion_minutos' => $this->aMinutos($datos['tiempo_evaluacion']),
                    'id_docente' => Auth::id(),
                    'fecha_evaluacion' => now(),
                ]
            );

            $evaluacion->criterios()->delete();
            $evaluacion->criterios()->createMany($criterios->map(fn ($c) => [
                'id_evidencia' => $c->id_evidencia,
                'id_criterio' => $c->id_criterio,
                'calificacion' => $calificaciones[$c->id_criterio]['calificacion'],
                'retroalimentacion' => filled($calificaciones[$c->id_criterio]['retroalimentacion'] ?? null)
                    ? trim($calificaciones[$c->id_criterio]['retroalimentacion'])
                    : null,
            ])->all());
        });

        if ($faltaRubrica) {
            return back()->with('error', $sinRubrica);
        }

        // Si el documento ya tiene evaluación con IA, las métricas del motor comparativo se actualizan solas.
        $this->comparativas->actualizar($documento);

        $grupo = $documento->equipo?->grupo;

        return redirect()->route('evaluacion-docente.index', array_filter([
            'asignatura' => $grupo?->id_asignatura,
            'grupo' => $grupo?->id_grupo,
            'evidencia' => $documento->id_evidencia,
            'documento' => $documento->id_corpus,
        ]))->with('status', 'La evaluación se guardó correctamente.');
    }

    /** Convierte «mm:ss» a minutos decimales (la columna guarda minutos, con dos decimales). */
    private function aMinutos(string $tiempo): float
    {
        [$minutos, $segundos] = array_map('intval', explode(':', $tiempo) + [1 => 0]);

        return round(($minutos * 60 + $segundos) / 60, 2);
    }

    /**
     * Reglas y mensajes de validación: una calificación obligatoria (0 al puntaje máximo del criterio)
     * y una retroalimentación opcional por cada criterio de la rúbrica.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function reglas(Collection $criterios): array
    {
        $reglas = [
            'comentarios_cualitativos' => ['nullable', 'string', 'max:5000'],
            'tiempo_evaluacion' => ['required', 'string', 'regex:/^\d{1,3}:[0-5]\d$/', fn ($atributo, $valor, $fallar) => $this->aMinutos((string) $valor) > 0 ? null : $fallar('El tiempo invertido debe ser mayor que cero.')],
            'criterios' => ['required', 'array'],
        ];
        $mensajes = [
            'comentarios_cualitativos.max' => 'Los comentarios generales no pueden exceder 5000 caracteres.',
            'tiempo_evaluacion.required' => 'Indica el tiempo invertido en la revisión.',
            'tiempo_evaluacion.regex' => 'Escribe el tiempo como minutos:segundos, por ejemplo 01:50.',
            'criterios.required' => 'Califica cada criterio de la rúbrica.',
        ];

        foreach ($criterios as $criterio) {
            $clave = "criterios.{$criterio->id_criterio}";
            $nombre = "«{$criterio->nombre_criterio}»";
            $maximo = rtrim(rtrim(number_format((float) $criterio->puntaje_maximo, 2, '.', ''), '0'), '.');

            $reglas["{$clave}.calificacion"] = ['required', 'numeric', 'min:0', "max:{$criterio->puntaje_maximo}"];
            $reglas["{$clave}.retroalimentacion"] = ['nullable', 'string', 'max:2000'];
            $mensajes["{$clave}.calificacion.required"] = "Captura la calificación del criterio {$nombre}.";
            $mensajes["{$clave}.calificacion.numeric"] = "La calificación del criterio {$nombre} debe ser un número.";
            $mensajes["{$clave}.calificacion.min"] = "La calificación del criterio {$nombre} no puede ser negativa.";
            $mensajes["{$clave}.calificacion.max"] = "La calificación del criterio {$nombre} no puede superar {$maximo} puntos.";
            $mensajes["{$clave}.retroalimentacion.max"] = "La retroalimentación del criterio {$nombre} no puede exceder 2000 caracteres.";
        }

        return [$reglas, $mensajes];
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
