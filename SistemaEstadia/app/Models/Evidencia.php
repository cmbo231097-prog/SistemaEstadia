<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evidencia extends Model
{
    private const TIPOS = [
        'ensayo' => 'Ensayo Académico',
        'reporte' => 'Reporte Técnico',
        'codigo' => 'Código Fuente',
        'proyecto' => 'Proyecto de Software',
    ];

    private const NIVELES = [
        'exhaustiva' => 'Exhaustiva',
        'superficial' => 'Superficial',
    ];

    protected $table = 'evidencias';
    protected $primaryKey = 'id_evidencia';
    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'tipo_evidencia',
        'nivel_retroalimentacion_ia',
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

    public static function niveles(): array
    {
        return self::NIVELES;
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente', 'id_usuario');
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'evidencia_grupos', 'id_evidencia', 'id_grupo');
    }
}
