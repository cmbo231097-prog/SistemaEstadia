<?php

namespace Tests\Feature;

use App\Jobs\EvaluarDocumentoConGemini;
use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionIa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use App\Services\GeminiEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvaluacionIaTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(): array
    {
        $docente = User::create([
            'matricula' => 'PROF001',
            'nombre' => 'Ana',
            'apellidos' => 'Docente',
            'correo_institucional' => 'ana@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave(User::ROLE_PROFESOR),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
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
            'descripcion_entregables' => 'Explicar la implementación y analizar su complejidad.',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);

        return compact('docente', 'grupo', 'equipo', 'evidencia');
    }

    private function criterios(Evidencia $evidencia): array
    {
        return [
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => 1,
                'nombre_criterio' => 'Implementación',
                'descripcion_criterio' => 'Estructura y corrección de la implementación.',
                'puntaje_maximo' => 6,
            ]),
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => 2,
                'nombre_criterio' => 'Análisis',
                'descripcion_criterio' => 'Explicación de complejidad y resultados.',
                'puntaje_maximo' => 4,
            ]),
        ];
    }

    private function documento(array $escenario): CorpusDocumento
    {
        Storage::fake('local');
        Storage::put('corpus/sanitizado.pdf', '%PDF-1.4 documento anonimizado');

        return CorpusDocumento::create([
            'id_evidencia' => $escenario['evidencia']->id_evidencia,
            'id_equipo' => $escenario['equipo']->id_equipo,
            'nombre_archivo_original' => 'entrega.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'ruta_archivo_sanitizado' => 'corpus/sanitizado.pdf',
            'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);
    }

    public function test_docente_configura_rubrica_y_el_puntaje_total_debe_ser_cien(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['docente'])
            ->get(route('evidencias.rubrica', $escenario['evidencia']))
            ->assertOk()
            ->assertSee('Rúbrica de evaluación');

        $datos = ['criterios' => [
            ['nombre_criterio' => 'Implementación', 'descripcion_criterio' => 'Código correcto.', 'puntaje_maximo' => 60],
            ['nombre_criterio' => 'Análisis', 'descripcion_criterio' => 'Explica complejidad.', 'puntaje_maximo' => 40],
        ]];

        $this->actingAs($escenario['docente'])
            ->put(route('evidencias.rubrica.update', $escenario['evidencia']), $datos)
            ->assertRedirect(route('evidencias.rubrica', $escenario['evidencia']));

        $this->assertDatabaseCount('criterios_rubrica', 2);

        $datos['criterios'][1]['puntaje_maximo'] = 39.99;
        $this->actingAs($escenario['docente'])
            ->put(route('evidencias.rubrica.update', $escenario['evidencia']), $datos)
            ->assertSessionHasErrors('criterios');
    }

    public function test_solicitud_de_evaluacion_encola_solo_documentos_sanitizados_con_rubrica(): void
    {
        Queue::fake();
        config()->set('services.gemini.api_key', 'test-api-key');
        $escenario = $this->escenario();
        $this->criterios($escenario['evidencia']);
        $documento = $this->documento($escenario);

        $this->actingAs($escenario['docente'])
            ->post(route('corpus.evaluar'), [
                'id_evidencia' => $escenario['evidencia']->id_evidencia,
                'id_grupo' => $escenario['grupo']->id_grupo,
            ])
            ->assertSessionHas('status');

        Queue::assertPushed(EvaluarDocumentoConGemini::class, fn ($job) => $job->idCorpus === $documento->id_corpus);
    }

    public function test_job_persiste_calificacion_global_criterios_y_comentario(): void
    {
        $escenario = $this->escenario();
        $criterios = $this->criterios($escenario['evidencia']);
        $documento = $this->documento($escenario);
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-test');
        config()->set('services.gemini.base_url', 'https://gemini.test');

        $json = json_encode([
            'retroalimentacion_cualitativa' => 'La solución funciona; falta justificar la complejidad temporal.',
            'criterios' => [
                ['id_criterio' => $criterios[0]->id_criterio, 'calificacion' => 5, 'retroalimentacion' => 'Implementación correcta.'],
                ['id_criterio' => $criterios[1]->id_criterio, 'calificacion' => 3.5, 'retroalimentacion' => 'Falta analizar el peor caso.'],
            ],
        ], JSON_THROW_ON_ERROR);
        $respuestaGenerada = ['candidates' => [[
            'content' => ['parts' => [['text' => $json]]],
        ]]];

        Http::fake(function (ClientRequest $request) use ($respuestaGenerada) {
            if ($request->url() === 'https://gemini.test/upload/v1beta/files') {
                return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/session']);
            }

            if ($request->url() === 'https://upload.test/session') {
                return Http::response(['file' => [
                    'name' => 'files/test-pdf',
                    'uri' => 'https://gemini.test/v1beta/files/test-pdf',
                    'state' => 'PROCESSING',
                ]]);
            }

            if ($request->url() === 'https://gemini.test/v1beta/files/test-pdf') {
                return $request->method() === 'GET'
                    ? Http::response([
                        'name' => 'files/test-pdf',
                        'uri' => 'https://gemini.test/v1beta/files/test-pdf',
                        'state' => 'ACTIVE',
                    ])
                    : Http::response([], 200);
            }

            if (str_contains($request->url(), ':generateContent')) {
                return Http::response($respuestaGenerada);
            }

            return Http::response([], 500);
        });

        (new EvaluarDocumentoConGemini($documento->id_corpus))
            ->handle(app(GeminiEvaluationService::class), app(\App\Services\EvaluacionIaService::class));

        $evaluacion = EvaluacionIa::with('criterios')->firstOrFail();
        $this->assertSame('8.50', $evaluacion->calificacion_global);
        $this->assertSame('La solución funciona; falta justificar la complejidad temporal.', $evaluacion->retroalimentacion_cualitativa);
        $this->assertCount(2, $evaluacion->criterios);
        $this->assertSame('gemini-test', $evaluacion->modelo_llm);
        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), ':generateContent')
            && $request->data()['contents'][0]['parts'][1]['file_data']['mime_type'] === 'application/pdf');
    }

    public function test_no_persiste_puntajes_que_exceden_la_rubrica(): void
    {
        $escenario = $this->escenario();
        $criterios = $this->criterios($escenario['evidencia']);
        $documento = $this->documento($escenario);
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-test');
        config()->set('services.gemini.base_url', 'https://gemini.test');

        $respuestaGenerada = ['candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'retroalimentacion_cualitativa' => 'Revisar el análisis.',
                'criterios' => [
                    ['id_criterio' => $criterios[0]->id_criterio, 'calificacion' => 7, 'retroalimentacion' => 'Excede el máximo.'],
                    ['id_criterio' => $criterios[1]->id_criterio, 'calificacion' => 2, 'retroalimentacion' => 'Parcial.'],
                ],
            ], JSON_THROW_ON_ERROR)]]],
        ]]];

        Http::fake([
            'https://gemini.test/upload/v1beta/files' => Http::response([], 200, [
                'X-Goog-Upload-URL' => 'https://upload.test/session',
            ]),
            'https://upload.test/session' => Http::response([
                'file' => ['name' => 'files/test-pdf', 'uri' => 'https://gemini.test/v1beta/files/test-pdf', 'state' => 'ACTIVE'],
            ]),
            'https://gemini.test/v1beta/models/gemini-test:generateContent' => Http::response($respuestaGenerada),
            'https://gemini.test/v1beta/files/test-pdf' => Http::response([], 200),
        ]);

        try {
            (new EvaluarDocumentoConGemini($documento->id_corpus))
                ->handle(app(GeminiEvaluationService::class), app(\App\Services\EvaluacionIaService::class));
            $this->fail('Se esperaba rechazar la calificación fuera de escala.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('fuera de la rúbrica', $exception->getMessage());
        }

        $this->assertDatabaseCount('evaluaciones_ia', 0);
    }
}
