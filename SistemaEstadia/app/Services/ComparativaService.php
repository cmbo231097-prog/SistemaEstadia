<?php

namespace App\Services;

use App\Models\ComparativaCriterioDetalle;
use App\Models\CorpusDocumento;
use App\Models\EvaluacionComparativa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor comparativo (FN.10): cruza la evaluación de la IA con la evaluación manual del docente y
 * guarda el margen de error porcentual, la varianza y la diferencia de tiempos de revisión.
 *
 * Ambas calificaciones se comparan sobre los mismos criterios (los que la IA evalúa) y se normalizan
 * a 100 puntos; así los criterios que solo califica el docente no distorsionan el margen de error.
 */
class ComparativaService
{
    // El margen de error se limita a lo que admite la columna decimal(5,2).
    private const MAX_PORCENTAJE = 999.99;

    public function umbralAlerta(): float
    {
        return (float) config('services.comparativa.umbral_alerta', 10);
    }

    /**
     * Calcula las métricas sin guardarlas.
     *
     * @return array{ok: true, metricas: array<string, mixed>, detalle: array<int, array<string, mixed>>}|array{ok: false, motivo: string}
     */
    public function calcular(CorpusDocumento $documento): array
    {
        $documento->load(['evidencia.criteriosRubrica', 'ultimaEvaluacionIa.criterios', 'evaluacionDocente.criterios']);
        $ia = $documento->ultimaEvaluacionIa;
        $docente = $documento->evaluacionDocente;

        if (! $ia) {
            return ['ok' => false, 'motivo' => 'Falta la evaluación con IA.'];
        }
        if (! $docente) {
            return ['ok' => false, 'motivo' => 'Falta la evaluación del docente.'];
        }

        $rubrica = $documento->evidencia->criteriosRubrica->keyBy('id_criterio');
        $porIa = $ia->criterios->filter(fn ($c) => $rubrica->has($c->id_criterio))->keyBy('id_criterio');
        if ($porIa->isEmpty()) {
            return ['ok' => false, 'motivo' => 'La evaluación con IA no tiene criterios de la rúbrica actual.'];
        }

        $maximo = (float) $porIa->sum(fn ($c) => (float) $rubrica[$c->id_criterio]->puntaje_maximo);
        $totalIa = (float) $porIa->sum(fn ($c) => (float) $c->calificacion);
        $porDocente = $docente->criterios->keyBy('id_criterio');
        $detalle = [];

        if ($porDocente->isEmpty()) {
            // Evaluaciones anteriores al registro por rubro: solo comparables si la IA cubrió toda la rúbrica.
            if ($porIa->count() !== $rubrica->count()) {
                return ['ok' => false, 'motivo' => 'La evaluación del docente se capturó sin calificar por rubro; vuelve a calificar cada rubro para compararla con la IA.'];
            }
            $totalDocente = (float) $docente->calificacion_global;
        } else {
            if ($porIa->keys()->diff($porDocente->keys())->isNotEmpty()) {
                return ['ok' => false, 'motivo' => 'El docente no calificó todos los rubros que evaluó la IA.'];
            }
            $totalDocente = (float) $porIa->sum(fn ($c) => (float) $porDocente[$c->id_criterio]->calificacion);

            foreach ($porIa as $idCriterio => $criterioIa) {
                $criterioDocente = $porDocente[$idCriterio];
                $comentarios = array_filter([
                    filled($criterioDocente->retroalimentacion) ? 'Docente: '.$criterioDocente->retroalimentacion : null,
                    filled($criterioIa->retroalimentacion) ? 'IA: '.$criterioIa->retroalimentacion : null,
                ]);
                $detalle[] = [
                    'id_evidencia' => $documento->id_evidencia,
                    'id_criterio' => $idCriterio,
                    'calificacion_docente' => round((float) $criterioDocente->calificacion, 2),
                    'calificacion_ia' => round((float) $criterioIa->calificacion, 2),
                    'discrepancia_criterio' => round(abs((float) $criterioIa->calificacion - (float) $criterioDocente->calificacion), 2),
                    'comentarios_criterio' => $comentarios === [] ? null : implode("\n", $comentarios),
                ];
            }
        }

        $escalaIa = $totalIa / $maximo * 100;
        $escalaDocente = $totalDocente / $maximo * 100;
        $diferencia = abs($escalaIa - $escalaDocente);
        // Margen de error relativo a la calificación del docente; si este asignó 0 se usa la escala de 100.
        $porcentual = $escalaDocente > 0 ? $diferencia / $escalaDocente * 100 : $diferencia;

        return [
            'ok' => true,
            'metricas' => [
                'discrepancia_absoluta' => round($diferencia, 2),
                'discrepancia_porcentual' => round(min($porcentual, self::MAX_PORCENTAJE), 2),
                // Varianza poblacional del par (IA, docente) en escala de 0 a 10: ((a - b) / 2)².
                'varianza' => round(($diferencia / 10 / 2) ** 2, 4),
                'diferencia_tiempos_minutos' => round(
                    (float) $docente->tiempo_evaluacion_minutos - (float) $ia->tiempo_procesamiento_segundos / 60,
                    2
                ),
                'alerta_discrepancia' => $porcentual > $this->umbralAlerta(),
                'id_eval_docente' => $docente->id_eval_docente,
                'id_eval_ia' => $ia->id_eval_ia,
            ],
            'detalle' => $detalle,
        ];
    }

    /**
     * Recalcula y guarda la comparación del documento. Si ya no es comparable (por ejemplo, porque se
     * reemplazó una de las evaluaciones), elimina la comparación anterior para no dejar métricas obsoletas.
     */
    public function actualizar(CorpusDocumento $documento): ?EvaluacionComparativa
    {
        return DB::transaction(function () use ($documento) {
            // Serializa los cálculos del mismo documento (la tabla admite una comparación por documento).
            CorpusDocumento::whereKey($documento->getKey())->lockForUpdate()->first();

            $previas = EvaluacionComparativa::where('id_corpus', $documento->id_corpus)->pluck('id_comparativa');
            ComparativaCriterioDetalle::whereIn('id_comparativa', $previas)->delete();
            EvaluacionComparativa::whereIn('id_comparativa', $previas)->delete();

            $resultado = $this->calcular($documento);
            if (! $resultado['ok']) {
                return null;
            }

            $comparativa = EvaluacionComparativa::create([
                ...$resultado['metricas'],
                'id_corpus' => $documento->id_corpus,
                'fecha_calculo' => now(),
            ]);
            $comparativa->detalle()->createMany($resultado['detalle']);

            return $comparativa;
        });
    }

    /**
     * Procesa un lote de documentos.
     *
     * @param  Collection<int, CorpusDocumento>  $documentos
     * @return array{comparados: int, omitidos: array<int, string>}  Omitidos: id_corpus => motivo.
     */
    public function procesarLote(Collection $documentos): array
    {
        $comparados = 0;
        $omitidos = [];
        foreach ($documentos as $documento) {
            if ($this->actualizar($documento)) {
                $comparados++;
            } else {
                $omitidos[$documento->id_corpus] = $this->calcular($documento)['motivo'] ?? 'No comparable.';
            }
        }

        return ['comparados' => $comparados, 'omitidos' => $omitidos];
    }

    /**
     * Calificaciones de la IA y del docente sobre los mismos criterios, normalizadas a 100 puntos.
     *
     * @param  Collection<int, \App\Models\CriterioRubrica>  $rubrica  Criterios de la evidencia indexados por id_criterio.
     * @return array{ia: float, docente: float}
     */
    public function puntajes(EvaluacionComparativa $comparativa, Collection $rubrica): array
    {
        $detalle = $comparativa->detalle;
        if ($detalle->isEmpty()) {
            // Comparación sin detalle por criterio (evaluación docente anterior): se usan las calificaciones globales.
            return [
                'ia' => (float) ($comparativa->documento?->ultimaEvaluacionIa?->calificacion_global ?? 0),
                'docente' => (float) ($comparativa->documento?->evaluacionDocente?->calificacion_global ?? 0),
            ];
        }

        $maximo = (float) $detalle->sum(fn ($d) => (float) ($rubrica[$d->id_criterio]->puntaje_maximo ?? 0));
        if ($maximo <= 0) {
            return ['ia' => 0.0, 'docente' => 0.0];
        }

        return [
            'ia' => round($detalle->sum('calificacion_ia') / $maximo * 100, 2),
            'docente' => round($detalle->sum('calificacion_docente') / $maximo * 100, 2),
        ];
    }

    /**
     * Resumen estadístico del lote a partir de las métricas ya guardadas.
     *
     * @param  Collection<int, EvaluacionComparativa>  $comparativas
     * @return array<string, float|int>|null
     */
    public function resumen(Collection $comparativas): ?array
    {
        if ($comparativas->isEmpty()) {
            return null;
        }

        return [
            'documentos' => $comparativas->count(),
            'margen_error_promedio' => round($comparativas->avg('discrepancia_porcentual'), 2),
            'discrepancia_promedio' => round($comparativas->avg('discrepancia_absoluta'), 2),
            'varianza_promedio' => round($comparativas->avg('varianza'), 4),
            'diferencia_tiempos_promedio' => round($comparativas->avg('diferencia_tiempos_minutos'), 2),
            'alertas' => $comparativas->where('alerta_discrepancia', true)->count(),
        ];
    }
}
