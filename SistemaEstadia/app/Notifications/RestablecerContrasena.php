<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RestablecerContrasena extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña | '.config('app.name'))
            ->greeting('Hola, '.$notifiable->nombre)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta institucional.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace expirará en {$minutos} minutos por seguridad.")
            ->line('Si no solicitaste este cambio, ignora este correo; tu contraseña seguirá siendo la misma.')
            ->salutation(config('app.name'));
    }
}
