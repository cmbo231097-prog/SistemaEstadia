<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecuperacionContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $estatus = User::ESTATUS_ACTIVO): User
    {
        return User::create([
            'matricula' => 'ITI2023001',
            'nombre' => 'Ana',
            'apellidos' => 'Alumna',
            'correo_institucional' => 'ana@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave(User::ROLE_ESTUDIANTE),
            'estatus' => $estatus,
        ]);
    }

    public function test_la_respuesta_es_identica_exista_o_no_el_correo_y_solo_se_envia_a_cuentas_activas(): void
    {
        Notification::fake();
        $usuario = $this->usuario();

        $respuestas = [];
        foreach (['ana@upemor.edu.mx', 'nadie@upemor.edu.mx'] as $correo) {
            $respuesta = $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $correo])
                ->assertRedirect(route('password.request'))
                ->assertSessionDoesntHaveErrors();
            $respuestas[] = session('status');
        }

        $this->assertSame($respuestas[0], $respuestas[1]);
        $this->assertStringNotContainsString('ana@', $respuestas[0]);
        Notification::assertSentToTimes($usuario, RestablecerContrasena::class, 1);
        Notification::assertCount(1);
    }

    public function test_una_cuenta_inactiva_recibe_la_misma_respuesta_y_no_se_le_envia_enlace(): void
    {
        Notification::fake();
        $this->usuario(User::ESTATUS_INACTIVO);

        $this->post(route('password.email'), ['email' => 'ana@upemor.edu.mx'])
            ->assertSessionHas('enviado')
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
    }
}
