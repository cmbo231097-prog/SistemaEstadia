<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Aviso al Líder de Proyecto: un estudiante pidió ser profesor.
class SolicitudCambioProfesor extends Notification
{
    public function __construct(private readonly User $solicitante) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Solicitud para ser profesor | '.config('app.name'))
            ->greeting(($nombre = data_get($notifiable, 'nombre')) ? "Hola, {$nombre}" : 'Hola')
            ->line('Un usuario con rol Estudiante solicitó unirse al sistema como profesor.')
            ->line('Nombre: '.$this->solicitante->nombre_completo)
            ->line('Matrícula: '.$this->solicitante->matricula)
            ->line('Correo: '.$this->solicitante->correo_institucional)
            ->line('Fecha de la solicitud: '.now()->format('d/m/Y H:i').' (hora de la Ciudad de México)')
            ->action('Revisar en Gestión de Usuarios', route('admin.usuarios.index', ['q' => $this->solicitante->matricula]))
            ->line('Para aceptarla, cambia su rol a Profesor desde Gestión de Usuarios. Si no deseas aceptarla, ignora este mensaje.');
    }
}
