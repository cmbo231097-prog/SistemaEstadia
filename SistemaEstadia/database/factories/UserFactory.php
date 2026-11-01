<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'matricula' => strtoupper(fake()->unique()->bothify('ITI#######')),
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'correo_institucional' => fake()->unique()->userName().'@upemor.edu.mx',
            'password_hash' => static::$password ??= Hash::make('password'),
            'id_rol' => fn () => Role::idPorClave(User::ROLE_ESTUDIANTE),
            'estatus' => User::ESTATUS_ACTIVO,
        ];
    }
}
