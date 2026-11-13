<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionDocente;
use App\Models\EvaluacionDocenteCriterio;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use App\Services\RubricaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionDocenteTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $clave, string $matricula): User
    {
        return User::create([
            'matricula' => $matricula,
            'nombre' => 'Usuario',
            'apellidos' => $matricula,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function escenario(?User $docente = null): array
    {
        $docente ??= $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-0001',
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => 'Estructuras de Datos',
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => 5,
        ]);
        $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-12-31',
            'activo' => true,
        ]);
        $grupo = Grupo::create([
            'clave_grupo' => '9A',
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
            'id_periodo' => $periodo->id_periodo,
        ]);
        $equipo = Equipo::create(['nombre_equipo' => 'Equipo 1', 'id_grupo' => $grupo->id_grupo]);
        $evidencia = Evidencia::create([
            'titulo' => 'Reporte de árboles',
            'tipo_evidencia' => 'reporte',
            'descripcion_entregables' => 'Explicar la implementación.',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        $evidencia->criteriosRubrica()->create([
            'id_criterio' => 1,
            'nombre_criterio' => 'Contenido',
            'descripcion_criterio' => 'Calidad del contenido.',
            'puntaje_maximo' => 100,
        ]);

        $documento = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => $equipo->id_equipo,
            'nombre_archivo_original' => 'entrega.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'ruta_archivo_sanitizado' => 'corpus/sanitizado.pdf',
            'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);

        return compact('docente', 'documento', 'evidencia', 'grupo');
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'criterios' => [1 => ['calificacion' => 85, 'retroalimentacion' => 'Falta justificar la complejidad.']],
            'comentarios_cualitativos' => 'Buen desarrollo.',
            'tiempo_evaluacion' => '12:30',
        ], $extra);
    }

    /** Sustituye la rúbrica del escenario por tres criterios que suman 100 (40, 40 y 20). */
    private function rubricaDeTres(Evidencia $evidencia): void
    {
        $evidencia->criteriosRubrica()->delete();
        foreach ([[1, 'Contenido', 40], [2, 'Redacción', 40], [3, 'Formato', 20]] as [$id, $nombre, $puntos]) {
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => $id,
                'nombre_criterio' => $nombre,
                'descripcion_criterio' => $nombre,
                'puntaje_maximo' => $puntos,
            ]);
        }
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('evaluacion-docente.index'))->assertRedirect(route('login'));

        $estudiante = $this->usuario(User::ROLE_ESTUDIANTE, '2301001');
        $this->actingAs($estudiante)->get(route('evaluacion-docente.index'))->assertForbidden();

        $lider = $this->usuario(User::ROLE_LIDER, 'LID001');
        $this->actingAs($lider)->get(route('evaluacion-docente.index'))->assertOk();
    }

    public function test_profesor_ve_documentos_sanitizados_de_su_evidencia(): void
    {
        $escenario = $this->escenario();
        CorpusDocumento::create([
            'id_evidencia' => $escenario['evidencia']->id_evidencia,
            'id_equipo' => Equipo::create(['nombre_equipo' => 'Equipo 2', 'id_grupo' => $escenario['grupo']->id_grupo])->id_equipo,
            'nombre_archivo_original' => 'pendiente.pdf',
            'ruta_archivo_original' => 'corpus/pendiente.pdf',
            'estado_sanitizacion' => CorpusDocumento::PENDIENTE,
        ]);

        $this->actingAs($escenario['docente'])
            ->get(route('evaluacion-docente.index'))
            ->assertOk()
            ->assertSee('Captura de Evaluación Docente')
            ->assertSee('Equipo 1')
            ->assertDontSee('Equipo 2');
    }

    public function test_muestra_la_rubrica_de_la_actividad_junto_al_pdf(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['docente'])
            ->get(route('evaluacion-docente.index'))
            ->assertOk()
            ->assertSee('Rúbrica · Reporte de árboles')
            ->assertSee('Calidad del contenido.')
            ->assertSee('Tu evaluación')
            ->assertDontSee("has('archivo')", false);
    }

    public function test_sin_rubrica_no_se_puede_evaluar_la_practica(): void
    {
        $escenario = $this->escenario();
        $escenario['evidencia']->criteriosRubrica()->delete();

        $this->actingAs($escenario['docente'])
            ->get(route('evaluacion-docente.index'))
            ->assertOk()
            ->assertSee('aún no tiene rúbrica')
            ->assertDontSee('Tu evaluación')
            ->assertDontSee('<iframe', false);

        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos())
            ->assertSessionHas('error');
        $this->assertDatabaseCount('evaluaciones_docente', 0);
    }

    public function test_guarda_calificacion_comentarios_y_tiempo_y_permite_corregir(): void
    {
        $escenario = $this->escenario();
        $documento = $escenario['documento'];

        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $documento), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('evaluaciones_docente', [
            'id_corpus' => $documento->id_corpus,
            'id_docente' => $escenario['docente']->id_usuario,
            'calificacion_global' => 85,
            'tiempo_evaluacion_minutos' => 12.5,
        ]);

        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $documento), $this->datos(['criterios' => [1 => ['calificacion' => 90]]]))
            ->assertSessionHas('status');

        $this->assertSame(1, EvaluacionDocente::count());
        $this->assertSame('90.00', EvaluacionDocente::first()->calificacion_global);
        $this->assertSame(1, EvaluacionDocenteCriterio::count());
    }

    public function test_califica_rubro_por_rubro_y_la_retroalimentacion_es_opcional(): void
    {
        $escenario = $this->escenario();
        $this->rubricaDeTres($escenario['evidencia']);

        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos([
                'comentarios_cualitativos' => null,
                'criterios' => [
                    1 => ['calificacion' => 35.5, 'retroalimentacion' => '  Buen análisis.  '],
                    2 => ['calificacion' => 30, 'retroalimentacion' => ''],
                    3 => ['calificacion' => 20],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $evaluacion = EvaluacionDocente::firstOrFail();
        $this->assertSame('85.50', $evaluacion->calificacion_global);
        $this->assertNull($evaluacion->comentarios_cualitativos);
        $this->assertSame(
            ['35.50' => 'Buen análisis.', '30.00' => null, '20.00' => null],
            $evaluacion->criterios->pluck('retroalimentacion', 'calificacion')->all()
        );

        $this->actingAs($escenario['docente'])
            ->get(route('evaluacion-docente.index'))
            ->assertOk()
            ->assertSee('Retroalimentación de este rubro (opcional)')
            ->assertSee('Buen análisis.')
            ->assertSee('Calificación total');
    }

    public function test_cada_rubro_exige_calificacion_dentro_de_su_puntaje_maximo(): void
    {
        $escenario = $this->escenario();
        $this->rubricaDeTres($escenario['evidencia']);
        $ruta = route('evaluacion-docente.store', $escenario['documento']);
        $completo = [1 => ['calificacion' => 40], 2 => ['calificacion' => 40], 3 => ['calificacion' => 20]];

        $this->actingAs($escenario['docente'])
            ->post($ruta, $this->datos(['criterios' => [1 => ['calificacion' => 41]] + $completo]))
            ->assertSessionHasErrors('criterios.1.calificacion');
        $this->post($ruta, $this->datos(['criterios' => [3 => ['calificacion' => -1]] + $completo]))
            ->assertSessionHasErrors('criterios.3.calificacion');
        $this->post($ruta, $this->datos(['criterios' => [1 => ['calificacion' => 40], 2 => ['calificacion' => 40]]]))
            ->assertSessionHasErrors('criterios.3.calificacion');
        $this->post($ruta, $this->datos(['criterios' => $completo, 'comentarios_cualitativos' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors('comentarios_cualitativos');
        $this->post($ruta, $this->datos(['criterios' => [2 => ['calificacion' => 1, 'retroalimentacion' => str_repeat('a', 2001)]] + $completo]))
            ->assertSessionHasErrors('criterios.2.retroalimentacion');

        $this->assertDatabaseCount('evaluaciones_docente', 0);
        $this->assertDatabaseCount('evaluacion_docente_criterios', 0);
    }

    public function test_el_tiempo_se_captura_en_minutos_y_segundos_y_se_muestra_igual(): void
    {
        $escenario = $this->escenario();
        $ruta = route('evaluacion-docente.store', $escenario['documento']);

        foreach (['1.90', '1:75', 'abc', '1:5', '12'] as $invalido) {
            $this->actingAs($escenario['docente'])->post($ruta, $this->datos(['tiempo_evaluacion' => $invalido]))
                ->assertSessionHasErrors('tiempo_evaluacion');
        }

        $this->post($ruta, $this->datos(['tiempo_evaluacion' => '01:50']))->assertSessionHasNoErrors();
        $this->assertSame('1.83', EvaluacionDocente::firstOrFail()->tiempo_evaluacion_minutos);

        $this->get(route('evaluacion-docente.index', ['documento' => $escenario['documento']->id_corpus]))
            ->assertOk()
            ->assertSee('value="01:50"', false);
    }

    public function test_valida_tiempo_y_ya_no_exige_comentarios_generales(): void
    {
        $escenario = $this->escenario();
        $ruta = route('evaluacion-docente.store', $escenario['documento']);

        $this->actingAs($escenario['docente'])->post($ruta, $this->datos(['tiempo_evaluacion' => '00:00']))
            ->assertSessionHasErrors('tiempo_evaluacion');
        $this->assertDatabaseCount('evaluaciones_docente', 0);

        $this->post($ruta, $this->datos(['comentarios_cualitativos' => '']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('evaluaciones_docente', 1);
    }

    public function test_la_rubrica_queda_bloqueada_una_vez_calificada_por_rubro(): void
    {
        $escenario = $this->escenario();
        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos());

        $this->assertTrue(app(RubricaService::class)->estaBloqueada($escenario['evidencia']));
        $this->actingAs($escenario['docente'])
            ->put(route('evidencias.rubrica.update', $escenario['evidencia']), [
                'criterios' => [['nombre_criterio' => 'Otro', 'puntaje_maximo' => 100, 'evaluable_ia' => '1']],
            ])
            ->assertStatus(409);
        $this->assertSame(['Contenido'], $escenario['evidencia']->criteriosRubrica()->pluck('nombre_criterio')->all());
    }

    public function test_un_profesor_no_evalua_documentos_ajenos_ni_sin_sanitizar(): void
    {
        $escenario = $this->escenario();
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');

        $this->actingAs($otro)
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos())
            ->assertForbidden();

        $escenario['documento']->update(['estado_sanitizacion' => CorpusDocumento::PENDIENTE]);
        $this->actingAs($escenario['docente'])
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos())
            ->assertStatus(422);

        $this->assertDatabaseCount('evaluaciones_docente', 0);
    }

    public function test_el_lider_puede_evaluar_documentos_de_cualquier_profesor(): void
    {
        $escenario = $this->escenario();
        $lider = $this->usuario(User::ROLE_LIDER, 'LID001');

        $this->actingAs($lider)
            ->post(route('evaluacion-docente.store', $escenario['documento']), $this->datos())
            ->assertSessionHas('status');

        $this->assertDatabaseHas('evaluaciones_docente', ['id_docente' => $lider->id_usuario]);
    }
}
