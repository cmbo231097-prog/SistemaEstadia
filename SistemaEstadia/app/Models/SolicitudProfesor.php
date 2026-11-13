<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Petición de un estudiante para recibir el rol de Profesor.
class SolicitudProfesor extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ATENDIDA = 'atendida';

    protected $table = 'solicitudes_profesor';

    protected $primaryKey = 'id_solicitud';

    protected $fillable = ['id_usuario', 'estado'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public static function pendienteDe(int $idUsuario): ?self
    {
        return self::where('id_usuario', $idUsuario)->where('estado', self::PENDIENTE)->latest('id_solicitud')->first();
    }
}
