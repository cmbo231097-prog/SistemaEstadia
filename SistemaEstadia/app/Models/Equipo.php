<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    public const MIN_INTEGRANTES = 2;

    public const MAX_INTEGRANTES = 6;

    protected $table = 'equipos';

    protected $primaryKey = 'id_equipo';

    public $timestamps = false;

    protected $fillable = ['nombre_equipo', 'id_grupo'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'id_grupo', 'id_grupo');
    }

    public function integrantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'equipo_estudiantes', 'id_equipo', 'id_estudiante');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CorpusDocumento::class, 'id_equipo', 'id_equipo');
    }
}
