<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispositivoUsuario extends Model
{
    protected $table = 'dispositivos_usuario';

    protected $primaryKey = 'id_dispositivo';

    protected $fillable = ['id_usuario', 'token_hash', 'descripcion', 'ip', 'ultimo_acceso'];

    protected function casts(): array
    {
        return ['ultimo_acceso' => 'datetime'];
    }
}
