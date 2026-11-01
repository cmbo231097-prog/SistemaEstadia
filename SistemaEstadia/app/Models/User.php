<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\RestablecerContrasena;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_LIDER = 'lider';
    public const ROLE_PROFESOR = 'profesor';
    public const ROLE_ESTUDIANTE = 'estudiante';

    public const ESTATUS_ACTIVO = 'activo';
    public const ESTATUS_INACTIVO = 'inactivo';

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'matricula',
        'nombre',
        'apellidos',
        'correo_institucional',
        'password_hash',
        'id_rol',
        'estatus',
        'google_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }

    public static function roles(): array
    {
        return [
            self::ROLE_LIDER => 'Líder de proyecto',
            self::ROLE_PROFESOR => 'Profesor',
            self::ROLE_ESTUDIANTE => 'Estudiante',
        ];
    }

    /** Clave interna del rol (lider, profesor, estudiante). */
    public function getRoleAttribute(): string
    {
        return Role::claveDe($this->rol?->nombre_rol);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre.' '.$this->apellidos);
    }

    public function isRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isActivo(): bool
    {
        return $this->estatus === self::ESTATUS_ACTIVO;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // La tabla no tiene remember_token; la sesión solo se mantiene por la cookie de sesión.
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo_institucional;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo_institucional;
    }
}
