<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = ['nombre_rol', 'descripcion'];

    /** Clave interna (lider, profesor, estudiante) deducida del nombre del rol. */
    public static function claveDe(?string $nombreRol): string
    {
        $nombre = Str::of((string) $nombreRol)->ascii()->lower();

        return match (true) {
            $nombre->contains('lider') => User::ROLE_LIDER,
            $nombre->contains(['profesor', 'docente']) => User::ROLE_PROFESOR,
            default => User::ROLE_ESTUDIANTE,
        };
    }

    public function getClaveAttribute(): string
    {
        return self::claveDe($this->nombre_rol);
    }

    public static function idPorClave(string $clave): ?int
    {
        $roles = static::query()->get();

        // Primero coincidencia explícita por nombre; "estudiante" también es el valor por defecto de claveDe().
        $rol = $roles->first(fn (self $r) => self::coincideExplicito($r->nombre_rol, $clave));

        return $rol?->id_rol;
    }

    /** Devuelve el id del rol; si aún no existe en la tabla roles, lo crea. */
    public static function idOCrearPorClave(string $clave): int
    {
        $id = self::idPorClave($clave);

        if ($id) {
            return $id;
        }

        $nombre = User::roles()[$clave];

        return static::create(['nombre_rol' => $nombre, 'descripcion' => $nombre])->id_rol;
    }

    private static function coincideExplicito(?string $nombreRol, string $clave): bool
    {
        $nombre = Str::of((string) $nombreRol)->ascii()->lower();

        return match ($clave) {
            User::ROLE_LIDER => $nombre->contains('lider'),
            User::ROLE_PROFESOR => $nombre->contains(['profesor', 'docente']),
            default => $nombre->contains(['estudiante', 'alumno']),
        };
    }
}
