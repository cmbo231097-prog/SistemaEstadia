<?php

namespace App\Services;

use App\Models\DispositivoUsuario;
use App\Models\Notificacion;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InicioSesionNuevoDispositivo;
use App\Notifications\PerfilModificado;
use App\Notifications\SolicitudCambioProfesor;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificacionFacade;
use Illuminate\Support\Str;
use Throwable;

// Correos del sistema (con su copia en la campana): solicitud de profesor, perfil modificado y dispositivo nuevo.
class AvisosCorreoService
{
    public const COOKIE_DISPOSITIVO = 'dispositivo_id';

    /** Líder que recibe las solicitudes: el correo configurado o, si no hay, el Líder activo más antiguo. */
    public function destinatarioLider(): ?User
    {
        $consulta = User::where('id_rol', Role::idOCrearPorClave(User::ROLE_LIDER))->where('estatus', User::ESTATUS_ACTIVO);

        $correo = config('services.solicitudes_profesor.correo');
        if (filled($correo) && ($configurado = (clone $consulta)->where('correo_institucional', Str::lower(trim($correo)))->first())) {
            return $configurado;
        }

        return $consulta->orderBy('id_usuario')->first();
    }

    /** Avisa al Líder (y solo a él) que un estudiante solicitó ser profesor. */
    public function solicitudProfesor(User $estudiante): ?User
    {
        $lider = $this->destinatarioLider();
        if (! $lider) {
            return null;
        }

        Notificacion::avisar(
            $lider->id_usuario,
            'Solicitud para ser profesor',
            "{$estudiante->nombre_completo} ({$estudiante->matricula}) solicitó cambiar su rol a Profesor. Revísalo en Gestión de Usuarios.",
            Notificacion::TIPO_SOLICITUD_PROFESOR,
            'ambos'
        );
        $this->enviar($lider, new SolicitudCambioProfesor($estudiante));

        return $lider;
    }

    /**
     * Avisa al titular que se modificó su perfil. Si cambió el correo, también se avisa al anterior.
     *
     * @param  array<int, string>  $campos
     */
    public function perfilModificado(User $usuario, array $campos, string $modificadoPor, ?string $correoAnterior = null): void
    {
        if ($campos === []) {
            return;
        }

        Notificacion::avisar(
            $usuario->id_usuario,
            'Se modificó tu perfil',
            $modificadoPor.' modificó: '.implode(', ', $campos).'.',
            Notificacion::TIPO_PERFIL_MODIFICADO,
            'ambos'
        );

        $this->enviar($usuario, new PerfilModificado($campos, $modificadoPor));
        if ($correoAnterior && $correoAnterior !== $usuario->correo_institucional) {
            $this->enviar($correoAnterior, new PerfilModificado($campos, $modificadoPor));
        }
    }

    /**
     * Registra el navegador desde el que se inició sesión y avisa si es la primera vez que se usa con esta cuenta.
     * El primer acceso de una cuenta solo establece el punto de partida: no hay un dispositivo anterior con el cual comparar.
     */
    public function inicioDeSesion(User $usuario, Request $request): void
    {
        try {
            $token = (string) $request->cookie(self::COOKIE_DISPOSITIVO);
            $hash = hash('sha256', $token);
            $ip = $request->ip();

            if ($token !== '' && ($conocido = DispositivoUsuario::where('id_usuario', $usuario->id_usuario)->where('token_hash', $hash)->first())) {
                $conocido->update(['ultimo_acceso' => now(), 'ip' => $ip]);

                return;
            }

            $habiaOtros = DispositivoUsuario::where('id_usuario', $usuario->id_usuario)->exists();
            $token = $token !== '' ? $token : Str::random(40);
            $descripcion = $this->describirDispositivo((string) $request->userAgent());

            DispositivoUsuario::create([
                'id_usuario' => $usuario->id_usuario,
                'token_hash' => hash('sha256', $token),
                'descripcion' => $descripcion,
                'ip' => $ip,
                'ultimo_acceso' => now(),
            ]);
            Cookie::queue(Cookie::make(self::COOKIE_DISPOSITIVO, $token, 60 * 24 * 730, null, null, null, true, false, 'lax'));

            if ($habiaOtros) {
                Notificacion::avisar(
                    $usuario->id_usuario,
                    'Inicio de sesión en un dispositivo nuevo',
                    "Se inició sesión en tu cuenta desde {$descripcion} (IP ".($ip ?: 'no disponible').'). Si no fuiste tú, restablece tu contraseña.',
                    Notificacion::TIPO_NUEVO_DISPOSITIVO,
                    'ambos'
                );
                $this->enviar($usuario, new InicioSesionNuevoDispositivo($descripcion, $ip));
            }
        } catch (Throwable $e) {
            // Un fallo al registrar o avisar nunca debe impedir el acceso.
            report($e);
        }
    }

    /** «Chrome en macOS», a partir del User-Agent. */
    public function describirDispositivo(string $agente): string
    {
        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'OPR/') || str_contains($agente, 'Opera') => 'Opera',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Chrome/') || str_contains($agente, 'CriOS') => 'Chrome',
            str_contains($agente, 'Safari/') => 'Safari',
            default => 'Navegador desconocido',
        };
        $sistema = match (true) {
            str_contains($agente, 'iPhone') || str_contains($agente, 'iPad') => 'iOS',
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Mac OS X') || str_contains($agente, 'Macintosh') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => 'sistema desconocido',
        };

        return "{$navegador} en {$sistema}";
    }

    /** El correo se envía al terminar la respuesta para no retrasar la pantalla; un fallo SMTP solo se registra. */
    private function enviar(User|string $destino, Notification $notificacion): void
    {
        // La aplicación conserva los callbacks de cierre; la bandera evita reenviar el correo si se atiende otra petición.
        $enviado = false;
        app()->terminating(function () use ($destino, $notificacion, &$enviado) {
            if ($enviado) {
                return;
            }
            $enviado = true;

            try {
                $destino instanceof User
                    ? $destino->notifyNow($notificacion)
                    : NotificacionFacade::route('mail', $destino)->notifyNow($notificacion);
            } catch (Throwable $e) {
                Log::warning('No se pudo enviar el correo «'.class_basename($notificacion).'»: '.$e->getMessage());
            }
        });
    }
}
