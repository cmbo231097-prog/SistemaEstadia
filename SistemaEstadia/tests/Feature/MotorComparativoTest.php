<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionComparativa;
use App\Models\EvaluacionDocente;
use App\Models\EvaluacionIa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use App\Services\ComparativaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MotorComparativoTest extends TestCase
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

    /** Rúbrica: Contenido 60 (IA), Redacción 25 (IA) y Desempeño 15 (solo docente). */
    private function escenario(bool $conCriterioDocente = true, string $matricula = 'PROF001'): array
    {
        $docente = $this->usuario(User::ROLE_PROFESOR, $matricula);
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.$matricula,
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
        $evidencia = Evidencia::create([
            'titulo' => 'Reporte de árboles',
            'tipo_evidencia' => 'reporte',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        $criterios = $conCriterioDocente
            ? [[1, 'Contenido', 60, true], [2, 'Redacción', 25, true], [3, 'Desempeño', 15, false]]
            : [[1, 'Contenido', 60, true], [2, 'Redacción', 40, true]];
        foreach ($criterios as [$id, $nombre, $puntos, $ia]) {
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => $id, 'nombre_criterio' => $nombre, 'descripcion_criterio' => $nombre,
                'puntaje_maximo' => $puntos, 'evaluable_ia' => $ia,
            ]);
        }

        return compact('docente', 'grupo', 'evidencia');
    }

    private function documento(array $e, string $equipo = 'Equipo 1'): CorpusDocumento
    {
        Storage::fake('local');
        Storage::put('corpus/sanitizado.pdf', '%PDF-1.4 anonimizado');

        return CorpusDocumento::create([
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_equipo' => Equipo::create(['nombre_equipo' => $equipo, 'id_grupo' => $e['grupo']->id_grupo])->id_equipo,
            'nombre_archivo_original' => $equipo.'.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'ruta_archivo_sanitizado' => 'corpus/sanitizado.pdf',
            'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);
    }

    /** @param  array<int, float>  $califs  id_criterio => puntos */
    private function evaluacionIa(CorpusDocumento $doc, array $califs, float $segundos = 60): EvaluacionIa
    {
        $ia = EvaluacionIa::create([
            'id_corpus' => $doc->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => array_sum($califs),
            'retroalimentacion_cualitativa' => 'Comentario IA.', 'tiempo_procesamiento_segundos' => $segundos,
            'fecha_procesamiento' => now(),
        ]);
        foreach ($califs as $id => $puntos) {
            $ia->criterios()->create([
                'id_evidencia' => $doc->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos,
                'retroalimentacion' => 'IA criterio '.$id,
            ]);
        }

        return $ia;
    }

    /** @param  array<int, float>|null  $califs  null = evaluación antigua sin rubros */
    private function evaluacionDocente(CorpusDocumento $doc, User $docente, ?array $califs, float $minutos = 12.5, ?float $global = null): EvaluacionDocente
    {
        $eval = EvaluacionDocente::create([
            'id_corpus' => $doc->id_corpus, 'id_docente' => $docente->id_usuario,
            'calificacion_global' => $global ?? array_sum($califs ?? []), 'comentarios_cualitativos' => null,
            'tiempo_evaluacion_minutos' => $minutos, 'fecha_evaluacion' => now(),
        ]);
        foreach ($califs ?? [] as $id => $puntos) {
            $eval->criterios()->create([
                'id_evidencia' => $doc->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos,
                'retroalimentacion' => $id === 1 ? 'Docente criterio 1' : null,
            ]);
        }

        return $eval;
    }

    public function test_calcula_margen_de_error_varianza_y_diferencia_de_tiempos_sobre_los_criterios_de_la_ia(): void
    {
        $e = $this->escenario();
        $doc = $this->documento($e);
        // IA: 50 + 20 = 70 de 85. Docente: 54 + 20 = 74 de 85 (su criterio de desempeño no entra en la comparación).
        $this->evaluacionIa($doc, [1 => 50, 2 => 20], 60);
        $this->evaluacionDocente($doc, $e['docente'], [1 => 54, 2 => 20, 3 => 10], 12.5);

        $comparativa = app(ComparativaService::class)->actualizar($doc);

        $this->assertNotNull($comparativa);
        // IA 82.3529 y docente 87.0588 sobre 100: |diferencia| = 4.7059.
        $this->assertSame(4.71, $comparativa->discrepancia_absoluta);
        $this->assertSame(5.41, $comparativa->discrepancia_porcentual);       // 4.7059 / 87.0588 × 100
        $this->assertSame(0.0554, $comparativa->varianza);                    // (4.7059 / 10 / 2)²
        $this->assertSame(11.5, $comparativa->diferencia_tiempos_minutos);     // 12.5 min − 1 min
        $this->assertFalse($comparativa->alerta_discrepancia);
        $this->assertSame(1, EvaluacionComparativa::count());

        $detalle = $comparativa->detalle;
        $this->assertCount(2, $detalle);
        $this->assertSame([1, 2], $detalle->pluck('id_criterio')->all());
        $this->assertSame(4.0, $detalle[0]->discrepancia_criterio);
        $this->assertSame(54.0, $detalle[0]->calificacion_docente);
        $this->assertSame(50.0, $detalle[0]->calificacion_ia);
        $this->assertStringContainsString('Docente: Docente criterio 1', $detalle[0]->comentarios_criterio);
        $this->assertStringContainsString('IA: IA criterio 1', $detalle[0]->comentarios_criterio);
        $this->assertSame(0.0, $detalle[1]->discrepancia_criterio);
    }

    public function test_marca_alerta_cuando_el_margen_de_error_supera_el_umbral(): void
    {
        $e = $this->escenario();
        $doc = $this->documento($e);
        $this->evaluacionIa($doc, [1 => 30, 2 => 10]);
        $this->evaluacionDocente($doc, $e['docente'], [1 => 54, 2 => 20, 3 => 10]);

        $comparativa = app(ComparativaService::class)->actualizar($doc);

        $this->assertTrue($comparativa->alerta_discrepancia);
        $this->assertGreaterThan(config('services.comparativa.umbral_alerta'), $comparativa->discrepancia_porcentual);

        config()->set('services.comparativa.umbral_alerta', 90);
        $this->assertFalse(app(ComparativaService::class)->actualizar($doc)->alerta_discrepancia);
    }

    public function test_si_el_docente_califico_cero_el_margen_se_expresa_sobre_la_escala_de_100(): void
    {
        $e = $this->escenario();
        $doc = $this->documento($e);
        $this->evaluacionIa($doc, [1 => 30, 2 => 10]);
        $this->evaluacionDocente($doc, $e['docente'], [1 => 0, 2 => 0, 3 => 0]);

        $comparativa = app(ComparativaService::class)->actualizar($doc);

        $this->assertSame(47.06, $comparativa->discrepancia_absoluta);
        $this->assertSame(47.06, $comparativa->discrepancia_porcentual);
        $this->assertTrue($comparativa->alerta_discrepancia);
    }

    public function test_una_evaluacion_docente_antigua_solo_se_compara_si_la_ia_cubrio_toda_la_rubrica(): void
    {
        $servicio = app(ComparativaService::class);

        $e = $this->escenario();
        $doc = $this->documento($e);
        $this->evaluacionIa($doc, [1 => 50, 2 => 20]);
        $this->evaluacionDocente($doc, $e['docente'], null, 10, 80);
        $this->assertNull($servicio->actualizar($doc));
        $this->assertStringContainsString('sin calificar por rubro', $servicio->calcular($doc)['motivo']);

        $e2 = $this->escenario(conCriterioDocente: false, matricula: 'PROF009');
        $doc2 = $this->documento($e2, 'Equipo 2');
        $this->evaluacionIa($doc2, [1 => 50, 2 => 30]);
        $this->evaluacionDocente($doc2, $e2['docente'], null, 10, 90);
        $comparativa = $servicio->actualizar($doc2);
        $this->assertNotNull($comparativa);
        $this->assertSame(11.11, $comparativa->discrepancia_porcentual);      // |80 − 90| / 90
    }

    public function test_se_calcula_sola_al_guardar_la_evaluacion_del_docente_y_se_actualiza_al_corregirla(): void
    {
        $e = $this->escenario();
        $doc = $this->documento($e);
        $this->evaluacionIa($doc, [1 => 50, 2 => 20]);
        $enviar = fn (array $califs) => $this->actingAs($e['docente'])->post(route('evaluacion-docente.store', $doc), [
            'criterios' => array_map(fn ($p) => ['calificacion' => $p], $califs),
            'tiempo_evaluacion' => '08:00',
        ])->assertSessionHasNoErrors();

        $enviar([1 => 54, 2 => 20, 3 => 10]);
        $primera = EvaluacionComparativa::firstOrFail();
        $this->assertSame(4.71, $primera->discrepancia_absoluta);

        $enviar([1 => 50, 2 => 20, 3 => 15]);
        $this->assertSame(1, EvaluacionComparativa::count());
        $this->assertSame(0.0, EvaluacionComparativa::firstOrFail()->discrepancia_absoluta);
        $this->assertSame(2, EvaluacionComparativa::firstOrFail()->detalle()->count());
    }

    public function test_se_calcula_sola_al_evaluar_con_ia_si_el_docente_ya_evaluo(): void
    {
        $e = $this->escenario();
        $doc = $this->documento($e);
        $this->evaluacionDocente($doc, $e['docente'], [1 => 54, 2 => 20, 3 => 10], 10);
        config()->set('services.gemini', ['api_key' => 'k', 'model' => 'gemini-test', 'base_url' => 'https://gemini.test']);
        Http::swap(new HttpFactory);
        Http::fake(function (ClientRequest $request) {
            return match (true) {
                $request->url() === 'https://gemini.test/upload/v1beta/files' => Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/s']),
                $request->url() === 'https://upload.test/s' => Http::response(['file' => ['name' => 'files/t', 'uri' => 'https://gemini.test/v1beta/files/t', 'state' => 'ACTIVE']]),
                str_contains($request->url(), ':generateContent') => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'retroalimentacion_cualitativa' => 'Bien.',
                    'criterios' => [
                        ['id_criterio' => 1, 'calificacion' => 50, 'retroalimentacion' => 'a'],
                        ['id_criterio' => 2, 'calificacion' => 20, 'retroalimentacion' => 'b'],
                    ],
                ])]]]]]]),
                default => Http::response([], 200),
            };
        });

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $doc))->assertOk();

        $this->assertSame(4.71, EvaluacionComparativa::firstOrFail()->discrepancia_absoluta);
    }

    public function test_procesar_lote_compara_los_documentos_completos_y_reporta_los_pendientes(): void
    {
        $e = $this->escenario();
        $completo = $this->documento($e, 'Equipo 1');
        $this->evaluacionIa($completo, [1 => 50, 2 => 20]);
        $this->evaluacionDocente($completo, $e['docente'], [1 => 54, 2 => 20, 3 => 10]);
        $sinDocente = $this->documento($e, 'Equipo 2');
        $this->evaluacionIa($sinDocente, [1 => 40, 2 => 20]);
        $this->documento($e, 'Equipo 3');

        $this->actingAs($e['docente'])
            ->post(route('motor-comparativo.procesar'), ['id_evidencia' => $e['evidencia']->id_evidencia, 'id_grupo' => $e['grupo']->id_grupo])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, '1 de 3') && str_contains($m, '2 quedaron pendientes'));

        $this->assertSame(1, EvaluacionComparativa::count());
        $this->assertSame($completo->id_corpus, EvaluacionComparativa::firstOrFail()->id_corpus);

        $this->get(route('motor-comparativo.index'))
            ->assertOk()
            ->assertSee('Falta la evaluación del docente.')
            ->assertSee('Falta la evaluación con IA.')
            ->assertSee('Coherente')
            ->assertSee('Margen de error promedio');
    }

    public function test_procesar_sin_ningun_par_de_evaluaciones_avisa_y_no_guarda(): void
    {
        $e = $this->escenario();
        $this->documento($e);

        $this->actingAs($e['docente'])
            ->post(route('motor-comparativo.procesar'), ['id_evidencia' => $e['evidencia']->id_evidencia, 'id_grupo' => $e['grupo']->id_grupo])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('evaluaciones_comparativas', 0);
    }

    public function test_acceso_por_rol_y_aislamiento_entre_profesores(): void
    {
        $e = $this->escenario();
        $this->get(route('motor-comparativo.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE, '2301001'))->get(route('motor-comparativo.index'))->assertForbidden();

        $otro = $this->usuario(User::ROLE_PROFESOR, 'PROF002');
        $this->actingAs($otro)->get(route('motor-comparativo.index'))->assertOk()->assertDontSee('Reporte de árboles');
        $this->post(route('motor-comparativo.procesar'), ['id_evidencia' => $e['evidencia']->id_evidencia, 'id_grupo' => $e['grupo']->id_grupo])
            ->assertForbidden();

        $lider = $this->usuario(User::ROLE_LIDER, 'LID001');
        $this->actingAs($lider)->get(route('motor-comparativo.index'))->assertOk()->assertSee('Reporte de árboles')
            ->assertSee(route('motor-comparativo.index'), false);
    }

    public function test_el_resumen_del_lote_promedia_las_metricas_guardadas(): void
    {
        $e = $this->escenario();
        foreach ([[54, 'Equipo 1'], [50, 'Equipo 2']] as [$contenido, $nombre]) {
            $doc = $this->documento($e, $nombre);
            $this->evaluacionIa($doc, [1 => 50, 2 => 20], 120);
            $this->evaluacionDocente($doc, $e['docente'], [1 => $contenido, 2 => 20, 3 => 10], 10);
            app(ComparativaService::class)->actualizar($doc);
        }

        $resumen = app(ComparativaService::class)->resumen(EvaluacionComparativa::all());

        $this->assertSame(2, $resumen['documentos']);
        $this->assertEqualsWithDelta(2.35, $resumen['discrepancia_promedio'], 0.01);   // (4.71 + 0) / 2
        $this->assertEqualsWithDelta(2.7, $resumen['margen_error_promedio'], 0.01);    // (5.41 + 0) / 2
        $this->assertSame(8.0, $resumen['diferencia_tiempos_promedio']);       // 10 − 2
        $this->assertSame(0, $resumen['alertas']);
        $this->assertNull(app(ComparativaService::class)->resumen(collect()));
    }
}
