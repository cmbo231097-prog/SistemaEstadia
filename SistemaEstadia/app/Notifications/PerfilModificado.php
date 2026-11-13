<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Aviso al titular de un perfil cuando alguien modifica su información.
class PerfilModificado extends Notification
{
    /** @param  array<int, string>  $campos  Nombres de los datos modificados (nunca sus valores). */
    public function __construct(private readonly array $campos, private readonly string $modificadoPor) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject('Se modificó la información de tu perfil | '.config('app.name'))
            ->greeting(($nombre = data_get($notifiable, 'nombre')) ? "Hola, {$nombre}" : 'Hola')
            ->line($this->modificadoPor.' modificó la información de tu perfil el '.now()->format('d/m/Y H:i').' (hora de la Ciudad de México).')
            ->line('Datos modificados:');

        foreach ($this->campos as $campo) {
            $mensaje->line('• '.$campo);
        }

        return $mensaje->line('Si no reconoces este cambio, comunícate con el Líder del proyecto.');
    }
}
