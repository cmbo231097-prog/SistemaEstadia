<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionIa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    /** Rúbrica válida: suma 100 puntos y un criterio no evaluable por la IA. */
    private function criterios(): array
    {
        return [
            ['nombre_criterio' => 'Portada', 'descripcion_criterio' => 'Portada completa.', 'puntaje_maximo' => '2.5', 'evaluable_ia' => '1'],
            ['nombre_criterio' => 'Contenido', 'descripcion_criterio' => 'Contenido correcto.', 'puntaje_maximo' => '95', 'evaluable_ia' => '1'],
            ['nombre_criterio' => 'Desempeño', 'descripcion_criterio' => 'Trabajo del equipo.', 'puntaje_maximo' => '2.5', 'evaluable_ia' => '0'],
        ];
    }

    private function datos(Grupo $grupo, array $extra = []): array
    {
        return $extra + [
            'titulo' => 'Práctica 4: Grafos',
            'codigo' => 'GDS-EP4',
            'tipo_evidencia' => 'reporte',
            'id_grupo' => $grupo->id_grupo,
            'fecha_limite' => now()->addWeek()->format('Y-m-d\TH:i'),
            'criterios' => $this->criterios(),
        ];
    }

    private function evidencia(User $docente, Grupo $grupo, string $titulo = 'Práctica 1'): Evidencia
    {
        $evidencia = Evidencia::create([
            'titulo' => $titulo,
            'tipo_evidencia' => 'reporte',
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

    public function test_el_codigo_es_obligatorio_con_formato_valido_y_unico_por_docente(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $grupo = $this->grupo($docente);
        $grupoOtro = $this->grupo($otro, '9B', 'Redes');

        foreach (['', '   ', 'GDS EP1', 'GDS--EP1'] as $invalido) {
            $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['codigo' => $invalido]))
                ->assertSessionHasErrors('codigo');
        }
        $this->assertSame(0, Evidencia::count());

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['codigo' => 'gds-ep1']))
            ->assertSessionHasNoErrors();
        $this->assertSame('GDS-EP1', Evidencia::firstOrFail()->codigo);

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['codigo' => 'GDS-EP1']))
            ->assertSessionHasErrors('codigo');

        // Otro docente sí puede usar el mismo código.
        $this->actingAs($otro)->post(route('evidencias.store'), $this->datos($grupoOtro, ['codigo' => 'GDS-EP1']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Evidencia::count());

        // Al editar se conserva su propio código, pero no puede quedar vacío.
        $propia = Evidencia::where('id_docente', $docente->id_usuario)->firstOrFail();
        $this->actingAs($docente)->put(route('evidencias.update', $propia), $this->datos($grupo, ['codigo' => 'GDS-EP1']))
            ->assertSessionHasNoErrors();
        $this->actingAs($docente)->put(route('evidencias.update', $propia), $this->datos($grupo, ['codigo' => '']))
            ->assertSessionHasErrors('codigo');
    }

    public function test_no_permite_grupos_de_otro_docente_ni_datos_invalidos(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $ajeno = $this->grupo($otro, '9B', 'Redes');

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($ajeno))
            ->assertSessionHasErrors('id_grupo');

        $this->actingAs($docente)->post(route('evidencias.store'), [
            'titulo' => '', 'tipo_evidencia' => 'otro',
            'id_grupo' => '', 'fecha_limite' => 'no-fecha',
        ])->assertSessionHasErrors(['titulo', 'tipo_evidencia', 'id_grupo', 'fecha_limite', 'criterios']);

        $this->assertDatabaseCount('evidencias', 0);
    }

    public function test_admite_fechas_de_entrega_anteriores_a_hoy_al_crear_y_editar(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);
        $pasada = now()->subMonths(2)->format('Y-m-d\TH:i');

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['fecha_limite' => $pasada]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('evidencias.index'));

        $evidencia = Evidencia::firstOrFail();
        $this->assertSame($pasada, $evidencia->fecha_limite->format('Y-m-d\TH:i'));

        $otra = now()->subYear()->format('Y-m-d\TH:i');
        $this->actingAs($docente)->put(route('evidencias.update', $evidencia), $this->datos($grupo, ['fecha_limite' => $otra]))
            ->assertSessionHasNoErrors();
        $this->assertSame($otra, $evidencia->fresh()->fecha_limite->format('Y-m-d\TH:i'));
    }

    public function test_el_formulario_ya_no_pide_nivel_de_retroalimentacion_ni_entregables(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');

        $this->actingAs($docente)->get(route('evidencias.index'))
            ->assertOk()
            ->assertDontSee('Retroalimentación de la IA')
            ->assertDontSee('nivel_retroalimentacion_ia', false)
            ->assertDontSee('Entregables requeridos')
            ->assertDontSee('name="entregables"', false)
            ->assertSee('Rúbrica de evaluación')
            ->assertSee('id="rArchivo"', false)
            ->assertSee('accept=".xlsx,.xls,.csv"', false);
    }

    public function test_no_se_puede_crear_una_evidencia_sin_rubrica(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);

        $datos = $this->datos($grupo);
        unset($datos['criterios']);

        $this->actingAs($docente)->post(route('evidencias.store'), $datos)
            ->assertSessionHasErrors('criterios');

        $this->assertDatabaseCount('evidencias', 0);
        $this->assertDatabaseCount('criterios_rubrica', 0);
    }

    public function test_crea_la_evidencia_junto_con_su_rubrica(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('evidencias.index'));

        $evidencia = Evidencia::firstOrFail();
        $this->assertSame(3, $evidencia->criteriosRubrica()->count());
        $this->assertEquals(100.0, (float) $evidencia->criteriosRubrica()->sum('puntaje_maximo'));
        $this->assertSame(2, $evidencia->criteriosEvaluablesIa()->count());
        $this->assertNull($evidencia->descripcion_entregables);

        $this->actingAs($docente)->get(route('evidencias.index'))
            ->assertSee('3 criterios · 100 pts');
    }

    public function test_la_rubrica_solo_necesita_criterio_puntaje_y_si_es_evaluable_desde_el_producto(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);
        $criterios = [
            ['nombre_criterio' => 'Contenido', 'puntaje_maximo' => '97.5', 'evaluable_ia' => '1'],
            ['nombre_criterio' => 'Desempeño', 'puntaje_maximo' => '2.5', 'evaluable_ia' => '0'],
        ];

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['criterios' => $criterios]))
            ->assertSessionHasNoErrors();

        $evidencia = Evidencia::firstOrFail();
        $this->assertSame(['Contenido', 'Desempeño'], $evidencia->criteriosRubrica()->orderBy('id_criterio')->pluck('nombre_criterio')->all());
        // Sin descripción capturada, el nombre del criterio describe lo que se evalúa.
        $this->assertSame('Contenido', $evidencia->criteriosRubrica()->orderBy('id_criterio')->first()->descripcion_criterio);
        $this->assertSame(['Contenido'], $evidencia->criteriosEvaluablesIa()->pluck('nombre_criterio')->all());

        $this->actingAs($docente)->get(route('evidencias.index'))
            ->assertSee('Puntaje máximo')
            ->assertSee('Evaluable desde el producto')
            ->assertDontSee('Descripción de lo esperado');
    }

    public function test_la_rubrica_debe_sumar_cien_y_se_revierte_toda_la_alta_si_falla(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);
        $criterios = $this->criterios();
        $criterios[1]['puntaje_maximo'] = '90';

        $this->actingAs($docente)->post(route('evidencias.store'), $this->datos($grupo, ['criterios' => $criterios]))
            ->assertSessionHasErrors('criterios');

        $this->assertDatabaseCount('evidencias', 0);
    }

    public function test_al_editar_se_reemplaza_la_rubrica_y_sigue_siendo_obligatoria(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);
        $evidencia = $this->evidencia($docente, $grupo);

        $sinRubrica = $this->datos($grupo);
        unset($sinRubrica['criterios']);
        $this->actingAs($docente)->put(route('evidencias.update', $evidencia), $sinRubrica)
            ->assertSessionHasErrors('criterios');

        $nueva = [['nombre_criterio' => 'Único', 'descripcion_criterio' => 'Todo el trabajo.', 'puntaje_maximo' => '100', 'evaluable_ia' => '1']];
        $this->actingAs($docente)->put(route('evidencias.update', $evidencia), $this->datos($grupo, ['criterios' => $nueva]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Único'], $evidencia->criteriosRubrica()->pluck('nombre_criterio')->all());
    }

    public function test_una_rubrica_con_evaluaciones_de_ia_se_conserva_al_editar_la_evidencia(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $grupo = $this->grupo($docente);
        $evidencia = $this->evidencia($docente, $grupo);
        $evidencia->criteriosRubrica()->create([
            'id_criterio' => 1, 'nombre_criterio' => 'Original', 'descripcion_criterio' => 'Texto.', 'puntaje_maximo' => 100,
        ]);
        $equipo = Equipo::create(['nombre_equipo' => 'Equipo 1', 'id_grupo' => $grupo->id_grupo]);
        $documento = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => $equipo->id_equipo,
            'nombre_archivo_original' => 'entrega.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'estado_sanitizacion' => CorpusDocumento::PENDIENTE,
        ]);
        EvaluacionIa::create([
            'id_corpus' => $documento->id_corpus,
            'modelo_llm' => 'gemini',
            'calificacion_global' => 90,
            'retroalimentacion_cualitativa' => 'Bien.',
            'tiempo_procesamiento_segundos' => 3,
            'fecha_procesamiento' => now(),
        ]);

        $datos = $this->datos($grupo, ['titulo' => 'Título nuevo']);
        unset($datos['criterios']);
        $this->actingAs($docente)->put(route('evidencias.update', $evidencia), $datos)->assertSessionHasNoErrors();

        $this->assertSame('Título nuevo', $evidencia->fresh()->titulo);
        $this->assertSame(['Original'], $evidencia->criteriosRubrica()->pluck('nombre_criterio')->all());
    }

    public function test_lee_el_archivo_de_rubricas_para_precargar_el_formulario(): void
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $csv = UploadedFile::fake()->createWithContent('rubricas.csv', implode("\n", [
            'asignatura,tipo_evidencia,orden_criterio,id_criterio,criterio,puntaje_maximo,evaluable_desde_el_producto',
            'GDS,EP1,1,GDS01,Portada y tabla de contenido,2.5,si',
            'GDS,EP1,2,GDS02,Contenido,97.5,si',
            'FPTI,EP4,1,FPT01,Resumen,100,si',
        ]));

        $this->actingAs($docente)->postJson(route('evidencias.rubrica.leer'), ['archivo' => $csv])
            ->assertOk()
            ->assertJsonCount(2, 'rubricas')
            ->assertJsonPath('rubricas.0.codigo', 'GDS-EP1')
            ->assertJsonPath('rubricas.0.criterios.0.nombre_criterio', 'Portada y tabla de contenido')
            ->assertJsonPath('rubricas.0.criterios.1.puntaje_maximo', 97.5)
            ->assertJsonPath('rubricas.1.codigo', 'FPTI-EP4');

        $this->actingAs($docente)->postJson(route('evidencias.rubrica.leer'), ['archivo' => UploadedFile::fake()->create('rubricas.pdf', 10)])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');

        $sinColumnas = UploadedFile::fake()->createWithContent('rubricas.csv', "asignatura,criterio\nGDS,Portada\n");
        $this->actingAs($docente)->postJson(route('evidencias.rubrica.leer'), ['archivo' => $sinColumnas])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');

        $this->assertDatabaseCount('criterios_rubrica', 0);
    }

    public function test_solo_profesor_o_lider_leen_archivos_de_rubricas(): void
    {
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE, 'EST001'))
            ->postJson(route('evidencias.rubrica.leer'), [])->assertForbidden();
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
