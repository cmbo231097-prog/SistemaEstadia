<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionGruposTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $matricula, string $estatus = User::ESTATUS_ACTIVO): User
    {
        return User::create([
            'matricula' => $matricula,
            'nombre' => 'Nombre',
            'apellidos' => $matricula,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($rol),
            'estatus' => $estatus,
        ]);
    }

    private function asignatura(string $nombre = 'Bases de Datos'): Asignatura
    {
        return Asignatura::create([
            'clave_asignatura' => 'IID-'.random_int(1000, 9999),
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => $nombre,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => 5,
        ]);
    }

    private function periodo(): Periodo
    {
        return Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-12-31',
            'activo' => 1,
        ]);
    }

    private function grupo(Asignatura $asignatura, User $docente, string $id = '5A'): Grupo
    {
        return Grupo::create([
            'clave_grupo' => $id,
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
            'id_periodo' => $this->periodo()->id_periodo,
        ]);
    }

    private function datos(Asignatura $asignatura, User $docente, array $extra = []): array
    {
        return $extra + [
            'identificador' => '5A',
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
            'id_periodo' => $this->periodo()->id_periodo,
        ];
    }

    public function test_solo_el_lider_accede(): void
    {
        $this->get(route('admin.grupos.index'))->assertRedirect(route('login'));

        $this->actingAs($this->usuario(User::ROLE_PROFESOR, 'PROF001'))
            ->get(route('admin.grupos.index'))->assertForbidden();

        $this->actingAs($this->usuario(User::ROLE_LIDER, 'LIDER001'))
            ->get(route('admin.grupos.index'))->assertOk()->assertSee('Gestión de Grupos');
    }

    public function test_crea_grupo_con_docente_y_asignatura(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $asignatura = $this->asignatura();

        $this->actingAs($lider)->post(route('admin.grupos.store'), $this->datos($asignatura, $docente, ['identificador' => ' 5a ']))->assertRedirect(route('admin.grupos.index'));

        $this->assertDatabaseHas('grupos', [
            'clave_grupo' => '5A',
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
        ]);
    }

    public function test_rechaza_usuarios_que_no_son_docentes_activos(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE, 'EST001');
        $inactivo = $this->usuario(User::ROLE_PROFESOR, 'PROF002', User::ESTATUS_INACTIVO);
        $asignatura = $this->asignatura();

        foreach ([$estudiante, $inactivo] as $usuario) {
            $this->actingAs($lider)->post(route('admin.grupos.store'), $this->datos($asignatura, $usuario))->assertSessionHasErrors('id_docente');
        }

        $this->assertDatabaseCount('grupos', 0);
    }

    public function test_no_permite_duplicados_y_valida_campos(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $asignatura = $this->asignatura();
        $this->grupo($asignatura, $docente);

        $this->actingAs($lider)->post(route('admin.grupos.store'), $this->datos($asignatura, $docente))->assertSessionHasErrors('identificador');

        $this->actingAs($lider)->post(route('admin.grupos.store'), [
            'identificador' => '',
            'id_asignatura' => 999,
            'id_docente' => '',
            'id_periodo' => 999,
        ])->assertSessionHasErrors(['identificador', 'id_asignatura', 'id_docente', 'id_periodo']);
    }

    public function test_actualiza_grupo(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($asignatura, $docente);

        $this->actingAs($lider)->put(route('admin.grupos.update', $grupo), $this->datos($asignatura, $otro, ['identificador' => '5B']))->assertRedirect();

        $this->assertDatabaseHas('grupos', ['id_grupo' => $grupo->id_grupo, 'clave_grupo' => '5B', 'id_docente' => $otro->id_usuario]);
    }

    public function test_filtra_por_docente_y_asignatura(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $docenteA = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $docenteB = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $redes = $this->asignatura('Redes Avanzadas');
        $datos = $this->asignatura('Minería de Datos');
        $this->grupo($redes, $docenteA, 'GRUPO-RED');
        $this->grupo($datos, $docenteB, 'GRUPO-DAT');

        $this->actingAs($lider)->get(route('admin.grupos.index', ['docente' => $docenteA->id_usuario]))
            ->assertSee('GRUPO-RED')->assertDontSee('GRUPO-DAT');

        $this->actingAs($lider)->get(route('admin.grupos.index', ['asignatura' => $datos->id_asignatura]))
            ->assertSee('GRUPO-DAT')->assertDontSee('GRUPO-RED');
    }

    public function test_elimina_grupo(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $grupo = $this->grupo($this->asignatura(), $this->usuario(User::ROLE_PROFESOR, 'PROF001'));

        $this->actingAs($lider)->delete(route('admin.grupos.destroy', $grupo))->assertRedirect();

        $this->assertDatabaseMissing('grupos', ['id_grupo' => $grupo->id_grupo]);
    }

    public function test_asignatura_y_docente_con_grupos_quedan_protegidos(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER, 'LIDER001');
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $asignatura = $this->asignatura();
        $this->grupo($asignatura, $docente);

        $this->actingAs($lider)->delete(route('admin.asignaturas.destroy', $asignatura))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('asignaturas', ['id_asignatura' => $asignatura->id_asignatura]);

        $this->actingAs($lider)->delete(route('admin.usuarios.destroy', $docente));
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $docente->id_usuario, 'estatus' => User::ESTATUS_INACTIVO]);
    }
}
