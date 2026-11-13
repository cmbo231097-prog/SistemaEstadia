<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CorpusDocumento extends Model
{
    // Estados usados para identificar el resultado de la sanitización del documento.
    public const PENDIENTE = 'pendiente';

    public const SANITIZADO = 'sanitizado';

    public const ERROR = 'error';

    // La tabla usa una clave primaria con nombre propio y no mantiene marcas de tiempo.
    protected $table = 'corpus_documentos';

    protected $primaryKey = 'id_corpus';

    public $timestamps = false;

    // Atributos que pueden asignarse en operaciones de creación o actualización masiva.
    protected $fillable = [
        'id_evidencia',
        'id_estudiante',
        'id_equipo',
        'nombre_archivo_original',
        'ruta_archivo_original',
        'ruta_archivo_sanitizado',
        'estado_sanitizacion',
    ];

    // Relaciona el documento con la evidencia académica a la que pertenece.
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'id_evidencia', 'id_evidencia');
    }

    // Relaciona el documento con el equipo asociado, si corresponde.
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'id_equipo', 'id_equipo');
    }

    public function evaluacionesIa(): HasMany
    {
        return $this->hasMany(EvaluacionIa::class, 'id_corpus', 'id_corpus');
    }

    public function ultimaEvaluacionIa(): HasOne
    {
        return $this->hasOne(EvaluacionIa::class, 'id_corpus', 'id_corpus')->latestOfMany('id_eval_ia');
    }

    // Evaluación humana registrada para el documento, si existe.
    public function evaluacionDocente(): HasOne
    {
        return $this->hasOne(EvaluacionDocente::class, 'id_corpus', 'id_corpus');
    }

    // Métricas del cruce IA vs. docente, si ya se calcularon.
    public function comparativa(): HasOne
    {
        return $this->hasOne(EvaluacionComparativa::class, 'id_corpus', 'id_corpus');
    }

    // Indica si el estado actual confirma que el archivo ya fue sanitizado.
    public function estaSanitizado(): bool
    {
        return $this->estado_sanitizacion === self::SANITIZADO;
    }
}
