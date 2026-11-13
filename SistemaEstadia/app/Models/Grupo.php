<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $table = 'grupos';

    protected $primaryKey = 'id_grupo';

    public $timestamps = false;

    protected $fillable = [
        'clave_grupo',
        'id_asignatura',
        'id_docente',
        'id_periodo',
    ];

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente', 'id_usuario');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'id_periodo', 'id_periodo');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'id_grupo', 'id_grupo');
    }
}
