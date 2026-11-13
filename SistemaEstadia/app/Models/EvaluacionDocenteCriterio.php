<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Calificación del docente para un criterio de la rúbrica; la retroalimentación es opcional.
class EvaluacionDocenteCriterio extends Model
{
    protected $table = 'evaluacion_docente_criterios';

    protected $primaryKey = 'id_eval_docente_criterio';

    public $timestamps = false;

    protected $fillable = [
        'id_eval_docente',
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
        return $this->belongsTo(EvaluacionDocente::class, 'id_eval_docente', 'id_eval_docente');
    }
}
