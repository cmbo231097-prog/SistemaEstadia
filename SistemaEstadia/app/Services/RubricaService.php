<?php

namespace App\Services;

use App\Models\CriterioRubrica;
use App\Models\Evidencia;
use App\Models\EvaluacionDocenteCriterio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Reglas y persistencia de la rúbrica de una evidencia, compartidas por el editor manual y la importación.
class RubricaService
{
    /**
     * @param  array<string, mixed>  $entrada
     * @return array<int, array{nombre_criterio: string, descripcion_criterio: string, puntaje_maximo: float, evaluable_ia: bool}>
     *
     * @throws ValidationException
     */
    public function validar(array $entrada): array
    {
        $total = CriterioRubrica::PUNTAJE_TOTAL;

        $datos = Validator::make($entrada, [
            'criterios' => ['required', 'array', 'min:1', 'max:'.CriterioRubrica::MAX_CRITERIOS],
            'criterios.*.nombre_criterio' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'criterios.*.descripcion_criterio' => ['nullable', 'string', 'max:1000'],
            'criterios.*.puntaje_maximo' => ['required', 'numeric', 'gt:0', 'lte:'.$total],
            'criterios.*.evaluable_ia' => ['sometimes', 'boolean'],
        ], [
            'criterios.required' => 'Agrega al menos un criterio a la rúbrica.',
            'criterios.min' => 'Agrega al menos un criterio a la rúbrica.',
            'criterios.max' => 'La rúbrica admite como máximo '.CriterioRubrica::MAX_CRITERIOS.' criterios.',
            'criterios.*.nombre_criterio.required' => 'Cada criterio necesita un nombre.',
            'criterios.*.nombre_criterio.max' => 'El nombre de un criterio no puede exceder 100 caracteres.',
            'criterios.*.nombre_criterio.distinct' => 'No repitas el nombre de un criterio.',
            'criterios.*.descripcion_criterio.max' => 'La descripción de un criterio no puede exceder 1000 caracteres.',
            'criterios.*.puntaje_maximo.required' => 'Indica el puntaje máximo de cada criterio.',
            'criterios.*.puntaje_maximo.numeric' => 'El puntaje máximo debe ser un número.',
            'criterios.*.puntaje_maximo.gt' => 'El puntaje máximo debe ser mayor que cero.',
            'criterios.*.puntaje_maximo.lte' => "El puntaje máximo de cada criterio no puede superar {$total}.",
            'criterios.*.evaluable_ia.boolean' => 'Indica si el criterio es evaluable desde el producto.',
        ])->validate();

        $criterios = array_map(static fn (array $criterio): array => [
            'nombre_criterio' => trim($criterio['nombre_criterio']),
            // La descripción es opcional: sin ella el propio nombre del criterio describe lo que se evalúa.
            'descripcion_criterio' => trim((string) ($criterio['descripcion_criterio'] ?? '')) ?: trim($criterio['nombre_criterio']),
            'puntaje_maximo' => (float) $criterio['puntaje_maximo'],
            'evaluable_ia' => (bool) ($criterio['evaluable_ia'] ?? true),
        ], array_values($datos['criterios']));

        $suma = array_sum(array_column($criterios, 'puntaje_maximo'));
        if (round($suma, 2) !== (float) $total) {
            throw ValidationException::withMessages([
                'criterios' => ["La suma de los puntajes máximos debe ser exactamente {$total} puntos (actualmente suma ".round($suma, 2).').'],
            ]);
        }

        if (! in_array(true, array_column($criterios, 'evaluable_ia'), true)) {
            throw ValidationException::withMessages([
                'criterios' => ['Al menos un criterio debe ser evaluable desde el producto (la IA solo evalúa esos criterios).'],
            ]);
        }

        return $criterios;
    }

    /**
     * @param  array<int, array<string, mixed>>  $criterios  Criterios ya validados con validar().
     */
    public function guardar(Evidencia $evidencia, array $criterios): void
    {
        DB::transaction(function () use ($evidencia, $criterios) {
            Evidencia::whereKey($evidencia->getKey())->lockForUpdate()->firstOrFail();
            $evidencia->criteriosRubrica()->delete();
            $evidencia->criteriosRubrica()->createMany(array_map(
                static fn (array $criterio, int $indice): array => ['id_criterio' => $indice + 1, ...$criterio],
                $criterios,
                array_keys($criterios)
            ));
        });
    }

    // Una rúbrica con evaluaciones (de IA o calificadas por el docente criterio por criterio) es historial y no se modifica.
    public function estaBloqueada(Evidencia $evidencia): bool
    {
        return $evidencia->grupos()->whereHas('equipos.documentos.evaluacionesIa')->exists()
            || EvaluacionDocenteCriterio::where('id_evidencia', $evidencia->getKey())->exists();
    }
}
