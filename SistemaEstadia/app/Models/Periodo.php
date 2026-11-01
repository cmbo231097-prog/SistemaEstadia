<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $table = 'periodos_escolares';
    protected $primaryKey = 'id_periodo';
    public $timestamps = false;

    protected $fillable = ['codigo_periodo', 'nombre_periodo', 'fecha_inicio', 'fecha_fin', 'activo'];
}
