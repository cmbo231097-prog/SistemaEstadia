<?php

namespace App\Http\Controllers;

use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionComparativa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\User;
use App\Services\ComparativaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// FN.10 Motor comparativo: cruza la calificación de la IA con la del docente para un lote de evidencias.
class MotorComparativoController extends Controller
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

        $rubrica = $evidencia ? $evidencia->criteriosRubrica()->orderBy('id_criterio')->get()->keyBy('id_criterio') : collect();

        $filas = collect();
        if ($evidencia && $grupo) {
            $documentos = CorpusDocumento::with(['equipo', 'ultimaEvaluacionIa', 'evaluacionDocente', 'comparativa.detalle'])
                ->where('id_evidencia', $evidencia->id_evidencia)
                ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
                ->orderBy('id_corpus')
                ->get();

            $filas = $documentos->map(function (CorpusDocumento $doc) use ($rubrica) {
                $comparativa = $doc->comparativa;

                return [
                    'documento' => $doc,
                    'comparativa' => $comparativa,
                    'ia' => $doc->ultimaEvaluacionIa,
                    'docente' => $doc->evaluacionDocente,
                    'puntajes' => $comparativa ? $this->comparativas->puntajes($comparativa, $rubrica) : null,
                    'motivo' => $comparativa ? null : $this->comparativas->calcular($doc)['motivo'] ?? null,
                ];
            });
        }

        $comparadas = $filas->pluck('comparativa')->filter();

        return view('motor-comparativo.index', [
            'user' => Auth::user(),
            'roleName' => User::roles()[Auth::user()->role] ?? '',
            'asignaturas' => $asignaturas,
            'grupos' => $gruposAsignatura,
            'evidencias' => $evidencias,
            'asignatura' => $asignatura,
            'grupo' => $grupo,
            'evidencia' => $evidencia,
            'rubrica' => $rubrica,
            'filas' => $filas,
            'resumen' => $this->comparativas->resumen($comparadas),
        ]);
    }

    // Procesa el lote completo: recalcula la comparación de todos los documentos que tengan ambas evaluaciones.
    public function procesar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'id_evidencia' => ['required', 'integer'],
            'id_grupo' => ['required', 'integer'],
        ]);

        $grupo = $this->gruposDisponibles()->firstWhere('id_grupo', (int) $datos['id_grupo']);
        abort_unless($grupo, 403, 'No puedes procesar documentos de este grupo.');
        $evidencia = $this->evidenciasVisibles()
            ->whereKey($datos['id_evidencia'])
            ->whereHas('grupos', fn ($q) => $q->where('grupos.id_grupo', $grupo->id_grupo))
            ->firstOrFail();

        $documentos = CorpusDocumento::where('id_evidencia', $evidencia->id_evidencia)
            ->whereIn('id_equipo', Equipo::where('id_grupo', $grupo->id_grupo)->pluck('id_equipo'))
            ->orderBy('id_corpus')
            ->get();

        $volver = redirect()->route('motor-comparativo.index', [
            'asignatura' => $grupo->id_asignatura,
            'grupo' => $grupo->id_grupo,
            'evidencia' => $evidencia->id_evidencia,
        ]);

        if ($documentos->isEmpty()) {
            return $volver->with('error', 'No hay documentos cargados para esta evidencia.');
        }

        $resultado = $this->comparativas->procesarLote($documentos);
        if ($resultado['comparados'] === 0) {
            return $volver->with('error', 'No hay documentos con evaluación de la IA y del docente para comparar. Evalúa primero los documentos del lote.');
        }

        $mensaje = "Lote procesado: {$resultado['comparados']} de {$documentos->count()} documento(s) comparados y guardados.";
        $omitidos = count($resultado['omitidos']);
        if ($omitidos > 0) {
            $mensaje .= " {$omitidos} quedaron pendientes porque les falta alguna evaluación.";
        }

        return $volver->with('status', $mensaje);
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
}
