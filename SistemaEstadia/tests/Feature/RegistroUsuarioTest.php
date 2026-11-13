<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'Ya existe un usuario registrado con los datos proporcionados.';

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Luis',
            'apellidos' => 'Pérez',
            'matricula' => 'ITI2023001',
            'email' => 'luis@upemor.edu.mx',
            'password' => 'Segura#2026a',
            'password_confirmation' => 'Segura#2026a',
            'terms' => '1',
        ], $extra);
    }

    private function existente(): User
    {
        return User::create([
            'matricula' => 'ITI2023001',
            'nombre' => 'Ana',
            'apellidos' => 'Docente',
            'correo_institucional' => 'ana@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave(User::ROLE_ESTUDIANTE),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    public function test_registro_valido_crea_la_cuenta(): void
    {
        $this->post(route('register.submit'), $this->datos())->assertRedirect(route('login'));

        $this->assertDatabaseHas('usuarios', ['matricula' => 'ITI2023001', 'correo_institucional' => 'luis@upemor.edu.mx']);
    }

    public function test_matricula_o_correo_repetidos_dan_el_mismo_mensaje_generico_sin_indicar_el_dato(): void
    {
        $this->existente();

        foreach ([
            $this->datos(['email' => 'otro@upemor.edu.mx']),
            $this->datos(['matricula' => 'ITI2023999', 'email' => 'ana@upemor.edu.mx']),
        ] as $datos) {
            $this->from(route('register'))
                ->post(route('register.submit'), $datos)
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors(['registro' => self::MENSAJE])
                ->assertSessionDoesntHaveErrors(['matricula', 'email']);
        }

        $this->assertDatabaseCount('usuarios', 1);
        $this->get(route('register'))->assertOk();
    }

    public function test_el_mensaje_se_muestra_sin_marcar_matricula_ni_correo(): void
    {
        $this->existente();

        $this->followingRedirects()
            ->from(route('register'))
            ->post(route('register.submit'), $this->datos(['email' => 'otro@upemor.edu.mx']))
            ->assertSee(self::MENSAJE)
            ->assertDontSee('Este dato ya está registrado');
    }

    public function test_los_formularios_con_contrasena_incluyen_el_boton_para_verla(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-target="password"', false)->assertSee('Mostrar contraseña');
        $this->get(route('register'))->assertOk()
            ->assertSee('data-target="password"', false)
            ->assertSee('data-target="password_confirmation"', false);
        $this->get('/restablecer-contrasena/token?email=a@upemor.edu.mx')->assertRedirect();
    }
}
