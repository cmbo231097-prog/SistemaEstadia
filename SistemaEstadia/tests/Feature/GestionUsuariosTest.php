<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $clave, string $matricula, array $extra = []): User
    {
        return User::create($extra + [
            'matricula' => $matricula,
            'nombre' => 'Nombre'.$matricula,
            'apellidos' => 'Apellido',
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function lider(): User
    {
        return $this->crearUsuario(User::ROLE_LIDER, 'LIDER001');
    }

    private function datosValidos(array $extra = []): array
    {
        return $extra + [
            'matricula' => 'itio230001',
            'nombre' => 'Roberto Carlos',
            'apellidos' => 'Morales Gómez',
            'correo_institucional' => 'RMorales@upemor.edu.mx',
            'rol' => 'profesor',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
        ];
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.usuarios.index'))->assertRedirect(route('login'));
    }

    public function test_profesor_y_estudiante_no_acceden_al_modulo(): void
    {
        $this->actingAs($this->crearUsuario(User::ROLE_PROFESOR, 'PROF001'))
            ->get(route('admin.usuarios.index'))->assertForbidden();

        $this->actingAs($this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001'))
            ->post(route('admin.usuarios.store'), $this->datosValidos())->assertForbidden();
    }

    public function test_lider_ve_catalogo_y_filtra_por_rol_estado_y_busqueda(): void
    {
        $lider = $this->lider();
        $this->crearUsuario(User::ROLE_PROFESOR, 'DOC001', ['nombre' => 'Irma']);
        $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001', ['nombre' => 'Alan']);
        $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI002', ['nombre' => 'Sofia', 'estatus' => User::ESTATUS_INACTIVO]);

        $this->actingAs($lider)->get(route('admin.usuarios.index'))
            ->assertOk()->assertSee('DOC001')->assertSee('ITI001')->assertDontSee('ITI002')
            ->assertSee('Todos los roles')->assertDontSee('Rol: Todos')->assertDontSee('Estado: Activos')
            ->assertSee('>Editar</button>', false)->assertSee('>Eliminar</button>', false);

        $this->actingAs($lider)->get(route('admin.usuarios.index', ['rol' => 'profesor']))
            ->assertSee('DOC001')->assertDontSee('ITI001');

        $this->actingAs($lider)->get(route('admin.usuarios.index', ['estado' => 'inactivo']))
            ->assertSee('ITI002')->assertDontSee('ITI001');

        $this->actingAs($lider)->get(route('admin.usuarios.index', ['q' => 'alan', 'estado' => 'todos']))
            ->assertSee('ITI001')->assertDontSee('DOC001')->assertDontSee('ITI002');
    }

    public function test_catalogo_se_pagina_de_cinco_en_cinco(): void
    {
        $lider = $this->lider();
        foreach (range(1, 6) as $n) {
            $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI00'.$n);
        }

        $this->actingAs($lider)->get(route('admin.usuarios.index'))
            ->assertSee('Mostrando 1 a 5 de 7 usuarios registrados')->assertSee('Siguiente');
    }

    public function test_alta_valida_crea_usuario_con_contrasena_hasheada(): void
    {
        $this->actingAs($this->lider())
            ->post(route('admin.usuarios.store'), $this->datosValidos())
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('status');

        $usuario = User::where('matricula', 'ITIO230001')->firstOrFail();
        $this->assertSame('rmorales@upemor.edu.mx', $usuario->correo_institucional);
        $this->assertSame('profesor', $usuario->role);
        $this->assertSame('activo', $usuario->estatus);
        $this->assertNotSame('secreta123', $usuario->password_hash);
        $this->assertTrue(password_verify('secreta123', $usuario->password_hash));
    }

    public function test_matricula_exige_formato_tres_letras_o_anio_posterior_a_2020_y_cuatro_digitos(): void
    {
        $lider = $this->lider();

        foreach (['ITI2023001', 'ITIO200001', 'ITIO230001X', 'ITO230001', 'IT1O230001', 'ITIO23001'] as $invalida) {
            $this->actingAs($lider)->post(route('admin.usuarios.store'), $this->datosValidos(['matricula' => $invalida]))
                ->assertSessionHasErrors('matricula');
        }

        foreach (['ITIO210001', 'DOCO260005', 'itio990123'] as $n => $valida) {
            $this->actingAs($lider)->post(route('admin.usuarios.store'), $this->datosValidos([
                'matricula' => $valida,
                'correo_institucional' => "valido{$n}@upemor.edu.mx",
            ]))->assertSessionHasNoErrors();
        }

        $this->assertNotNull(User::where('matricula', 'ITIO990123')->first());
    }

    public function test_lider_puede_asignar_rol_lider_y_el_rol_por_defecto_es_estudiante(): void
    {
        $lider = $this->lider();

        $this->actingAs($lider)->post(route('admin.usuarios.store'), $this->datosValidos(['rol' => 'lider']))
            ->assertSessionHasNoErrors();
        $this->assertSame('lider', User::where('matricula', 'ITIO230001')->first()->role);

        $this->actingAs($lider)->get(route('admin.usuarios.index'))
            ->assertSee('<option value="estudiante" selected>', false)
            ->assertDontSee('Seleccionar:')->assertDontSee('Teléfono');
    }

    public function test_alta_rechaza_datos_invalidos(): void
    {
        $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001');

        $this->actingAs($this->lider())->post(route('admin.usuarios.store'), $this->datosValidos([
            'matricula' => 'ITI001',
            'correo_institucional' => 'alguien@gmail.com',
            'rol' => 'administrador',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ]))->assertSessionHasErrors(['matricula', 'correo_institucional', 'rol', 'password']);

        $this->assertSame(2, User::count());
    }

    public function test_edicion_actualiza_datos_y_conserva_contrasena_si_va_vacia(): void
    {
        $lider = $this->lider();
        $alumno = $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001');
        $hashAnterior = $alumno->password_hash;

        $this->actingAs($lider)->put(route('admin.usuarios.update', $alumno), $this->datosValidos([
            'matricula' => 'ITI001',
            'correo_institucional' => 'iti001@upemor.edu.mx',
            'rol' => 'profesor',
            'estatus' => 'inactivo',
            'nombre' => 'Editado',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $alumno->refresh();
        $this->assertSame('Editado', $alumno->nombre);
        $this->assertSame('profesor', $alumno->role);
        $this->assertSame('inactivo', $alumno->estatus);
        $this->assertSame($hashAnterior, $alumno->password_hash);
    }

    public function test_correo_con_dominio_distinto_se_acepta_solo_si_no_cambia(): void
    {
        $lider = $this->lider();
        $demo = $this->crearUsuario(User::ROLE_PROFESOR, 'PROF001', ['correo_institucional' => 'profesor@estadia.com']);

        $base = $this->datosValidos(['matricula' => 'PROF001', 'estatus' => 'activo', 'password' => '', 'password_confirmation' => '']);

        $this->actingAs($lider)->put(route('admin.usuarios.update', $demo), array_merge($base, ['correo_institucional' => 'profesor@estadia.com']))
            ->assertSessionHasNoErrors();

        $this->actingAs($lider)->put(route('admin.usuarios.update', $demo), array_merge($base, ['correo_institucional' => 'otro@estadia.com']))
            ->assertSessionHasErrors('correo_institucional');
    }

    public function test_lider_no_puede_cambiar_su_propio_rol_ni_desactivarse(): void
    {
        $lider = $this->lider();

        $this->actingAs($lider)->put(route('admin.usuarios.update', $lider), $this->datosValidos([
            'matricula' => 'LIDER001',
            'correo_institucional' => 'lider001@upemor.edu.mx',
            'rol' => 'estudiante',
            'estatus' => 'inactivo',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertSessionHasNoErrors();

        $lider->refresh();
        $this->assertSame('lider', $lider->role);
        $this->assertSame('activo', $lider->estatus);
    }

    public function test_eliminacion_borra_usuario_y_protege_cuentas_lider(): void
    {
        $lider = $this->lider();
        $alumno = $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001');
        $otroLider = $this->crearUsuario(User::ROLE_LIDER, 'LIDER002');

        $this->actingAs($lider)->delete(route('admin.usuarios.destroy', $alumno))->assertSessionHas('status');
        $this->assertNull(User::where('matricula', 'ITI001')->first());

        $this->actingAs($lider)->delete(route('admin.usuarios.destroy', $lider))->assertSessionHas('error');
        $this->actingAs($lider)->delete(route('admin.usuarios.destroy', $otroLider))->assertSessionHas('error');
        $this->assertSame(2, User::count());
    }

    public function test_lider_alterna_el_estado_de_otro_usuario_desde_el_listado(): void
    {
        $lider = $this->lider();
        $profesor = $this->crearUsuario(User::ROLE_PROFESOR, 'PROF001');

        $this->actingAs($lider)->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee(route('admin.usuarios.estatus', $profesor), false)
            ->assertSee('Desactivar usuario');

        $this->actingAs($lider)->patch(route('admin.usuarios.estatus', $profesor))
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('status');
        $this->assertSame(User::ESTATUS_INACTIVO, $profesor->fresh()->estatus);

        $this->actingAs($lider)->patch(route('admin.usuarios.estatus', $profesor));
        $this->assertSame(User::ESTATUS_ACTIVO, $profesor->fresh()->estatus);
    }

    public function test_el_lider_no_puede_desactivar_su_propia_cuenta_ni_otros_roles_cambiar_estados(): void
    {
        $lider = $this->lider();

        $this->actingAs($lider)->patch(route('admin.usuarios.estatus', $lider))->assertSessionHas('error');
        $this->assertSame(User::ESTATUS_ACTIVO, $lider->fresh()->estatus);

        $profesor = $this->crearUsuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->crearUsuario(User::ROLE_ESTUDIANTE, 'ITI001');
        $this->actingAs($profesor)->patch(route('admin.usuarios.estatus', $otro))->assertForbidden();
        $this->assertSame(User::ESTATUS_ACTIVO, $otro->fresh()->estatus);
    }
}
