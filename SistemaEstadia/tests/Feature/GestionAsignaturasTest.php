<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GestionAsignaturasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $matricula): User
    {
        return User::create([
            'matricula' => $matricula,
            'nombre' => 'Nombre',
            'apellidos' => $matricula,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($rol),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function lider(): User
    {
        return $this->usuario(User::ROLE_LIDER, 'LIDER001');
    }

    private function datosAsignatura(array $extra = []): array
    {
        return $extra + [
            'clave_asignatura' => 'ITI-0001',
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => 'Procesamiento de Lenguaje Natural',
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => '9',
        ];
    }

    private function datosSolicitud(array $extra = []): array
    {
        $datos = $this->datosAsignatura($extra);

        return [
            'modelo_educativo' => $datos['modelo_educativo'],
            'nombre' => $datos['nombre_asignatura'],
            'programa_educativo' => $datos['programa_educativo'],
            'cuatrimestre' => $datos['cuatrimestre'],
            'clave' => $datos['clave_asignatura'],
        ];
    }

    public function test_modelo_usa_el_esquema_existente_de_asignaturas(): void
    {
        $this->assertTrue(Schema::hasColumns('asignaturas', [
            'id_asignatura',
            'clave_asignatura',
            'nombre_asignatura',
            'programa_educativo',
            'cuatrimestre',
            'modelo_educativo',
        ]));
        $this->assertFalse(Schema::hasColumn('asignaturas', 'clave'));
        $this->assertFalse(Schema::hasColumn('asignaturas', 'nombre'));
        $this->assertFalse((new Asignatura())->usesTimestamps());
    }

    public function test_solo_lider_puede_acceder_al_catalogo(): void
    {
        $this->get(route('admin.asignaturas.index'))->assertRedirect(route('login'));

        $this->actingAs($this->usuario(User::ROLE_PROFESOR, 'PROF001'))
            ->get(route('admin.asignaturas.index'))->assertForbidden();
    }

    public function test_catalogo_lista_filtros_y_pagina_asignaturas(): void
    {
        $lider = $this->lider();
        Asignatura::create($this->datosAsignatura([
            'clave_asignatura' => 'IID-9001',
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => 9,
        ]));
        Asignatura::create($this->datosAsignatura([
            'clave_asignatura' => 'IBT-6001',
            'nombre_asignatura' => 'Bioestadística y Modelado',
            'programa_educativo' => 'Ingeniería en Biotecnología',
            'cuatrimestre' => 6,
        ]));
        Asignatura::create($this->datosAsignatura([
            'clave_asignatura' => 'IIN-ANT-0001',
            'modelo_educativo' => Asignatura::MODELO_ANTERIOR,
            'programa_educativo' => 'Ingeniería Industrial',
            'nombre_asignatura' => 'Materia del plan industrial anterior',
        ]));

        $this->actingAs($lider)->get(route('admin.asignaturas.index'))
            ->assertOk()
            ->assertSee('IID-9001')->assertSee('IBT-6001')->assertSee('Nueva Asignatura')
            ->assertSee('Todas las carreras')->assertSee('Todos los cuatrimestres')
            ->assertSee('Modelo educativo anterior')
            ->assertSee('Modelo educativo actual')
            ->assertSee('IIF — Ingeniería Financiera')
            ->assertSee('LAG / LAE — Licenciatura en Administración y Gestión / Licenciatura en Administración de Empresas')
            ->assertSee('ITI — Ingeniería en Tecnologías de la Información')
            ->assertSee('IET — Ingeniería en Electrónica y Telecomunicaciones')
            ->assertSee('ITA — Ingeniería en Tecnología Ambiental')
            ->assertSee('IIN — Ingeniería Industrial')
            ->assertSee('IFI — Ingeniería Financiera')
            ->assertSee('LAD — Licenciatura en Administración')
            ->assertSee('IID — Ingeniería en Tecnologías de la Información e Innovación Digital')
            ->assertSee('ISE — Ingeniería en Sistemas Electrónicos')
            ->assertSee('IBT — Ingeniería en Biotecnología')
            ->assertSee('IAS — Ingeniería Ambiental y Sustentabilidad');

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['programa' => 'Ingeniería en Biotecnología']));
        $this->assertCount(0, $response->viewData('asignaturas'));

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['programa' => 'actual|IBT']));
        $this->assertSame(['IBT-6001'], $response->viewData('asignaturas')->pluck('clave_asignatura')->all());

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['cuatrimestre' => 9]));
        $this->assertSame(['IID-9001', 'IIN-ANT-0001'], $response->viewData('asignaturas')->pluck('clave_asignatura')->all());

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['q' => 'bioestadística']));
        $this->assertSame(['IBT-6001'], $response->viewData('asignaturas')->pluck('clave_asignatura')->all());

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['modelo_educativo' => 'anterior']));
        $this->assertSame(['IIN-ANT-0001'], $response->viewData('asignaturas')->pluck('clave_asignatura')->all());

        $response = $this->actingAs($lider)->get(route('admin.asignaturas.index', ['programa' => 'anterior|IIN']));
        $this->assertSame(['IIN-ANT-0001'], $response->viewData('asignaturas')->pluck('clave_asignatura')->all());

        $this->actingAs($lider)->post(route('admin.asignaturas.store'), $this->datosSolicitud([
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información',
        ]))->assertSessionHasErrors(['programa_educativo']);
    }

    public function test_crear_genera_clave_y_persiste_datos(): void
    {
        $this->actingAs($this->lider())
            ->post(route('admin.asignaturas.store'), $this->datosSolicitud(['clave_asignatura' => 'CLAVE-MANUAL']))
            ->assertRedirect(route('admin.asignaturas.index'))
            ->assertSessionHasNoErrors();

        $asignatura = Asignatura::where('clave_asignatura', 'IID-0001')->firstOrFail();
        $this->assertSame(9, $asignatura->cuatrimestre);
        $this->assertSame(Asignatura::MODELO_ACTUAL, $asignatura->modelo_educativo);
        $this->assertSame('Ingeniería en Tecnologías de la Información e Innovación Digital', $asignatura->programa_educativo);
    }

    public function test_crear_asignatura_del_modelo_anterior_conserva_carrera_historica_y_clave(): void
    {
        $this->actingAs($this->lider())
            ->post(route('admin.asignaturas.store'), $this->datosSolicitud([
                'modelo_educativo' => Asignatura::MODELO_ANTERIOR,
                'programa_educativo' => 'Ingeniería en Tecnologías de la Información',
            ]))
            ->assertRedirect(route('admin.asignaturas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asignaturas', [
            'clave_asignatura' => 'ITI-0001',
            'modelo_educativo' => Asignatura::MODELO_ANTERIOR,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información',
        ]);
    }

    public function test_validacion_rechaza_carrera_no_registrada_y_cuatrimestre_fuera_de_rango(): void
    {
        $this->actingAs($this->lider())->post(route('admin.asignaturas.store'), $this->datosSolicitud([
            'programa_educativo' => 'Carrera no registrada',
            'cuatrimestre' => 11,
        ]))->assertSessionHasErrors(['programa_educativo', 'cuatrimestre']);
    }

    public function test_editar_actualiza_asignatura(): void
    {
        $lider = $this->lider();
        $asignatura = Asignatura::create($this->datosAsignatura(['clave_asignatura' => 'IID-0001']));

        $this->actingAs($lider)->put(route('admin.asignaturas.update', $asignatura), $this->datosSolicitud([
            'clave_asignatura' => 'CLAVE-MANUAL',
            'modelo_educativo' => Asignatura::MODELO_ANTERIOR,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información',
            'nombre_asignatura' => 'Arquitectura y Desarrollo Web',
            'cuatrimestre' => 7,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asignaturas', [
            'id_asignatura' => $asignatura->id_asignatura,
            'clave_asignatura' => 'ITI-0001',
            'nombre_asignatura' => 'Arquitectura y Desarrollo Web',
            'modelo_educativo' => Asignatura::MODELO_ANTERIOR,
            'cuatrimestre' => 7,
        ]);
    }

    public function test_eliminar_asignatura_sin_relaciones_y_bloquear_si_tiene_grupo(): void
    {
        $lider = $this->lider();
        $asignatura = Asignatura::create($this->datosAsignatura(['clave_asignatura' => 'IID-0001']));

        $this->actingAs($lider)->delete(route('admin.asignaturas.destroy', $asignatura))
            ->assertSessionHas('status');
        $this->assertDatabaseMissing('asignaturas', ['id_asignatura' => $asignatura->id_asignatura]);

        Schema::create('grupos_prueba', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_asignatura');
            $table->foreign('id_asignatura')->references('id_asignatura')->on('asignaturas');
        });

        $asignatura = Asignatura::create($this->datosAsignatura(['clave_asignatura' => 'IID-0002']));
        Schema::getConnection()->table('grupos_prueba')->insert(['id_asignatura' => $asignatura->id_asignatura]);

        $this->actingAs($lider)->delete(route('admin.asignaturas.destroy', $asignatura))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('asignaturas', ['id_asignatura' => $asignatura->id_asignatura]);
    }
}
