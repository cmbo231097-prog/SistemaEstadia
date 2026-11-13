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
use App\Services\RubricaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuloEvaluacionIaTest extends TestCase
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

    /** Evidencia con tres criterios: dos los evalúa la IA (60 y 25) y uno el docente (15). */
    private function escenario(?User $docente = null, bool $sanitizado = true): array
    {
        Storage::fake('local');
        Storage::put('corpus/sanitizado.pdf', '%PDF-1.4 documento anonimizado');
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-test');
        config()->set('services.gemini.base_url', 'https://gemini.test');

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
            'codigo' => 'EDD-EP1',
            'tipo_evidencia' => 'reporte',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        foreach ([[1, 'Contenido', 60, true], [2, 'Redacción', 25, true], [3, 'Desempeño del equipo', 15, false]] as [$id, $nombre, $puntos, $ia]) {
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => $id,
                'nombre_criterio' => $nombre,
                'descripcion_criterio' => $nombre,
                'puntaje_maximo' => $puntos,
                'evaluable_ia' => $ia,
            ]);
        }
        $documento = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => $equipo->id_equipo,
            'nombre_archivo_original' => 'entrega.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'ruta_archivo_sanitizado' => $sanitizado ? 'corpus/sanitizado.pdf' : null,
            'estado_sanitizacion' => $sanitizado ? CorpusDocumento::SANITIZADO : CorpusDocumento::PENDIENTE,
        ]);

        return compact('docente', 'evidencia', 'documento', 'grupo');
    }

    /** @param  array<string, mixed>|null  $generado  Contenido que devuelve Gemini (null = respuesta válida por defecto). */
    private function fingirGemini(?array $generado = null, int $estadoGeneracion = 200): void
    {
        $generado ??= [
            'retroalimentacion_cualitativa' => 'Buen reporte, falta profundizar.',
            'criterios' => [
                ['id_criterio' => 1, 'calificacion' => 50, 'retroalimentacion' => 'Contenido sólido.'],
                ['id_criterio' => 2, 'calificacion' => 20, 'retroalimentacion' => 'Hay errores de redacción.'],
            ],
        ];

        // Un nuevo fake no reemplaza al anterior; se parte de un cliente limpio para poder cambiar la respuesta.
        Http::swap(new HttpFactory);
        Http::fake(function (ClientRequest $request) use ($generado, $estadoGeneracion) {
            if ($request->url() === 'https://gemini.test/upload/v1beta/files') {
                return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/session']);
            }
            if ($request->url() === 'https://upload.test/session') {
                return Http::response(['file' => ['name' => 'files/t', 'uri' => 'https://gemini.test/v1beta/files/t', 'state' => 'ACTIVE']]);
            }
            if (str_contains($request->url(), ':generateContent')) {
                return $estadoGeneracion === 200
                    ? Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($generado)]]]]]])
                    : Http::response(['error' => ['message' => 'fallo']], $estadoGeneracion);
            }

            return Http::response([], 200);
        });
    }

    public function test_el_menu_de_ambos_roles_enlaza_el_modulo_de_evaluacion_con_ia(): void
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR, 'PROF001');
        $lider = $this->usuario(User::ROLE_LIDER, 'LID001');

        foreach ([$profesor, $lider] as $usuario) {
            $this->actingAs($usuario)->get(route('evaluacion-ia.index'))
                ->assertOk()
                ->assertSee('Evaluación con IA')
                ->assertSee(route('evaluacion-ia.index'), false);
        }
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('evaluacion-ia.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE, '2301001'))
            ->get(route('evaluacion-ia.index'))->assertForbidden();
    }

    public function test_la_pantalla_separa_los_criterios_de_la_ia_y_los_del_docente(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['docente'])->get(route('evaluacion-ia.index'))
            ->assertOk()
            ->assertSee('La IA evalúa')
            ->assertSee('Lo califica el docente')
            ->assertSee('Contenido')
            ->assertSee('Desempeño del equipo')
            ->assertSee(route('evaluacion-ia.evaluar', $e['documento']), false);
    }

    public function test_guarda_la_evaluacion_en_la_base_sin_descargar_ningun_archivo(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();

        $respuesta = $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']));

        $respuesta->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'guardada') && str_contains($m, '70'));
        $this->assertNull($respuesta->headers->get('Content-Disposition'));

        $evaluacion = EvaluacionIa::with('criterios')->firstOrFail();
        $this->assertSame($e['documento']->id_corpus, $evaluacion->id_corpus);
        $this->assertSame('70.00', $evaluacion->calificacion_global);
        $this->assertSame('gemini-test', $evaluacion->modelo_llm);
        $this->assertSame('Buen reporte, falta profundizar.', $evaluacion->retroalimentacion_cualitativa);
        $this->assertEqualsCanonicalizing([1, 2], $evaluacion->criterios->pluck('id_criterio')->all());
        $this->assertSame('Contenido sólido.', $evaluacion->criterios->firstWhere('id_criterio', 1)->retroalimentacion);
    }

    public function test_solo_se_envian_y_guardan_los_criterios_evaluables_por_la_ia(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Contenido') && str_contains($prompt, 'Redacción')
                && ! str_contains($prompt, 'Desempeño del equipo');
        });
        $this->assertDatabaseMissing('evaluacion_ia_criterios', ['id_criterio' => 3]);
        $this->assertDatabaseCount('evaluacion_ia_criterios', 2);
    }

    public function test_volver_a_evaluar_reemplaza_la_evaluacion_anterior(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();
        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();

        $this->fingirGemini(['retroalimentacion_cualitativa' => 'Mejoró.', 'criterios' => [
            ['id_criterio' => 1, 'calificacion' => 60, 'retroalimentacion' => 'Excelente.'],
            ['id_criterio' => 2, 'calificacion' => 25, 'retroalimentacion' => 'Sin errores.'],
        ]]);
        $this->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();

        $this->assertDatabaseCount('evaluaciones_ia', 1);
        $this->assertDatabaseCount('evaluacion_ia_criterios', 2);
        $this->assertSame('85.00', EvaluacionIa::firstOrFail()->calificacion_global);
    }

    public function test_la_pantalla_muestra_la_evaluacion_guardada_y_la_rubrica_queda_bloqueada(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();
        $servicio = app(RubricaService::class);

        $this->actingAs($e['docente'])->get(route('evaluacion-ia.index'))->assertOk()->assertSee('data-bloqueada="0"', false);
        $this->assertFalse($servicio->estaBloqueada($e['evidencia']));

        $this->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();

        $this->assertTrue($servicio->estaBloqueada($e['evidencia']));
        $this->get(route('evaluacion-ia.index'))
            ->assertOk()
            ->assertSee('Guardada: 70 / 85 pts')
            ->assertSee('Contenido sólido.')
            ->assertSee('Volver a evaluar')
            ->assertSee('data-bloqueada="1"', false);
    }

    public function test_la_solicitud_normal_redirige_con_aviso_de_guardado(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();

        $this->actingAs($e['docente'])->post(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertDatabaseCount('evaluaciones_ia', 1);
    }

    public function test_rechaza_documentos_sin_sanitizar_y_rubricas_sin_criterios_para_la_ia(): void
    {
        $e = $this->escenario(sanitizado: false);
        Http::fake();

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'sanitizado'));

        $e['documento']->update(['estado_sanitizacion' => CorpusDocumento::SANITIZADO, 'ruta_archivo_sanitizado' => 'corpus/sanitizado.pdf']);
        $e['evidencia']->criteriosRubrica()->update(['evaluable_ia' => false]);
        $this->postJson(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'evaluables desde el producto'));

        Http::assertNothingSent();
    }

    public function test_sin_api_key_avisa_y_no_llama_a_gemini(): void
    {
        $e = $this->escenario();
        config()->set('services.gemini.api_key', '');
        Http::fake();

        $this->actingAs($e['docente'])->get(route('evaluacion-ia.index'))->assertSee('GEMINI_API_KEY');
        $this->postJson(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'GEMINI_API_KEY'));
        Http::assertNothingSent();
    }

    public function test_los_errores_de_gemini_se_traducen_a_un_mensaje_claro_sin_filtrar_la_clave(): void
    {
        $e = $this->escenario();
        $this->fingirGemini(estadoGeneracion: 429);

        $r = $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertStatus(502);

        $this->assertStringContainsString('límite de uso', $r->json('message'));
        $this->assertStringNotContainsString('test-api-key', $r->getContent());
        $this->assertDatabaseCount('evaluaciones_ia', 0);
    }

    public function test_rechaza_una_respuesta_de_gemini_con_puntajes_fuera_de_la_rubrica(): void
    {
        $e = $this->escenario();
        $this->fingirGemini(['retroalimentacion_cualitativa' => 'x', 'criterios' => [['id_criterio' => 1, 'calificacion' => 99, 'retroalimentacion' => 'y']]]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertStatus(502)->assertJsonPath('message', fn ($m) => str_contains($m, 'fuera de la rúbrica'));
        $this->assertDatabaseCount('evaluaciones_ia', 0);
    }

    public function test_un_profesor_no_evalua_documentos_ajenos_pero_el_lider_si(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();

        $this->actingAs($this->usuario(User::ROLE_PROFESOR, 'PROF002'))
            ->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertForbidden();
        Http::assertNothingSent();

        $this->actingAs($this->usuario(User::ROLE_LIDER, 'LID001'))
            ->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();
    }

    public function test_no_permite_evaluar_el_mismo_documento_dos_veces_a_la_vez(): void
    {
        $e = $this->escenario();
        $this->fingirGemini();
        $candado = Cache::lock('evaluacion-ia-'.$e['documento']->id_corpus, 60);
        $this->assertTrue($candado->get());

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documento']))
            ->assertStatus(409);
        Http::assertNothingSent();

        $candado->release();
        $this->postJson(route('evaluacion-ia.evaluar', $e['documento']))->assertOk();
    }
}
