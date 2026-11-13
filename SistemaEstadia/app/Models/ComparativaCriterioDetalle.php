<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Comparación de un criterio de la rúbrica: calificación del docente frente a la de la IA.
class ComparativaCriterioDetalle extends Model
{
    protected $table = 'comparativa_criterios_detalle';

    // Llave compuesta (id_comparativa, id_evidencia, id_criterio): no se usa una llave única de Eloquent.
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_comparativa',
        'id_evidencia',
        'id_criterio',
        'calificacion_docente',
        'calificacion_ia',
        'discrepancia_criterio',
        'comentarios_criterio',
    ];

    protected function casts(): array
    {
        return [
            'calificacion_docente' => 'float',
            'calificacion_ia' => 'float',
            'discrepancia_criterio' => 'float',
        ];
    }
}
