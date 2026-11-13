<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Avisos del sistema para un usuario (canal «sistema»).
class Notificacion extends Model
{
    public const TIPO_EVALUACION_IA = 'evaluacion_ia';

    public const TIPO_LOTE_IA = 'evaluacion_ia_lote';

    public const TIPO_SOLICITUD_PROFESOR = 'solicitud_profesor';

    public const TIPO_PERFIL_MODIFICADO = 'perfil_modificado';

    public const TIPO_NUEVO_DISPOSITIVO = 'inicio_sesion_nuevo_dispositivo';

    protected $table = 'notificaciones';

    protected $primaryKey = 'id_notificacion';

    public $timestamps = false;

    protected $fillable = ['id_usuario', 'titulo', 'mensaje', 'tipo_evento', 'canal', 'leido', 'fecha_envio'];

    protected function casts(): array
    {
        return ['leido' => 'boolean', 'fecha_envio' => 'datetime'];
    }

    public static function avisar(int $idUsuario, string $titulo, string $mensaje, string $tipo, string $canal = 'sistema'): self
    {
        return self::create([
            'id_usuario' => $idUsuario,
            'titulo' => mb_substr($titulo, 0, 120),
            'mensaje' => $mensaje,
            'tipo_evento' => $tipo,
            'canal' => $canal,
            'leido' => false,
            'fecha_envio' => now(),
        ]);
    }
}
