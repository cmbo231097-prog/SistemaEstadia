<?php

namespace App\Services;

use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\Evidencia;
use Illuminate\Support\Collection;

/**
 * Datos del Reporte de Contraste Evaluativo Individual: compara, rubro por rubro, la calificación que el
 * profesor dio al documento del equipo del estudiante con la que emitió Gemini.
 *
 * La IA solo califica los rubros «evaluables desde el producto»; los demás los califica únicamente el docente.
 */
class ContrasteIndividualService
{
    public const COMPLETO = 'completo';

    public const SIN_DOCUMENTO = 'sin_documento';

    public const SIN_DOCENTE = 'sin_docente';

    public const SIN_IA = 'sin_ia';

    public const SIN_AMBOS = 'sin_evaluaciones';

    /**
     * @return array{
     *     estado: string,
     *     rubros: Collection<int, array<string, mixed>>,
     *     resumen: array<string, float|int|null>,
     *     docente: ?array<string, mixed>,
     *     ia: ?array<string, mixed>
     * }
     */
    public function generar(Equipo $equipo, Evidencia $evidencia): array
    {
        $criterios = $evidencia->criteriosRubrica()->orderBy('id_criterio')->get();
        $documento = CorpusDocumento::with(['evaluacionDocente.criterios', 'ultimaEvaluacionIa.criterios'])
            ->where('id_evidencia', $evidencia->id_evidencia)
            ->where('id_equipo', $equipo->id_equipo)
            ->first();

        $docente = $documento?->evaluacionDocente;
        $ia = $documento?->ultimaEvaluacionIa;
        $puntosDocente = $docente?->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => $c]) ?? collect();
        $puntosIa = $ia?->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => $c]) ?? collect();

        $rubros = $criterios->map(function ($c) use ($puntosDocente, $puntosIa) {
            $maximo = (float) $c->puntaje_maximo;
            $d = $puntosDocente->get((int) $c->id_criterio);
            $i = $puntosIa->get((int) $c->id_criterio);
            $dPts = $d ? (float) $d->calificacion : null;
            $iPts = $i ? (float) $i->calificacion : null;

            return [
                'id' => (int) $c->id_criterio,
                'nombre' => $c->nombre_criterio,
                'maximo' => $maximo,
                'ia_evalua' => (bool) $c->evaluable_ia,
                'docente' => $dPts,
                'docente_pct' => $dPts !== null && $maximo > 0 ? round($dPts / $maximo * 100, 1) : null,
                'ia' => $iPts,
                'ia_pct' => $iPts !== null && $maximo > 0 ? round($iPts / $maximo * 100, 1) : null,
                'diferencia' => $dPts !== null && $iPts !== null ? round($iPts - $dPts, 2) : null,
                'retro_docente' => filled($d?->retroalimentacion) ? $d->retroalimentacion : null,
                'retro_ia' => filled($i?->retroalimentacion) ? $i->retroalimentacion : null,
            ];
        })->values();

        $estado = match (true) {
            $documento === null => self::SIN_DOCUMENTO,
            $docente !== null && $ia !== null => self::COMPLETO,
            $docente !== null => self::SIN_IA,
            $ia !== null => self::SIN_DOCENTE,
            default => self::SIN_AMBOS,
        };

        return [
            'estado' => $estado,
            'rubros' => $rubros,
            'resumen' => $this->resumen($rubros, $docente, $ia),
            'docente' => $docente ? [
                'total' => (float) $docente->calificacion_global,
                'comentarios' => $docente->comentarios_cualitativos,
                'fecha' => $docente->fecha_evaluacion,
                'desglose' => $puntosDocente->isNotEmpty(),
            ] : null,
            'ia' => $ia ? [
                'total' => (float) $ia->calificacion_global,
                'comentarios' => $ia->retroalimentacion_cualitativa,
                'fecha' => $ia->fecha_procesamiento,
                'modelo' => $ia->modelo_llm,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rubros
     * @return array<string, float|int|null>
     */
    private function resumen(Collection $rubros, $docente, $ia): array
    {
        $rubrosIa = $rubros->where('ia_evalua', true);
        $maximoIa = (float) $rubrosIa->sum('maximo');

        // Para comparar con justicia se suma lo que el docente dio en los mismos rubros que evaluó la IA.
        $comparable = $docente && $ia && $rubrosIa->isNotEmpty() && $rubrosIa->every(fn ($r) => $r['docente'] !== null && $r['ia'] !== null);
        $docenteEnRubrosIa = $comparable ? round($rubrosIa->sum('docente'), 2) : null;
        $iaTotal = $ia ? (float) $ia->calificacion_global : null;

        return [
            'docente_total' => $docente ? (float) $docente->calificacion_global : null,
            'ia_total' => $iaTotal,
            'ia_maximo' => $maximoIa,
            'docente_en_rubros_ia' => $docenteEnRubrosIa,
            'diferencia' => $comparable ? round($iaTotal - $docenteEnRubrosIa, 2) : null,
            // Margen de error frente a la calificación del docente en los mismos rubros (0 si el docente puso 0).
            'diferencia_pct' => $comparable && $docenteEnRubrosIa > 0 ? round(abs($iaTotal - $docenteEnRubrosIa) / $docenteEnRubrosIa * 100, 1) : null,
            'rubros_comparados' => $rubros->filter(fn ($r) => $r['docente'] !== null && $r['ia'] !== null)->count(),
        ];
    }
}
