<?php

namespace App\Services;

use App\Models\EvaluacionComparativa;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Datos del Reporte de Discrepancia Evaluativa: compara la calificación del docente con la de Gemini
 * (ambas sobre 100 y sobre los mismos criterios) para las comparaciones del Motor Comparativo.
 *
 * El rango de fechas se aplica a la fecha en que el docente registró su evaluación y el profesor es el
 * dueño de la evidencia (quien la creó y a quien pertenece el grupo evaluado).
 */
class ReporteDiscrepanciaService
{
    public function __construct(private readonly ComparativaService $comparativas) {}

    /**
     * @return array{
     *     filas: Collection<int, array<string, mixed>>,
     *     grupos: Collection<int, array<string, mixed>>,
     *     resumen: array<string, float|int>|null
     * }
     */
    public function generar(?CarbonInterface $desde, ?CarbonInterface $hasta, ?int $idProfesor): array
    {
        $comparativas = EvaluacionComparativa::query()
            ->with([
                'detalle',
                'documento.equipo',
                'documento.evidencia.docente',
                'documento.evidencia.criteriosRubrica',
                'documento.ultimaEvaluacionIa',
                'documento.evaluacionDocente',
            ])
            ->whereHas('documento.evaluacionDocente', function ($q) use ($desde, $hasta) {
                $q->when($desde, fn ($c) => $c->where('fecha_evaluacion', '>=', $desde->copy()->startOfDay()))
                    ->when($hasta, fn ($c) => $c->where('fecha_evaluacion', '<=', $hasta->copy()->endOfDay()));
            })
            ->when($idProfesor, fn ($q) => $q->whereHas('documento.evidencia', fn ($e) => $e->where('id_docente', $idProfesor)))
            ->get();

        $filas = $comparativas->map(function (EvaluacionComparativa $c) {
            $documento = $c->documento;
            $evidencia = $documento->evidencia;
            $puntajes = $this->comparativas->puntajes($c, $evidencia->criteriosRubrica->keyBy('id_criterio'));

            return [
                'id_corpus' => $documento->id_corpus,
                'equipo' => $documento->equipo?->nombre_equipo ?? 'Documento '.$documento->id_corpus,
                'id_evidencia' => $evidencia->id_evidencia,
                'evidencia' => $evidencia->titulo,
                'id_profesor' => $evidencia->id_docente,
                'profesor' => $this->nombre($evidencia->docente),
                'fecha' => $documento->evaluacionDocente?->fecha_evaluacion,
                'docente' => $puntajes['docente'],
                'ia' => $puntajes['ia'],
                'diferencia_pts' => (float) $c->discrepancia_absoluta,
                'diferencia_pct' => (float) $c->discrepancia_porcentual,
                'alerta' => (bool) $c->alerta_discrepancia,
            ];
        })->sortBy([['evidencia', 'asc'], ['equipo', 'asc']])->values();

        $grupos = $filas->groupBy('id_evidencia')->map(fn (Collection $docs) => [
            'id_evidencia' => $docs[0]['id_evidencia'],
            'evidencia' => $docs[0]['evidencia'],
            'profesor' => $docs[0]['profesor'],
            'documentos' => $docs->count(),
            'docente' => round($docs->avg('docente'), 2),
            'ia' => round($docs->avg('ia'), 2),
            'diferencia_pct' => round($docs->avg('diferencia_pct'), 2),
            'alertas' => $docs->where('alerta', true)->count(),
        ])->values();

        return [
            'filas' => $filas,
            'grupos' => $grupos,
            'resumen' => $filas->isEmpty() ? null : [
                'documentos' => $filas->count(),
                'evidencias' => $grupos->count(),
                'profesores' => $filas->pluck('id_profesor')->unique()->count(),
                'docente_promedio' => round($filas->avg('docente'), 2),
                'ia_promedio' => round($filas->avg('ia'), 2),
                'margen_promedio' => round($filas->avg('diferencia_pct'), 2),
                'margen_maximo' => round($filas->max('diferencia_pct'), 2),
                'alertas' => $filas->where('alerta', true)->count(),
            ],
        ];
    }

    private function nombre(?User $usuario): string
    {
        return $usuario ? trim($usuario->nombre.' '.$usuario->apellidos) : 'Sin profesor';
    }
}
