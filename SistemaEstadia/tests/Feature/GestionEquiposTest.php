<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionEquiposTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $rol, ?string $matricula = null, string $estatus = User::ESTATUS_ACTIVO): User
    {
        $matricula ??= 'USR'.str_pad((string) ++$this->consecutivo, 4, '0', STR_PAD_LEFT);

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

    private function alumnos(int $cantidad): array
    {
        return array_map(fn () => $this->usuario(User::ROLE_ESTUDIANTE), range(1, $cantidad));
    }

    private function grupo(User $docente, string $clave = '9A', string $materia = 'Estructuras de Datos'): Grupo
    {
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.random_int(1000, 9999),
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => $materia,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => 5,
        ]);
        $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-12-31',
            'activo' => 1,
        ]);

        return Grupo::create([
            'clave_grupo' => $clave,
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
            'id_periodo' => $periodo->id_periodo,
        ]);
    }

    private function datos(Grupo $grupo, array $alumnos, array $extra = []): array
    {
        return $extra + [
            'nombre' => 'Equipo 1',
            'id_grupo' => $grupo->id_grupo,
            'integrantes' => array_map(fn ($a) => $a->id_usuario, $alumnos),
        ];
    }

    private function equipo(Grupo $grupo, array $alumnos, string $nombre = 'Equipo 1'): Equipo
    {
        $equipo = Equipo::create(['nombre_equipo' => $nombre, 'id_grupo' => $grupo->id_grupo]);
        $equipo->integrantes()->sync(array_map(fn ($a) => $a->id_usuario, $alumnos));

        return $equipo;
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('equipos.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE))->get(route('equipos.index'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_PROFESOR))->get(route('equipos.index'))
            ->assertOk()->assertSee('Administración de Equipos de Trabajo')->assertSee('Agregar integrante');
        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('equipos.index'))->assertOk();
    }

    public function test_equipos_aceptan_de_2_a_6_integrantes(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $grupo = $this->grupo($docente);
        $alumnos = $this->alumnos(9);

        foreach ([2, 6] as $n => $cantidad) {
            $this->actingAs($docente)->post(route('equipos.store'), $this->datos(
                $grupo,
                array_slice($alumnos, $n * 2, $cantidad),
                ['nombre' => "Equipo {$cantidad}"]
            ))->assertRedirect(route('equipos.index'));
        }

        $this->assertSame(2, Equipo::where('nombre_equipo', 'Equipo 2')->first()->integrantes()->count());
        $this->assertSame(6, Equipo::where('nombre_equipo', 'Equipo 6')->first()->integrantes()->count());
        $this->assertDatabaseHas('grupo_estudiantes', ['id_grupo' => $grupo->id_grupo, 'id_estudiante' => $alumnos[0]->id_usuario]);
    }

    public function test_rechaza_menos_de_2_o_mas_de_6_integrantes(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $grupo = $this->grupo($docente);
        $alumnos = $this->alumnos(7);

        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, array_slice($alumnos, 0, 1)))
            ->assertSessionHasErrors('integrantes');
        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, $alumnos))
            ->assertSessionHasErrors('integrantes');

        $this->assertDatabaseCount('equipos', 0);
    }

    public function test_valida_integrantes_repetidos_inactivos_y_ocupados(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $grupo = $this->grupo($docente);
        [$a, $b, $c] = $this->alumnos(3);
        $inactivo = $this->usuario(User::ROLE_ESTUDIANTE, null, User::ESTATUS_INACTIVO);

        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, [$a, $a]))
            ->assertSessionHasErrors('integrantes.1');
        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, [$a, $inactivo]))
            ->assertSessionHasErrors('integrantes.1');
        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, [$docente, $a]))
            ->assertSessionHasErrors('integrantes.0');

        $this->equipo($grupo, [$a, $b], 'Equipo A');
        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, [$b, $c], ['nombre' => 'Equipo B']))
            ->assertSessionHasErrors('integrantes');
        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($grupo, [$c, $this->alumnos(1)[0]], ['nombre' => 'Equipo A']))
            ->assertSessionHasErrors('nombre');
    }

    public function test_profesor_solo_usa_y_ve_sus_grupos(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $otro = $this->usuario(User::ROLE_PROFESOR);
        $propio = $this->grupo($docente, '9A', 'Materia Uno');
        $ajeno = $this->grupo($otro, '9B', 'Materia Dos');
        $alumnos = $this->alumnos(4);
        $this->equipo($propio, array_slice($alumnos, 0, 2), 'EQ-PROPIO');
        $eqAjeno = $this->equipo($ajeno, array_slice($alumnos, 2, 2), 'EQ-AJENO');

        $this->actingAs($docente)->post(route('equipos.store'), $this->datos($ajeno, $this->alumnos(2)))
            ->assertSessionHasErrors('id_grupo');
        $this->actingAs($docente)->get(route('equipos.index'))->assertSee('EQ-PROPIO')->assertDontSee('EQ-AJENO');
        $this->actingAs($docente)->delete(route('equipos.destroy', $eqAjeno))->assertForbidden();
        $this->actingAs($docente)->put(route('equipos.update', $eqAjeno), $this->datos($propio, $this->alumnos(2)))->assertForbidden();

        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('equipos.index'))
            ->assertSee('EQ-PROPIO')->assertSee('EQ-AJENO');
    }

    public function test_filtra_por_asignatura_grupo_y_busqueda(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $g1 = $this->grupo($docente, 'GRP-UNO', 'Materia Uno');
        $g2 = $this->grupo($docente, 'GRP-DOS', 'Materia Dos');
        $alumnos = $this->alumnos(4);
        $this->equipo($g1, array_slice($alumnos, 0, 2), 'EQ-UNO');
        $this->equipo($g2, array_slice($alumnos, 2, 2), 'EQ-DOS');

        $this->actingAs($docente)->get(route('equipos.index', ['asignatura' => $g1->id_asignatura]))
            ->assertSee('EQ-UNO')->assertDontSee('EQ-DOS');
        $this->actingAs($docente)->get(route('equipos.index', ['grupo' => $g2->id_grupo]))
            ->assertSee('EQ-DOS')->assertDontSee('EQ-UNO');
        $this->actingAs($docente)->get(route('equipos.index', ['q' => $alumnos[0]->matricula]))
            ->assertSee('EQ-UNO')->assertDontSee('EQ-DOS');
    }

    public function test_actualiza_cambia_numero_de_integrantes_y_elimina(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR);
        $grupo = $this->grupo($docente);
        $alumnos = $this->alumnos(5);
        $equipo = $this->equipo($grupo, array_slice($alumnos, 0, 3));

        $this->actingAs($docente)->put(route('equipos.update', $equipo), $this->datos($grupo, $alumnos, ['nombre' => 'Renombrado']))
            ->assertRedirect();
        $equipo->refresh();
        $this->assertSame('Renombrado', $equipo->nombre_equipo);
        $this->assertSame(5, $equipo->integrantes()->count());

        $this->actingAs($docente)->put(route('equipos.update', $equipo), $this->datos($grupo, array_slice($alumnos, 0, 2), ['nombre' => 'Renombrado']))
            ->assertRedirect();
        $this->assertSame(2, $equipo->integrantes()->count());

        $this->actingAs($docente)->delete(route('equipos.destroy', $equipo))->assertRedirect();
        $this->assertDatabaseMissing('equipos', ['id_equipo' => $equipo->id_equipo]);
        $this->assertDatabaseCount('equipo_estudiantes', 0);
    }
}
