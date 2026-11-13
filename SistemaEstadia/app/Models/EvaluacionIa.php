<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionIa extends Model
{
    protected $table = 'evaluaciones_ia';

    protected $primaryKey = 'id_eval_ia';

    public $timestamps = false;

    protected $fillable = [
        'id_corpus',
        'modelo_llm',
        'calificacion_global',
        'retroalimentacion_cualitativa',
        'tiempo_procesamiento_segundos',
        'fecha_procesamiento',
    ];

    protected function casts(): array
    {
        return [
            'calificacion_global' => 'decimal:2',
            'tiempo_procesamiento_segundos' => 'decimal:2',
            'fecha_procesamiento' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(CorpusDocumento::class, 'id_corpus', 'id_corpus');
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(EvaluacionIaCriterio::class, 'id_eval_ia', 'id_eval_ia');
    }
}
