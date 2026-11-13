<?php

namespace App\Services;

use App\Models\CorpusDocumento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Datos del Reporte de Eficiencia de Tiempos: contrasta el tiempo que el docente invirtió en revisar cada
 * documento con el tiempo de respuesta de la API de Gemini (ambos en minutos).
 *
 * El rango de fechas se aplica a la fecha en que el docente registró su evaluación y la asignatura es la del
 * grupo al que pertenece el equipo del documento. Solo cuentan documentos con ambas evaluaciones.
 */
class ReporteTiemposService
{
    /**
     * @return array{
     *     filas: Collection<int, array<string, mixed>>,
     *     grupos: Collection<int, array<string, mixed>>,
     *     resumen: array<string, float|int>|null
     * }
     */
    public function generar(?CarbonInterface $desde, ?CarbonInterface $hasta, ?int $idAsignatura): array
    {
        $documentos = CorpusDocumento::query()
            ->with(['equipo.grupo.asignatura', 'evidencia', 'evaluacionDocente', 'ultimaEvaluacionIa'])
            ->whereHas('evaluacionesIa')
            ->whereHas('evaluacionDocente', function ($q) use ($desde, $hasta) {
                $q->when($desde, fn ($c) => $c->where('fecha_evaluacion', '>=', $desde->copy()->startOfDay()))
                    ->when($hasta, fn ($c) => $c->where('fecha_evaluacion', '<=', $hasta->copy()->endOfDay()));
            })
            ->when($idAsignatura, fn ($q) => $q->whereHas('equipo.grupo', fn ($g) => $g->where('id_asignatura', $idAsignatura)))
            ->get();

        $filas = $documentos->map(function (CorpusDocumento $doc) {
            $docente = (float) $doc->evaluacionDocente->tiempo_evaluacion_minutos;
            $segundosIa = (float) $doc->ultimaEvaluacionIa->tiempo_procesamiento_segundos;
            $ia = $segundosIa / 60;

            return [
                'id_corpus' => $doc->id_corpus,
                'equipo' => $doc->equipo?->nombre_equipo ?? 'Documento '.$doc->id_corpus,
                'id_evidencia' => $doc->id_evidencia,
                'evidencia' => $doc->evidencia->titulo,
                'asignatura' => $doc->equipo?->grupo?->asignatura?->nombre_asignatura ?? 'Sin asignatura',
                'fecha' => $doc->evaluacionDocente->fecha_evaluacion,
                'docente_min' => $docente,
                'ia_seg' => $segundosIa,
                'ia_min' => $ia,
                'ahorro_min' => $docente - $ia,
                'ahorro_pct' => $this->ahorro($docente, $ia),
            ];
        })->sortBy([['asignatura', 'asc'], ['evidencia', 'asc'], ['equipo', 'asc']])->values();

        $grupos = $filas->groupBy('id_evidencia')->map(function (Collection $docs) {
            $docente = $docs->avg('docente_min');
            $ia = $docs->avg('ia_min');

            return [
                'id_evidencia' => $docs[0]['id_evidencia'],
                'evidencia' => $docs[0]['evidencia'],
                'asignatura' => $docs[0]['asignatura'],
                'documentos' => $docs->count(),
                'docente_min' => round($docente, 2),
                'ia_min' => round($ia, 2),
                'ahorro_min' => round($docente - $ia, 2),
                'ahorro_pct' => round($this->ahorro($docente, $ia), 1),
            ];
        })->values();

        if ($filas->isEmpty()) {
            return ['filas' => $filas, 'grupos' => $grupos, 'resumen' => null];
        }

        $docenteProm = $filas->avg('docente_min');
        $iaProm = $filas->avg('ia_min');

        return [
            'filas' => $filas,
            'grupos' => $grupos,
            'resumen' => [
                'documentos' => $filas->count(),
                'evidencias' => $grupos->count(),
                'asignaturas' => $filas->pluck('asignatura')->unique()->count(),
                'docente_prom_min' => round($docenteProm, 2),
                'ia_prom_min' => round($iaProm, 2),
                'ia_prom_seg' => round($filas->avg('ia_seg'), 1),
                'ahorro_prom_min' => round($docenteProm - $iaProm, 2),
                'ahorro_pct' => round($this->ahorro($docenteProm, $iaProm), 1),
                'docente_total_horas' => round($filas->sum('docente_min') / 60, 2),
                'ia_total_horas' => round($filas->sum('ia_min') / 60, 2),
            ],
        ];
    }

    // Porcentaje de tiempo que la API ahorra frente a la revisión docente (negativo si fue más lenta).
    private function ahorro(float $docente, float $ia): float
    {
        return $docente > 0 ? ($docente - $ia) / $docente * 100 : 0.0;
    }
}
