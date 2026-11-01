<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionEvidenciasTest extends TestCase
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

    private function datos(Grupo $grupo, array $extra = []): array
    {
        return $extra + [
            'titulo' => 'Práctica 4: Grafos',
            'tipo_evidencia' => 'reporte',
            'nivel_retroalimentacion_ia' => 'exhaustiva',
            'entregables' => 'Introducción teórica y código documentado',
            'id_grupo' => $grupo->id_grupo,
            'fecha_limite' => now()->addWeek()->format('Y-m-d\TH:i'),
        ];
    }

    private function evidencia(User $docente, Grupo $grupo, string $titulo = 'Práctica 1'): Evidencia
    {
        $evidencia = Evidencia::create([
            'titulo' => $titulo,
            'tipo_evidencia' => 'reporte',
            'nivel_retroalimentacion_ia' => 'exhaustiva',
            'descripcion_entregables' => 'PDF con código',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->sync([$grupo->id_grupo]);

        return $evidencia;
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('evidencias.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE, 'EST001'))->get(route('evidencias.index'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_PROFESOR, 'PROF001'))->get(route('evidencias.index'))
            ->assertOk()->assertSee('Catálogo de Evidencias de Aprendizaje');
        $this->actingAs($this->usuario(User::ROLE_LIDER, 'LIDER001'))->get(route('evidencias.index'))->assertOk();
    }

    public function test_profesor_crea_evidencia_vinculada_a_su_grupo(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo))
            ->assertRedirect(route('evidencias.index'));

        $evidencia = Evidencia::firstOrFail();
        $this->assertSame($docente->id_usuario, $evidencia->id_docente);
        $this->assertSame('reporte', $evidencia->tipo_evidencia);
        $this->assertTrue($evidencia->grupos->contains($grupo));
    }

    public function test_no_permite_grupos_de_otro_docente_ni_datos_invalidos(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $ajeno = $this->grupo($otro, '9B', 'Redes');

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($ajeno))
            ->assertSessionHasErrors('id_grupo');

        $this->actingAs($docente)->post(route('evidencias.store'), [
            'titulo' => '', 'tipo_evidencia' => 'otro', 'nivel_retroalimentacion_ia' => 'x',
            'entregables' => '', 'id_grupo' => '', 'fecha_limite' => 'no-fecha',
        ])->assertSessionHasErrors(['titulo', 'tipo_evidencia', 'nivel_retroalimentacion_ia', 'entregables', 'id_grupo', 'fecha_limite']);

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($this->grupo($docente, '9C', 'BD'), [
            'fecha_limite' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('fecha_limite');

        $this->assertDatabaseCount('evidencias', 0);
    }

    public function test_profesor_solo_ve_y_modifica_sus_evidencias(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $propia = $this->evidencia($docente, $this->grupo($docente, '9A', 'Materia Uno'), 'EV-PROPIA');
        $ajena = $this->evidencia($otro, $this->grupo($otro, '9B', 'Materia Dos'), 'EV-AJENA');

        $this->actingAs($docente)->get(route('evidencias.index'))
            ->assertSee('EV-PROPIA')->assertDontSee('EV-AJENA');

        $this->actingAs($docente)->delete(route('evidencias.destroy', $ajena))->assertForbidden();
        $this->actingAs($docente)->put(route('evidencias.update', $ajena), $this->datos($propia->grupos->first()))->assertForbidden();

        $this->actingAs($this->usuario(User::ROLE_LIDER, 'LIDER001'))->get(route('evidencias.index'))
            ->assertSee('EV-PROPIA')->assertSee('EV-AJENA');
    }

    public function test_filtra_por_asignatura_y_grupo(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $g1 = $this->grupo($docente, 'GRP-UNO', 'Materia Uno');
        $g2 = $this->grupo($docente, 'GRP-DOS', 'Materia Dos');
        $this->evidencia($docente, $g1, 'EV-UNO');
        $this->evidencia($docente, $g2, 'EV-DOS');

        $this->actingAs($docente)->get(route('evidencias.index', ['asignatura' => $g1->id_asignatura]))
            ->assertSee('EV-UNO')->assertDontSee('EV-DOS');
        $this->actingAs($docente)->get(route('evidencias.index', ['grupo' => $g2->id_grupo]))
            ->assertSee('EV-DOS')->assertDontSee('EV-UNO');
        $this->actingAs($docente)->get(route('evidencias.index', ['q' => 'UNO']))
            ->assertSee('EV-UNO')->assertDontSee('EV-DOS');
    }

    public function test_actualiza_y_elimina_evidencia(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $g1 = $this->grupo($docente, '9A', 'Materia Uno');
        $g2 = $this->grupo($docente, '9B', 'Materia Dos');
        $evidencia = $this->evidencia($docente, $g1);

        $this->actingAs($docente)->put(route('evidencias.update', $evidencia), $this->datos($g2, [
            'titulo' => 'Nuevo título', 'tipo_evidencia' => 'proyecto',
        ]))->assertRedirect();

        $evidencia->refresh();
        $this->assertSame('Nuevo título', $evidencia->titulo);
        $this->assertSame('proyecto', $evidencia->tipo_evidencia);
        $this->assertSame([$g2->id_grupo], $evidencia->grupos()->pluck('grupos.id_grupo')->all());

        $this->actingAs($docente)->delete(route('evidencias.destroy', $evidencia))->assertRedirect();
        $this->assertDatabaseMissing('evidencias', ['id_evidencia' => $evidencia->id_evidencia]);
    }
}
