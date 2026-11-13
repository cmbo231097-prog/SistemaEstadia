<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $nombresRol = [
            User::ROLE_LIDER => 'Líder de Proyecto',
            User::ROLE_PROFESOR => 'Profesor',
            User::ROLE_ESTUDIANTE => 'Estudiante',
        ];

        // Solo crea los roles que aún no existan en la tabla.
        foreach ($nombresRol as $clave => $nombre) {
            if (! Role::idPorClave($clave)) {
                Role::create(['nombre_rol' => $nombre, 'descripcion' => $nombre]);
            }
        }

        $demo = [
            [User::ROLE_LIDER, 'LIDER001', 'Líder', 'de Proyecto', 'lider@estadia.com'],
            [User::ROLE_PROFESOR, 'PROF001', 'Profesor', 'Demo', 'profesor@estadia.com'],
            [User::ROLE_ESTUDIANTE, 'ITI2023001', 'Estudiante', 'Demo', 'estudiante@estadia.com'],
        ];

        foreach ($demo as [$clave, $matricula, $nombre, $apellidos, $correo]) {
            User::updateOrCreate(
                ['correo_institucional' => $correo],
                [
                    'matricula' => $matricula,
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'password_hash' => 'Demo2026!',
                    'id_rol' => Role::idOCrearPorClave($clave),
                    'estatus' => User::ESTATUS_ACTIVO,
                ]
            );
        }

        $this->call(DemoDataSeeder::class);
    }
}
