<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterioRubrica extends Model
{
    // Todas las evidencias se califican sobre 100 puntos.
    public const PUNTAJE_TOTAL = 100;

    public const MAX_CRITERIOS = 30;

    protected $table = 'criterios_rubrica';

    protected $primaryKey = 'id_criterio';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_criterio',
        'id_evidencia',
        'nombre_criterio',
        'descripcion_criterio',
        'puntaje_maximo',
        'evaluable_ia',
    ];

    protected function casts(): array
    {
        return ['puntaje_maximo' => 'decimal:2', 'evaluable_ia' => 'boolean'];
    }

    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'id_evidencia', 'id_evidencia');
    }
}
