<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidencia extends Model
{
    private const TIPOS = [
        'ensayo' => 'Ensayo Académico',
        'reporte' => 'Reporte Técnico',
        'codigo' => 'Código Fuente',
        'proyecto' => 'Proyecto de Software',
    ];

    protected $table = 'evidencias';

    protected $primaryKey = 'id_evidencia';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'codigo',
        'tipo_evidencia',
        'descripcion_entregables',
        'fecha_limite',
        'id_docente',
    ];

    protected function casts(): array
    {
        return ['fecha_limite' => 'datetime'];
    }

    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente', 'id_usuario');
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'evidencia_grupos', 'id_evidencia', 'id_grupo');
    }

    public function criteriosRubrica(): HasMany
    {
        return $this->hasMany(CriterioRubrica::class, 'id_evidencia', 'id_evidencia');
    }

    // Solo estos criterios se envían a la IA; el resto lo califica el docente.
    public function criteriosEvaluablesIa(): HasMany
    {
        return $this->criteriosRubrica()->where('evaluable_ia', true);
    }

    public function tieneRubricaParaIa(): bool
    {
        return $this->criteriosEvaluablesIa()->exists();
    }
}
