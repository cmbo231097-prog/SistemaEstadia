<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespaldoBd extends Model
{
    protected $table = 'respaldos_bd';
    protected $primaryKey = 'id_respaldo';
    public $timestamps = false;

    protected $fillable = [
        'nombre_archivo',
        'tamano_mb',
        'alcance_tablas',
        'id_usuario_creador',
        'fecha_respaldo',
    ];

    protected function casts(): array
    {
        return ['fecha_respaldo' => 'datetime', 'tamano_mb' => 'float'];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_creador', 'id_usuario');
    }
}
