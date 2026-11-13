<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Métricas del cruce entre la evaluación de la IA y la evaluación manual del docente para un documento.
class EvaluacionComparativa extends Model
{
    protected $table = 'evaluaciones_comparativas';

    protected $primaryKey = 'id_comparativa';

    public $timestamps = false;

    protected $fillable = [
        'id_corpus',
        'id_eval_docente',
        'id_eval_ia',
        'discrepancia_absoluta',
        'discrepancia_porcentual',
        'varianza',
        'diferencia_tiempos_minutos',
        'alerta_discrepancia',
        'fecha_calculo',
    ];

    protected function casts(): array
    {
        return [
            'discrepancia_absoluta' => 'float',
            'discrepancia_porcentual' => 'float',
            'varianza' => 'float',
            'diferencia_tiempos_minutos' => 'float',
            'alerta_discrepancia' => 'boolean',
            'fecha_calculo' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(CorpusDocumento::class, 'id_corpus', 'id_corpus');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(ComparativaCriterioDetalle::class, 'id_comparativa', 'id_comparativa')
            ->orderBy('id_criterio');
    }
}
