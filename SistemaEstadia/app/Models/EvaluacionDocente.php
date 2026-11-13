<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Evaluación humana de un documento del corpus: calificación, comentarios y tiempo invertido.
class EvaluacionDocente extends Model
{
    // Escala de calificación, igual a la suma de los puntos de la rúbrica.
    public const CALIFICACION_MAXIMA = CriterioRubrica::PUNTAJE_TOTAL;

    protected $table = 'evaluaciones_docente';

    protected $primaryKey = 'id_eval_docente';

    public $timestamps = false;

    protected $fillable = [
        'id_corpus',
        'id_docente',
        'calificacion_global',
        'comentarios_cualitativos',
        'tiempo_evaluacion_minutos',
        'fecha_evaluacion',
    ];

    protected function casts(): array
    {
        return [
            'calificacion_global' => 'decimal:2',
            'tiempo_evaluacion_minutos' => 'decimal:2',
            'fecha_evaluacion' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(CorpusDocumento::class, 'id_corpus', 'id_corpus');
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(EvaluacionDocenteCriterio::class, 'id_eval_docente', 'id_eval_docente')
            ->orderBy('id_criterio');
    }

    // Persona que registró la evaluación (profesor o líder del proyecto).
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente', 'id_usuario');
    }
}
