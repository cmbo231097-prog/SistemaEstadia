<?php

namespace App\Services;

use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use Illuminate\Support\Collection;

/**
 * Datos del Reporte de Rendimiento Grupal: calificaciones y desempeño por rubro de los equipos de un grupo en
 * una evidencia, cruzando la evaluación del docente con la de la IA.
 *
 * Los rubros pertenecen a cada evidencia, por eso el reporte se genera para una evidencia del grupo. La IA solo
 * califica los rubros «evaluables desde el producto»; los demás (por ejemplo desempeño) solo los califica el docente.
 */
class RendimientoGrupalService
{
    // Colores de los rubros en la gráfica (pantalla y Excel); si hay más rubros se repiten.
    public const PALETA = ['1E3A6E', '3F9A6B', 'D1802F', '4F46E5', '0EA5E9', 'B91C1C', 'A16207', '0F766E', '9333EA', 'DB2777', '65A30D', '475569'];

    public static function color(int $indice): string
    {
        return self::PALETA[$indice % count(self::PALETA)];
    }

    /**
     * @return array{
     *     criterios: Collection<int, array<string, mixed>>,
     *     equipos: Collection<int, array<string, mixed>>,
     *     barras: Collection<int, array<string, mixed>>,
     *     rubros: Collection<int, array<string, mixed>>,
     *     resumen: array<string, float|int|null>
     * }
     */
    public function generar(Grupo $grupo, Evidencia $evidencia): array
    {
        $criterios = $evidencia->criteriosRubrica()->orderBy('id_criterio')->get()->map(fn ($c) => [
            'id' => (int) $c->id_criterio,
            'nombre' => $c->nombre_criterio,
            'maximo' => (float) $c->puntaje_maximo,
            'ia' => (bool) $c->evaluable_ia,
        ])->values();

        $equipos = Equipo::where('id_grupo', $grupo->id_grupo)->orderBy('nombre_equipo')->get();
        $documentos = CorpusDocumento::with(['evaluacionDocente.criterios', 'ultimaEvaluacionIa.criterios'])
            ->where('id_evidencia', $evidencia->id_evidencia)
            ->whereIn('id_equipo', $equipos->pluck('id_equipo'))
            ->get()
            ->keyBy('id_equipo');

        $filas = $equipos->filter(fn (Equipo $e) => $documentos->has($e->id_equipo))->map(function (Equipo $equipo) use ($documentos) {
            $doc = $documentos[$equipo->id_equipo];
            $docente = $doc->evaluacionDocente;
            $ia = $doc->ultimaEvaluacionIa;

            return [
                'id_corpus' => $doc->id_corpus,
                'equipo' => $equipo->nombre_equipo,
                'docente' => $docente ? [
                    'total' => (float) $docente->calificacion_global,
                    'criterios' => $docente->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => (float) $c->calificacion])->all(),
                    'retro' => $docente->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => $c->retroalimentacion])->all(),
                ] : null,
                'ia' => $ia ? [
                    'total' => (float) $ia->calificacion_global,
                    'criterios' => $ia->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => (float) $c->calificacion])->all(),
                    'retro' => $ia->criterios->mapWithKeys(fn ($c) => [(int) $c->id_criterio => $c->retroalimentacion])->all(),
                ] : null,
            ];
        })->values();

        $rubros = $criterios->map(function (array $c) use ($filas) {
            $docentes = $filas->map(fn ($f) => $f['docente']['criterios'][$c['id']] ?? null)->filter(fn ($v) => $v !== null);
            $ias = $filas->map(fn ($f) => $f['ia']['criterios'][$c['id']] ?? null)->filter(fn ($v) => $v !== null);
            $docente = $docentes->isEmpty() ? null : round($docentes->avg(), 2);
            $ia = $ias->isEmpty() ? null : round($ias->avg(), 2);

            return $c + [
                'docente_prom' => $docente,
                'docente_pct' => $docente !== null && $c['maximo'] > 0 ? round($docente / $c['maximo'] * 100, 1) : null,
                'ia_prom' => $ia,
                'ia_pct' => $ia !== null && $c['maximo'] > 0 ? round($ia / $c['maximo'] * 100, 1) : null,
                'diferencia' => $docente !== null && $ia !== null ? round($ia - $docente, 2) : null,
            ];
        });

        return [
            'criterios' => $criterios,
            'equipos' => $filas,
            'barras' => $this->barras($filas),
            'rubros' => $rubros,
            'resumen' => $this->resumen($filas, $criterios),
        ];
    }

    /**
     * Una barra apilada por equipo y fuente (docente / IA), con un segmento por rubro.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function barras(Collection $filas): Collection
    {
        $barras = collect();
        foreach ($filas as $fila) {
            foreach (['docente' => 'Docente', 'ia' => 'IA'] as $clave => $fuente) {
                $dato = $fila[$clave];
                if ($dato === null) {
                    continue;
                }
                $barras->push([
                    'equipo' => $fila['equipo'],
                    'fuente' => $fuente,
                    'total' => $dato['total'],
                    'segmentos' => $dato['criterios'],
                    // Evaluaciones del docente anteriores al registro por rubro: solo hay calificación total.
                    'sin_desglose' => $dato['criterios'] === [],
                ]);
            }
        }

        return $barras;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  Collection<int, array<string, mixed>>  $criterios
     * @return array<string, float|int|null>
     */
    private function resumen(Collection $filas, Collection $criterios): array
    {
        $docentes = $filas->pluck('docente')->filter();
        $ias = $filas->pluck('ia')->filter();
        $maximoIa = (float) $criterios->where('ia', true)->sum('maximo');

        // Solo equipos con ambas evaluaciones y desglose: se compara al docente en los mismos rubros que evaluó la IA.
        $ambos = $filas->filter(fn ($f) => $f['docente'] && $f['ia'] && $f['docente']['criterios'] !== []);
        $idsIa = $criterios->where('ia', true)->pluck('id');
        $docenteEnRubrosIa = $ambos->isEmpty() ? null : round($ambos->avg(
            fn ($f) => $idsIa->sum(fn ($id) => $f['docente']['criterios'][$id] ?? 0)
        ), 2);

        return [
            'equipos' => $filas->count(),
            'con_docente' => $docentes->count(),
            'con_ia' => $ias->count(),
            'docente_prom' => $docentes->isEmpty() ? null : round($docentes->avg('total'), 2),
            'ia_prom' => $ias->isEmpty() ? null : round($ias->avg('total'), 2),
            'ia_maximo' => $maximoIa,
            'docente_en_rubros_ia' => $docenteEnRubrosIa,
            'comparados' => $ambos->count(),
        ];
    }
}
