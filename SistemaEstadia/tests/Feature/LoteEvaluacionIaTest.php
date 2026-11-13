<?php

namespace Tests\Feature;

use App\Jobs\ProcesarLoteEvaluacionIa;
use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\EvaluacionIa;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\LoteEvaluacionIa;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class LoteEvaluacionIaTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $clave): User
    {
        $m = 'LOT'.str_pad((string) ++$this->consecutivo, 3, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $m,
            'nombre' => 'Usuario',
            'apellidos' => $m,
            'correo_institucional' => strtolower($m).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    /** Evidencia con dos criterios evaluables por la IA (60 y 25) y uno del docente (15), con $equipos PDFs sanitizados. */
    private function escenario(int $equipos = 3, ?User $docente = null): array
    {
        Storage::fake('local');
        Storage::put('corpus/sanitizado.pdf', '%PDF-1.4 documento anonimizado');
        config()->set('services.gemini.api_key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-test');
        config()->set('services.gemini.base_url', 'https://gemini.test');

        $docente ??= $this->usuario(User::ROLE_PROFESOR);
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.random_int(1000, 9999),
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
            'codigo' => 'EDD-EP'.random_int(1, 999),
            'tipo_evidencia' => 'reporte',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        foreach ([[1, 'Contenido', 60, true], [2, 'Redacción', 25, true], [3, 'Desempeño del equipo', 15, false]] as [$id, $nombre, $puntos, $ia]) {
            $evidencia->criteriosRubrica()->create([
                'id_criterio' => $id, 'nombre_criterio' => $nombre, 'descripcion_criterio' => $nombre,
                'puntaje_maximo' => $puntos, 'evaluable_ia' => $ia,
            ]);
        }

        $documentos = [];
        for ($i = 1; $i <= $equipos; $i++) {
            $equipo = Equipo::create(['nombre_equipo' => "Equipo {$i}", 'id_grupo' => $grupo->id_grupo]);
            $documentos[] = CorpusDocumento::create([
                'id_evidencia' => $evidencia->id_evidencia,
                'id_equipo' => $equipo->id_equipo,
                'nombre_archivo_original' => "entrega{$i}.pdf",
                'ruta_archivo_original' => 'corpus/original.pdf',
                'ruta_archivo_sanitizado' => 'corpus/sanitizado.pdf',
                'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
            ]);
        }

        return compact('docente', 'evidencia', 'documentos', 'grupo');
    }

    /**
     * Simula a Gemini. $respuestasGeneracion lista, en orden, el estado HTTP de cada llamada a generateContent
     * (la última se repite); 200 devuelve una evaluación válida.
     *
     * @param  array<int, int>  $respuestasGeneracion
     */
    private function fingirGemini(array $respuestasGeneracion = [200]): void
    {
        $generado = [
            'retroalimentacion_cualitativa' => 'Buen reporte.',
            'criterios' => [
                ['id_criterio' => 1, 'calificacion' => 50, 'retroalimentacion' => 'Sólido.'],
                ['id_criterio' => 2, 'calificacion' => 20, 'retroalimentacion' => 'Mejorar redacción.'],
            ],
        ];
        $llamadas = 0;

        Http::swap(new HttpFactory);
        Http::fake(function (ClientRequest $request) use ($generado, $respuestasGeneracion, &$llamadas) {
            if ($request->url() === 'https://gemini.test/upload/v1beta/files') {
                return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/session']);
            }
            if ($request->url() === 'https://upload.test/session') {
                return Http::response(['file' => ['name' => 'files/t', 'uri' => 'https://gemini.test/v1beta/files/t', 'state' => 'ACTIVE']]);
            }
            if (str_contains($request->url(), ':generateContent')) {
                $estado = $respuestasGeneracion[min($llamadas++, count($respuestasGeneracion) - 1)];

                return $estado === 200
                    ? Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($generado)]]]]]])
                    : Http::response(['error' => ['message' => 'fallo']], $estado);
            }

            return Http::response([], 200);
        });
    }

    private function enviarLote(array $e, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $e['docente'])->postJson(route('evaluacion-ia.lote'), [
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_grupo' => $e['grupo']->id_grupo,
        ]);
    }

    private function generaciones(): int
    {
        return Http::recorded(fn (ClientRequest $r) => str_contains($r->url(), ':generateContent'))->count();
    }

    public function test_un_503_se_reintenta_tras_1_y_2_segundos_y_luego_se_evalua(): void
    {
        Sleep::fake();
        $e = $this->escenario(1);
        $this->fingirGemini([503, 503, 200]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documentos'][0]))->assertOk();

        Sleep::assertSequence([Sleep::usleep(1_000_000), Sleep::usleep(2_000_000)]);
        $this->assertSame(3, $this->generaciones());
        $this->assertSame(1, EvaluacionIa::count());
    }

    public function test_si_el_503_persiste_espera_1_2_4_8_y_16_segundos_y_falla(): void
    {
        Sleep::fake();
        $e = $this->escenario(1);
        $this->fingirGemini([503]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documentos'][0]))
            ->assertStatus(502)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'HTTP 503') && str_contains($m, '5 reintentos'));

        Sleep::assertSequence([
            Sleep::usleep(1_000_000), Sleep::usleep(2_000_000), Sleep::usleep(4_000_000), Sleep::usleep(8_000_000), Sleep::usleep(16_000_000),
        ]);
        $this->assertSame(6, $this->generaciones());
        $this->assertSame(0, EvaluacionIa::count());
    }

    public function test_otros_errores_http_no_se_reintentan(): void
    {
        Sleep::fake();
        $e = $this->escenario(1);
        $this->fingirGemini([500]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documentos'][0]))->assertStatus(502);

        Sleep::assertNeverSlept();
        $this->assertSame(1, $this->generaciones());
    }

    public function test_el_lote_evalua_todos_los_pdfs_pendientes_y_avisa_al_terminar(): void
    {
        $e = $this->escenario(3);
        $this->fingirGemini([200]);

        $this->enviarLote($e)->assertStatus(202)->assertJsonStructure(['message', 'lote']);

        $lote = LoteEvaluacionIa::firstOrFail();
        $this->assertSame(LoteEvaluacionIa::TERMINADO, $lote->estado);
        $this->assertSame(3, $lote->total);
        $this->assertSame(3, $lote->exitosos);
        $this->assertSame(0, $lote->fallidos);
        $this->assertSame(3, EvaluacionIa::count());
        $this->assertSame(['ok', 'ok', 'ok'], array_column($lote->documentos, 'estado'));

        $aviso = Notificacion::where('id_usuario', $e['docente']->id_usuario)->sole();
        $this->assertSame('Lote evaluado por la IA', $aviso->titulo);
        $this->assertSame('sistema', $aviso->canal);
        $this->assertFalse($aviso->leido);
        $this->assertStringContainsString('3 PDFs', $aviso->mensaje);
    }

    public function test_un_pdf_con_error_no_detiene_el_lote_y_el_aviso_lo_informa(): void
    {
        Sleep::fake();
        $e = $this->escenario(3);
        $this->fingirGemini([200, 400, 200]);

        $this->enviarLote($e)->assertStatus(202);

        $lote = LoteEvaluacionIa::firstOrFail();
        $this->assertSame(2, $lote->exitosos);
        $this->assertSame(1, $lote->fallidos);
        $this->assertSame(['ok', 'error', 'ok'], array_column($lote->documentos, 'estado'));
        $this->assertStringContainsString('HTTP 400', $lote->documentos[1]['mensaje']);
        $this->assertSame(2, EvaluacionIa::count());

        $aviso = Notificacion::sole();
        $this->assertSame('Lote evaluado con errores', $aviso->titulo);
        $this->assertStringContainsString('2 de 3', $aviso->mensaje);
    }

    public function test_el_lote_encola_un_solo_documento_a_la_vez(): void
    {
        Queue::fake();
        $e = $this->escenario(3);
        $this->fingirGemini();

        $this->enviarLote($e)->assertStatus(202);

        Queue::assertPushed(ProcesarLoteEvaluacionIa::class, 1);
        $this->assertSame(0, EvaluacionIa::count());
        $this->assertSame(LoteEvaluacionIa::EN_COLA, LoteEvaluacionIa::firstOrFail()->estado);
    }

    public function test_solo_se_envian_los_pendientes_y_sin_pendientes_se_rechaza(): void
    {
        $e = $this->escenario(3);
        $this->fingirGemini();
        EvaluacionIa::create([
            'id_corpus' => $e['documentos'][0]->id_corpus, 'modelo_llm' => 'x', 'calificacion_global' => 70,
            'retroalimentacion_cualitativa' => 'previa', 'tiempo_procesamiento_segundos' => 5, 'fecha_procesamiento' => now(),
        ]);
        $e['documentos'][2]->update(['estado_sanitizacion' => CorpusDocumento::PENDIENTE]);

        $this->enviarLote($e)->assertStatus(202);
        $this->assertSame(1, LoteEvaluacionIa::firstOrFail()->total);
        $this->assertSame(2, EvaluacionIa::count());

        $this->enviarLote($e)->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_contains($m, 'No hay PDFs sanitizados pendientes'));
    }

    public function test_no_permite_dos_lotes_simultaneos_de_la_misma_evidencia(): void
    {
        Queue::fake();
        $e = $this->escenario(2);

        $this->enviarLote($e)->assertStatus(202);
        $this->enviarLote($e)->assertStatus(409);
        $this->assertSame(1, LoteEvaluacionIa::count());
    }

    public function test_rechaza_sin_api_key_o_sin_rubrica_para_la_ia(): void
    {
        $e = $this->escenario(1);
        config()->set('services.gemini.api_key', '');
        $this->enviarLote($e)->assertStatus(503);

        config()->set('services.gemini.api_key', 'x');
        $e['evidencia']->criteriosRubrica()->update(['evaluable_ia' => false]);
        $this->enviarLote($e)->assertStatus(422);
        $this->assertSame(0, LoteEvaluacionIa::count());
    }

    public function test_control_de_acceso_del_lote(): void
    {
        Queue::fake();
        $e = $this->escenario(1);

        $this->postJson(route('evaluacion-ia.lote'))->assertUnauthorized();
        $this->enviarLote($e, $this->usuario(User::ROLE_ESTUDIANTE))->assertForbidden();
        $this->enviarLote($e, $this->usuario(User::ROLE_PROFESOR))->assertForbidden();

        $this->enviarLote($e)->assertStatus(202);
        $lote = LoteEvaluacionIa::firstOrFail();

        $this->actingAs($this->usuario(User::ROLE_PROFESOR))->getJson(route('evaluacion-ia.lote.estado', $lote))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_LIDER))->getJson(route('evaluacion-ia.lote.estado', $lote))->assertOk();
        $this->actingAs($e['docente'])->getJson(route('evaluacion-ia.lote.estado', $lote))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('estado', 'en_cola');
    }

    public function test_la_pantalla_muestra_el_boton_los_pendientes_y_el_progreso(): void
    {
        Queue::fake();
        $e = $this->escenario(2);
        $ruta = route('evaluacion-ia.index', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]);

        $this->actingAs($e['docente'])->get($ruta)->assertOk()
            ->assertSee('Enviar lote a la IA')
            ->assertSee('2 pendientes')
            ->assertSee('1, 2, 4, 8 y 16 segundos');

        $this->enviarLote($e)->assertStatus(202);
        $this->get($ruta)->assertOk()->assertSee('id="loteProgreso"', false)->assertSee('Equipo 2');
    }

    public function test_evaluar_un_documento_genera_una_notificacion_del_sistema(): void
    {
        $e = $this->escenario(1);
        $this->fingirGemini([200]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documentos'][0]))->assertOk();

        $aviso = Notificacion::sole();
        $this->assertSame('Evaluación con IA terminada', $aviso->titulo);
        $this->assertStringContainsString('Equipo 1', $aviso->mensaje);
        $this->assertSame($e['docente']->id_usuario, $aviso->id_usuario);
    }

    public function test_la_evaluacion_individual_fallida_tambien_avisa(): void
    {
        $e = $this->escenario(1);
        $this->fingirGemini([400]);

        $this->actingAs($e['docente'])->postJson(route('evaluacion-ia.evaluar', $e['documentos'][0]))->assertStatus(502);

        $this->assertSame('Evaluación con IA fallida', Notificacion::sole()->titulo);
    }

    public function test_las_notificaciones_son_privadas_y_se_marcan_como_leidas(): void
    {
        $uno = $this->usuario(User::ROLE_PROFESOR);
        $otro = $this->usuario(User::ROLE_PROFESOR);
        $mia = Notificacion::avisar($uno->id_usuario, 'Mía', 'Mensaje mío', 'evaluacion_ia_lote');
        Notificacion::avisar($uno->id_usuario, 'Otra mía', 'Mensaje', 'evaluacion_ia');
        $ajena = Notificacion::avisar($otro->id_usuario, 'Ajena', 'No debe verse', 'evaluacion_ia');

        $this->getJson(route('notificaciones.index'))->assertUnauthorized();

        $this->actingAs($uno)->getJson(route('notificaciones.index'))
            ->assertOk()
            ->assertJsonPath('no_leidas', 2)
            ->assertJsonCount(2, 'notificaciones')
            ->assertJsonMissing(['titulo' => 'Ajena']);

        $this->postJson(route('notificaciones.leer'), ['id' => $mia->id_notificacion])->assertOk();
        $this->getJson(route('notificaciones.index'))->assertJsonPath('no_leidas', 1);

        // Intentar marcar una ajena no tiene efecto.
        $this->postJson(route('notificaciones.leer'), ['id' => $ajena->id_notificacion])->assertOk();
        $this->assertFalse($ajena->fresh()->leido);

        $this->postJson(route('notificaciones.leer'))->assertOk();
        $this->getJson(route('notificaciones.index'))->assertJsonPath('no_leidas', 0);
    }

    public function test_la_campana_aparece_en_las_pantallas_del_panel(): void
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR);

        $this->actingAs($profesor)->get(route('dashboard'))->assertOk()
            ->assertSee('id="notifBtn"', false)
            ->assertSee(addcslashes(route('notificaciones.index'), '/'), false);
    }
}
