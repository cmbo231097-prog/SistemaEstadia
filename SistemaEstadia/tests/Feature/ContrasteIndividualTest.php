<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionDocente;
use App\Models\EvaluacionIa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use App\Services\ContrasteIndividualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContrasteIndividualTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $clave, string $nombre = 'Usuario'): User
    {
        $matricula = 'USR'.str_pad((string) ++$this->consecutivo, 4, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $matricula, 'nombre' => $nombre, 'apellidos' => $matricula,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx', 'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave), 'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    /**
     * Evidencia de 3 rubros (Contenido 60 y Redacción 25 los evalúa la IA; Desempeño 15 solo el profesor) con:
     * - Equipo 1 (estudiante Ana): profesor [54, 20, 10] = 84; IA [50, 20] = 70
     * - Equipo 2 (estudiante Beto): profesor [40, 15, 12] = 67; IA [45, 22] = 67, con un comentario «SECRETO»
     * - Estudiante Carla: sin equipo
     */
    private function escenario(): array
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR, 'Pedro');
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.++$this->consecutivo, 'modelo_educativo' => Asignatura::MODELO_ACTUAL, 'nombre_asignatura' => 'Estructuras de Datos',
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital', 'cuatrimestre' => 5,
        ]);
        $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'activo' => true,
        ]);
        $grupo = Grupo::create(['clave_grupo' => '9A', 'id_asignatura' => $asignatura->id_asignatura, 'id_docente' => $profesor->id_usuario, 'id_periodo' => $periodo->id_periodo]);
        $evidencia = Evidencia::create(['titulo' => 'Reporte de árboles', 'tipo_evidencia' => 'reporte', 'fecha_limite' => now(), 'id_docente' => $profesor->id_usuario]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        foreach ([[1, 'Contenido', 60, true], [2, 'Redacción', 25, true], [3, 'Desempeño', 15, false]] as [$id, $nombre, $puntos, $ia]) {
            $evidencia->criteriosRubrica()->create(['id_criterio' => $id, 'nombre_criterio' => $nombre, 'descripcion_criterio' => $nombre, 'puntaje_maximo' => $puntos, 'evaluable_ia' => $ia]);
        }

        $ana = $this->usuario(User::ROLE_ESTUDIANTE, 'Ana');
        $beto = $this->usuario(User::ROLE_ESTUDIANTE, 'Beto');
        $carla = $this->usuario(User::ROLE_ESTUDIANTE, 'Carla');
        $equipo1 = Equipo::create(['nombre_equipo' => 'Equipo 1', 'id_grupo' => $grupo->id_grupo]);
        $equipo2 = Equipo::create(['nombre_equipo' => 'Equipo 2', 'id_grupo' => $grupo->id_grupo]);
        $equipo1->integrantes()->attach($ana->id_usuario);
        $equipo2->integrantes()->attach($beto->id_usuario);

        $e1 = $this->documento($evidencia, $equipo1, $profesor, [1 => 54, 2 => 20, 3 => 10], [1 => 50, 2 => 20], 'Buen contenido del equipo uno', 'Contenido sólido en el equipo uno');
        $e2 = $this->documento($evidencia, $equipo2, $profesor, [1 => 40, 2 => 15, 3 => 12], [1 => 45, 2 => 22], 'SECRETO profesor equipo dos', 'SECRETO IA equipo dos');

        return compact('profesor', 'asignatura', 'grupo', 'evidencia', 'ana', 'beto', 'carla', 'equipo1', 'equipo2', 'e1', 'e2');
    }

    private function documento(Evidencia $evidencia, Equipo $equipo, User $profesor, ?array $docente, ?array $ia, string $retroDocente = '', string $retroIa = ''): CorpusDocumento
    {
        $doc = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia, 'id_equipo' => $equipo->id_equipo,
            'nombre_archivo_original' => 'x.pdf', 'ruta_archivo_original' => 'corpus/x.pdf', 'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);
        if ($docente !== null) {
            $eval = EvaluacionDocente::create([
                'id_corpus' => $doc->id_corpus, 'id_docente' => $profesor->id_usuario, 'calificacion_global' => array_sum($docente),
                'comentarios_cualitativos' => $retroDocente.' (general)', 'tiempo_evaluacion_minutos' => 10, 'fecha_evaluacion' => now(),
            ]);
            foreach ($docente as $id => $puntos) {
                $eval->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos, 'retroalimentacion' => $id === 1 ? $retroDocente : null]);
            }
        }
        if ($ia !== null) {
            $evalIa = EvaluacionIa::create([
                'id_corpus' => $doc->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => array_sum($ia),
                'retroalimentacion_cualitativa' => $retroIa.' (general)', 'tiempo_procesamiento_segundos' => 30, 'fecha_procesamiento' => now(),
            ]);
            foreach ($ia as $id => $puntos) {
                $evalIa->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos, 'retroalimentacion' => $id === 1 ? $retroIa : null]);
            }
        }

        return $doc;
    }

    private function borrarEvaluaciones(): void
    {
        EvaluacionDocente::query()->get()->each(function ($ev) {
            $ev->criterios()->delete();
            $ev->delete();
        });
        EvaluacionIa::query()->get()->each(function ($ev) {
            $ev->criterios()->delete();
            $ev->delete();
        });
    }

    public function test_solo_el_estudiante_accede_al_reporte_y_a_su_pdf(): void
    {
        $this->get(route('contraste.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_PROFESOR, User::ROLE_LIDER] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('contraste.index'))->assertForbidden();
            $this->get(route('contraste.pdf'))->assertForbidden();
        }
        $e = $this->escenario();
        $this->actingAs($e['ana'])->get(route('contraste.index'))->assertOk()->assertSee('Mi Reporte de Contraste Evaluativo');
    }

    public function test_un_estudiante_sin_equipo_ve_un_aviso(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['carla'])->get(route('contraste.index'))
            ->assertOk()
            ->assertSee('Aún no perteneces a un equipo')
            ->assertDontSee('Reporte de árboles');
        $this->get(route('contraste.pdf'))->assertRedirect()->assertSessionHas('error');
    }

    public function test_calcula_el_contraste_por_rubro_sobre_los_mismos_rubros_de_la_ia(): void
    {
        $e = $this->escenario();

        $r = app(ContrasteIndividualService::class)->generar($e['equipo1'], $e['evidencia']);

        $this->assertSame(ContrasteIndividualService::COMPLETO, $r['estado']);
        $this->assertSame(84.0, $r['resumen']['docente_total']);
        $this->assertSame(70.0, $r['resumen']['ia_total']);
        $this->assertSame(85.0, $r['resumen']['ia_maximo']);
        $this->assertSame(74.0, $r['resumen']['docente_en_rubros_ia']);   // 54 + 20, sin el rubro que la IA no evalúa
        $this->assertSame(-4.0, $r['resumen']['diferencia']);             // 70 − 74
        $this->assertSame(5.4, $r['resumen']['diferencia_pct']);          // 4 / 74
        $this->assertSame(2, $r['resumen']['rubros_comparados']);

        [$contenido, $redaccion, $desempeno] = $r['rubros']->all();
        $this->assertSame(90.0, $contenido['docente_pct']);               // 54 / 60
        $this->assertSame(83.3, $contenido['ia_pct']);                    // 50 / 60
        $this->assertSame(-4.0, $contenido['diferencia']);
        $this->assertSame('Buen contenido del equipo uno', $contenido['retro_docente']);
        $this->assertSame('Contenido sólido en el equipo uno', $contenido['retro_ia']);
        $this->assertSame(0.0, $redaccion['diferencia']);
        $this->assertFalse($desempeno['ia_evalua']);
        $this->assertNull($desempeno['ia']);
        $this->assertNull($desempeno['diferencia']);
    }

    public function test_la_pantalla_muestra_la_grafica_horizontal_y_la_retroalimentacion_propia(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['ana'])->get(route('contraste.index'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Gráfica de barras horizontales', false)
            ->assertSee('Contenido')
            ->assertSee('Solo lo califica el profesor')
            ->assertSee('Buen contenido del equipo uno')
            ->assertSee('Contenido sólido en el equipo uno')
            ->assertSee('Pedro')                                           // profesor del grupo
            ->assertSee(htmlspecialchars(route('contraste.pdf', ['asignatura' => $e['asignatura']->id_asignatura, 'evidencia' => $e['evidencia']->id_evidencia])), false);
    }

    public function test_un_estudiante_no_ve_nunca_los_datos_de_otro_equipo(): void
    {
        $e = $this->escenario();
        $this->actingAs($e['ana']);

        foreach ([[], ['equipo' => $e['equipo2']->id_equipo], ['evidencia' => $e['evidencia']->id_evidencia, 'asignatura' => $e['asignatura']->id_asignatura, 'estudiante' => $e['beto']->id_usuario]] as $consulta) {
            $this->get(route('contraste.index', $consulta))
                ->assertOk()
                ->assertSee('Equipo 1')
                ->assertDontSee('SECRETO')
                ->assertDontSee('Equipo 2');
        }
        $pdf = $this->get(route('contraste.pdf', ['equipo' => $e['equipo2']->id_equipo]))->assertOk()->getContent();
        $this->assertStringNotContainsString('SECRETO', $pdf);

        // Y el otro estudiante ve únicamente lo suyo.
        $this->actingAs($e['beto'])->get(route('contraste.index'))
            ->assertOk()->assertSee('Equipo 2')->assertSee('SECRETO profesor equipo dos')->assertDontSee('Buen contenido del equipo uno');
    }

    public function test_avisa_cuando_falta_el_documento_o_alguna_evaluacion_y_no_permite_el_pdf(): void
    {
        $e = $this->escenario();
        $consulta = ['asignatura' => $e['asignatura']->id_asignatura, 'evidencia' => $e['evidencia']->id_evidencia];

        // Solo la IA: falta el profesor.
        EvaluacionDocente::where('id_corpus', $e['e1']->id_corpus)->get()->each(function ($ev) {
            $ev->criterios()->delete();
            $ev->delete();
        });
        $this->actingAs($e['ana'])->get(route('contraste.index'))->assertOk()->assertSee('tu profesor aún no registra su calificación')->assertSee('Contenido sólido en el equipo uno');
        $this->get(route('contraste.pdf', $consulta))->assertRedirect()->assertSessionHas('error');

        // Solo el profesor: falta la IA.
        $equipo3 = Equipo::create(['nombre_equipo' => 'Equipo 3', 'id_grupo' => $e['grupo']->id_grupo]);
        $this->documento($e['evidencia'], $equipo3, $e['profesor'], [1 => 50, 2 => 20, 3 => 10], null);
        $tomas = $this->usuario(User::ROLE_ESTUDIANTE, 'Tomás');
        $equipo3->integrantes()->attach($tomas->id_usuario);
        $this->actingAs($tomas)->get(route('contraste.index'))->assertOk()->assertSee('aún no hay evaluación de la IA');
        $this->get(route('contraste.pdf', $consulta))->assertRedirect()->assertSessionHas('error');

        // Sin documento.
        $diana = $this->usuario(User::ROLE_ESTUDIANTE, 'Diana');
        Equipo::create(['nombre_equipo' => 'Equipo 4', 'id_grupo' => $e['grupo']->id_grupo])->integrantes()->attach($diana->id_usuario);
        $this->actingAs($diana)->get(route('contraste.index'))->assertOk()->assertSee('todavía no tiene un documento cargado');
    }

    public function test_descarga_el_pdf_del_contraste(): void
    {
        $e = $this->escenario();

        $respuesta = $this->actingAs($e['ana'])
            ->get(route('contraste.pdf', ['asignatura' => $e['asignatura']->id_asignatura, 'evidencia' => $e['evidencia']->id_evidencia]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="mi-reporte-de-contraste_', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        $this->assertStringContainsString('%%EOF', $respuesta->getContent());
    }

    public function test_el_pdf_soporta_muchos_rubros_con_textos_largos_y_acentos(): void
    {
        $e = $this->escenario();
        $this->borrarEvaluaciones();
        $e['evidencia']->criteriosRubrica()->delete();
        $docente = $ia = [];
        foreach (range(1, 30) as $i) {
            $e['evidencia']->criteriosRubrica()->create(['id_criterio' => $i, 'nombre_criterio' => "Rubro {$i}: análisis de algoritmos y estructuras de datos ñandú", 'descripcion_criterio' => 'x', 'puntaje_maximo' => 3, 'evaluable_ia' => true]);
            $docente[$i] = 2;
            $ia[$i] = 3;
        }
        $largo = str_repeat('Retroalimentación extensa con acentos y ñ. ', 40);
        $eval = EvaluacionDocente::create(['id_corpus' => $e['e1']->id_corpus, 'id_docente' => $e['profesor']->id_usuario, 'calificacion_global' => 60, 'comentarios_cualitativos' => $largo, 'tiempo_evaluacion_minutos' => 5, 'fecha_evaluacion' => now()]);
        $evalIa = EvaluacionIa::create(['id_corpus' => $e['e1']->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => 90, 'retroalimentacion_cualitativa' => $largo, 'tiempo_procesamiento_segundos' => 20, 'fecha_procesamiento' => now()]);
        foreach (range(1, 30) as $i) {
            $eval->criterios()->create(['id_evidencia' => $e['evidencia']->id_evidencia, 'id_criterio' => $i, 'calificacion' => 2, 'retroalimentacion' => $largo]);
            $evalIa->criterios()->create(['id_evidencia' => $e['evidencia']->id_evidencia, 'id_criterio' => $i, 'calificacion' => 3, 'retroalimentacion' => $largo]);
        }

        $respuesta = $this->actingAs($e['ana'])->get(route('contraste.pdf', ['asignatura' => $e['asignatura']->id_asignatura, 'evidencia' => $e['evidencia']->id_evidencia]))->assertOk();

        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        preg_match_all('#/Type /Page\b#', $respuesta->getContent(), $paginas);
        $this->assertGreaterThanOrEqual(4, count($paginas[0]));              // 30 rubros con retroalimentación larga
    }

    public function test_el_menu_y_el_tablero_del_estudiante_enlazan_al_reporte(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['ana'])->get(route('dashboard'))->assertOk()->assertSee(route('contraste.index'), false);
    }
}
