<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EvaluacionIaCriterio extends Model
{
    protected $table = 'evaluacion_ia_criterios';

    protected $primaryKey = 'id_eval_ia_criterio';

    public $timestamps = false;

    protected $fillable = [
        'id_eval_ia',
        'id_evidencia',
        'id_criterio',
        'calificacion',
        'retroalimentacion',
    ];

    protected function casts(): array
    {
        return ['calificacion' => 'decimal:2'];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionIa::class, 'id_eval_ia', 'id_eval_ia');
    }

    public function criterio(): HasOne
    {
        return $this->hasOne(CriterioRubrica::class, 'id_criterio', 'id_criterio')
            ->where('criterios_rubrica.id_evidencia', $this->id_evidencia);
    }
}
