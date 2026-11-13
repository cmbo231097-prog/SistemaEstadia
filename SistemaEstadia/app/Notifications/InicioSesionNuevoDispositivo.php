<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Aviso al titular de la cuenta cuando inicia sesión desde un navegador que no se había usado antes.
class InicioSesionNuevoDispositivo extends Notification
{
    public function __construct(private readonly string $dispositivo, private readonly ?string $ip) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Inicio de sesión desde un dispositivo nuevo | '.config('app.name'))
            ->greeting(($nombre = data_get($notifiable, 'nombre')) ? "Hola, {$nombre}" : 'Hola')
            ->line('Se inició sesión en tu cuenta desde un dispositivo que no habíamos visto antes.')
            ->line('Dispositivo: '.$this->dispositivo)
            ->line('Dirección IP: '.($this->ip ?: 'no disponible'))
            ->line('Fecha: '.now()->format('d/m/Y H:i').' (hora de la Ciudad de México)')
            ->line('Si fuiste tú, no necesitas hacer nada. Si no reconoces este acceso, restablece tu contraseña y avisa al Líder del proyecto.');
    }
}
