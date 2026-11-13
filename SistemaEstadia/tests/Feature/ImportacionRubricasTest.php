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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportacionRubricasTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = 'asignatura,tipo_evidencia,orden_criterio,id_criterio,criterio,puntaje_max,evaluable_desde_el_producto';

    private function docente(string $matricula = 'PROF001'): User
    {
        return User::create([
            'matricula' => $matricula,
            'nombre' => 'Ana',
            'apellidos' => 'Docente',
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave(User::ROLE_PROFESOR),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function evidencia(User $docente, ?string $codigo = 'GDS-EP1'): Evidencia
    {
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.random_int(1000, 9999),
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => 'Gestión de Datos',
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
            'titulo' => 'Reporte técnico EP1',
            'codigo' => $codigo,
            'tipo_evidencia' => 'reporte',
            'descripcion_entregables' => 'Reporte completo.',
            'fecha_limite' => now()->addWeek(),
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);

        return $evidencia;
    }

    private function csv(): UploadedFile
    {
        $contenido = implode("\n", [
            self::ENCABEZADO,
            'GDS,EP1,1,GDS01,Portada y tabla de contenido,2.5,si',
            'GDS,EP1,2,GDS02,Estructura y extensión,5,si',
            'GDS,EP1,3,GDS03,Diseño de reportes,90,si',
            'GDS,EP1,4,GDS04,Desempeño del equipo,2.5,no',
            'FPTI,EP4,1,FPT01,Portada,100,si',
        ]);

        return UploadedFile::fake()->createWithContent('rubricas.csv', $contenido);
    }

    public function test_pantalla_de_evaluacion_docente_ofrece_el_boton_de_importacion(): void
    {
        $docente = $this->docente();

        $this->actingAs($docente)
            ->get(route('evaluacion-docente.index'))
            ->assertOk()
            ->assertSee('Importar rúbricas')
            ->assertSee(route('evaluacion-docente.rubricas.importar'), false);
    }

    public function test_csv_genera_un_formulario_por_evidencia_con_codigo_y_lista_los_codigos_sin_evidencia(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()])
            ->assertRedirect(route('evaluacion-docente.rubricas.revision'));

        $this->actingAs($docente)
            ->get(route('evaluacion-docente.rubricas.revision'))
            ->assertOk()
            ->assertSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false)
            ->assertSee('Portada y tabla de contenido')
            ->assertSee('Estructura y extensión')
            ->assertSee('FPTI-EP4');

        $this->assertDatabaseCount('criterios_rubrica', 0);
    }

    public function test_guardar_el_formulario_crea_la_rubrica_y_excluye_de_la_ia_los_criterios_no_evaluables(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.guardar', $evidencia), ['criterios' => [
                ['nombre_criterio' => 'Portada', 'descripcion_criterio' => 'Portada completa.', 'puntaje_maximo' => '2.5', 'evaluable_ia' => '1'],
                ['nombre_criterio' => 'Estructura', 'descripcion_criterio' => 'Estructura correcta.', 'puntaje_maximo' => '5', 'evaluable_ia' => '1'],
                ['nombre_criterio' => 'Diseño', 'descripcion_criterio' => 'Diseño adecuado.', 'puntaje_maximo' => '90', 'evaluable_ia' => '1'],
                ['nombre_criterio' => 'Desempeño', 'descripcion_criterio' => 'Trabajo del equipo.', 'puntaje_maximo' => '2.5', 'evaluable_ia' => '0'],
            ]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(4, $evidencia->criteriosRubrica()->count());
        $this->assertSame(3, $evidencia->criteriosEvaluablesIa()->count());
        $this->assertEquals(100.0, (float) $evidencia->criteriosRubrica()->sum('puntaje_maximo'));
    }

    public function test_no_guarda_una_rubrica_que_no_suma_cien_y_conserva_lo_capturado(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.guardar', $evidencia), ['criterios' => [
                ['nombre_criterio' => 'Portada', 'descripcion_criterio' => 'Portada completa.', 'puntaje_maximo' => '10', 'evaluable_ia' => '1'],
            ]])
            ->assertRedirect();

        $this->assertSame(0, $evidencia->criteriosRubrica()->count());

        $this->actingAs($docente)
            ->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('debe ser exactamente 100 puntos')
            ->assertSee('Portada completa.');
    }

    public function test_una_rubrica_donde_ningun_criterio_es_evaluable_por_ia_se_rechaza(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.guardar', $evidencia), ['criterios' => [
                ['nombre_criterio' => 'Desempeño', 'descripcion_criterio' => 'Trabajo.', 'puntaje_maximo' => '100', 'evaluable_ia' => '0'],
            ]]);

        $this->assertSame(0, $evidencia->criteriosRubrica()->count());
    }

    public function test_un_profesor_no_ve_ni_guarda_rubricas_de_evidencias_ajenas(): void
    {
        $dueno = $this->docente('PROF001');
        $evidencia = $this->evidencia($dueno);
        $otro = $this->docente('PROF002');

        $this->actingAs($otro)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);
        $this->actingAs($otro)
            ->get(route('evaluacion-docente.rubricas.revision'))
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false);

        $this->actingAs($otro)
            ->post(route('evaluacion-docente.rubricas.guardar', $evidencia), ['criterios' => []])
            ->assertForbidden();
    }

    public function test_acepta_xlsx_y_csv_con_punto_y_coma_y_texto_mac_roman_mal_decodificado(): void
    {
        $docente = $this->docente();
        $this->evidencia($docente);

        $hoja = new Spreadsheet;
        $hoja->getActiveSheet()->fromArray([
            explode(',', self::ENCABEZADO),
            ['GDS', 'EP1', 1, 'GDS01', 'Diseño de reportes', 100, 'si'],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'rub').'.xlsx';
        (new Xlsx($hoja))->save($ruta);

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => new UploadedFile($ruta, 'rubricas.xlsx', null, null, true)])
            ->assertRedirect(route('evaluacion-docente.rubricas.revision'));
        unlink($ruta);

        $this->actingAs($docente)->get(route('evaluacion-docente.rubricas.revision'))->assertSee('Diseño de reportes');

        $csv = UploadedFile::fake()->createWithContent('rubricas.csv', str_replace(',', ';', self::ENCABEZADO)."\nGDS;EP1;1;GDS01;Ortograf√≠a;2,5;si\n");
        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $csv])
            ->assertRedirect(route('evaluacion-docente.rubricas.revision'));
        $this->actingAs($docente)->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('Ortografía')
            ->assertSee('value="2.5"', false);
    }

    public function test_acepta_puntaje_maximo_como_nombre_de_columna(): void
    {
        $docente = $this->docente();
        $this->evidencia($docente);
        $csv = UploadedFile::fake()->createWithContent(
            'rubricas.csv',
            str_replace('puntaje_max', 'puntaje_maximo', self::ENCABEZADO)."\r\nGDS,EP1,1,GDS01,Portada,100,si\r\n"
        );

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $csv])
            ->assertRedirect(route('evaluacion-docente.rubricas.revision'));
    }

    public function test_rechaza_archivos_con_formato_o_columnas_invalidas(): void
    {
        $docente = $this->docente();

        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => UploadedFile::fake()->create('rubricas.pdf', 10)])
            ->assertSessionHasErrors('archivo');

        $sinColumnas = UploadedFile::fake()->createWithContent('rubricas.csv', "asignatura,criterio\nGDS,Portada\n");
        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $sinColumnas])
            ->assertSessionHasErrors('archivo');

        $puntajeInvalido = UploadedFile::fake()->createWithContent('rubricas.csv', self::ENCABEZADO."\nGDS,EP1,1,GDS01,Portada,abc,si\n");
        $this->actingAs($docente)
            ->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $puntajeInvalido])
            ->assertSessionHasErrors('archivo');
    }

    public function test_no_se_envia_a_la_ia_una_evidencia_sin_rubrica(): void
    {
        Queue::fake();
        config()->set('services.gemini.api_key', 'test-api-key');
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);

        $this->actingAs($docente)
            ->post(route('corpus.evaluar'), [
                'id_evidencia' => $evidencia->id_evidencia,
                'id_grupo' => $evidencia->grupos()->first()->id_grupo,
            ])
            ->assertSessionHas('error');

        Queue::assertNotPushed(EvaluarDocumentoConGemini::class);
    }

    public function test_asigna_por_nombre_de_asignatura_si_hay_una_evidencia_unica_sin_codigo(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente, null);
        $archivo = UploadedFile::fake()->createWithContent('rubricas.csv',
            self::ENCABEZADO."\n gestion de datos ,EP1,1,C1,Portada,100,si\n");

        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $archivo]);
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false)
            ->assertSee('GESTION DE DATOS-EP1');
        $this->assertDatabaseCount('criterios_rubrica', 0);
    }

    public function test_solicita_elegir_evidencia_cuando_hay_varias_para_la_asignatura(): void
    {
        $docente = $this->docente();
        $primera = $this->evidencia($docente, null);
        $segunda = Evidencia::create([
            'titulo' => 'Segunda evidencia',
            'codigo' => null,
            'tipo_evidencia' => 'reporte',
            'fecha_limite' => now(),
            'id_docente' => $docente->id_usuario,
        ]);
        $segunda->grupos()->attach($primera->grupos()->first()->id_grupo);
        $archivo = UploadedFile::fake()->createWithContent('rubricas.csv',
            self::ENCABEZADO."\nGestión de Datos,EP1,1,C1,Portada,100,si\n");

        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $archivo]);
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('No se pudo identificar una evidencia única')
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $primera), false)
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $segunda), false);

        $this->post(route('evaluacion-docente.rubricas.asignar'), [
            'codigo' => 'GESTIÓN DE DATOS-EP1',
            'asignatura' => $primera->grupos()->first()->id_asignatura,
            'evidencia' => $segunda->id_evidencia,
        ])->assertRedirect(route('evaluacion-docente.rubricas.revision'));
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee(route('evaluacion-docente.rubricas.guardar', $segunda), false)
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $primera), false);
    }

    public function test_permite_elegir_asignatura_para_abreviatura_y_rechaza_evidencias_ajenas(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente, null);
        $ajena = $this->evidencia($this->docente('PROF002'), null);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);

        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('Asignatura sin identificar')
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false);
        $this->post(route('evaluacion-docente.rubricas.asignar'), [
            'codigo' => 'GDS-EP1',
            'asignatura' => $ajena->grupos()->first()->id_asignatura,
            'evidencia' => $ajena->id_evidencia,
        ])->assertNotFound();
        $this->post(route('evaluacion-docente.rubricas.asignar'), [
            'codigo' => 'GDS-EP1',
            'asignatura' => $evidencia->grupos()->first()->id_asignatura,
            'evidencia' => $evidencia->id_evidencia,
        ])->assertRedirect();
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false);
    }

    public function test_no_reemplaza_rubrica_existente_sin_confirmacion_o_si_cambio(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);
        $evidencia->criteriosRubrica()->create([
            'id_criterio' => 1,
            'nombre_criterio' => 'Original',
            'descripcion_criterio' => 'Original',
            'puntaje_maximo' => 100,
            'evaluable_ia' => true,
        ]);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('¿Modificar la rúbrica existente?', false);
        $firma = hash('sha256', $evidencia->criteriosRubrica()->orderBy('id_criterio')->get()->toJson());
        $datos = ['criterios' => [
            ['nombre_criterio' => 'Nuevo', 'puntaje_maximo' => 100, 'evaluable_ia' => '1'],
        ], 'firma_rubrica' => $firma];

        $this->post(route('evaluacion-docente.rubricas.guardar', $evidencia), $datos)->assertStatus(409);
        $this->assertSame('Original', $evidencia->criteriosRubrica()->first()->nombre_criterio);
        $this->post(route('evaluacion-docente.rubricas.guardar', $evidencia), array_merge($datos, [
            'confirmar_reemplazo' => '1', 'firma_rubrica' => 'firma-antigua',
        ]))->assertStatus(409);
        $this->assertSame('Original', $evidencia->criteriosRubrica()->first()->nombre_criterio);
        $this->post(route('evaluacion-docente.rubricas.guardar', $evidencia), $datos + [
            'confirmar_reemplazo' => '1',
        ])->assertRedirect();
        $this->assertSame('Nuevo', $evidencia->criteriosRubrica()->first()->nombre_criterio);

        $this->post(route('evaluacion-docente.rubricas.guardar', $evidencia), $datos + [
            'confirmar_reemplazo' => '1',
        ])->assertStatus(409);
        $this->assertSame('Nuevo', $evidencia->criteriosRubrica()->first()->nombre_criterio);
    }

    public function test_un_codigo_distinto_y_varias_rubricas_del_archivo_requieren_seleccion(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente, 'OTRA-EP2');
        $archivo = UploadedFile::fake()->createWithContent('rubricas.csv',
            self::ENCABEZADO."\nGestión de Datos,EP1,1,C1,Portada,100,si\n");
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $archivo]);
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertSee('No se pudo identificar una evidencia única')
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false);

        $evidencia->update(['codigo' => null]);
        $archivo = UploadedFile::fake()->createWithContent('rubricas.csv',
            self::ENCABEZADO."\nGestión de Datos,EP1,1,C1,Portada,100,si\nGestión de Datos,EP2,1,C2,Contenido,100,si\n");
        $this->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $archivo]);
        $this->get(route('evaluacion-docente.rubricas.revision'))
            ->assertDontSee(route('evaluacion-docente.rubricas.guardar', $evidencia), false)
            ->assertSee('GESTIÓN DE DATOS-EP1')
            ->assertSee('GESTIÓN DE DATOS-EP2');
    }

    public function test_importacion_no_reemplaza_rubrica_con_evaluaciones_de_ia(): void
    {
        $docente = $this->docente();
        $evidencia = $this->evidencia($docente);
        $evidencia->criteriosRubrica()->create([
            'id_criterio' => 1, 'nombre_criterio' => 'Original', 'descripcion_criterio' => 'Texto',
            'puntaje_maximo' => 100, 'evaluable_ia' => true,
        ]);
        $equipo = Equipo::create(['nombre_equipo' => 'Equipo 1', 'id_grupo' => $evidencia->grupos()->first()->id_grupo]);
        $documento = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => $equipo->id_equipo,
            'nombre_archivo_original' => 'entrega.pdf',
            'ruta_archivo_original' => 'corpus/original.pdf',
            'estado_sanitizacion' => CorpusDocumento::PENDIENTE,
        ]);
        EvaluacionIa::create([
            'id_corpus' => $documento->id_corpus, 'modelo_llm' => 'gemini', 'calificacion_global' => 90,
            'retroalimentacion_cualitativa' => 'Bien', 'tiempo_procesamiento_segundos' => 3, 'fecha_procesamiento' => now(),
        ]);
        $this->actingAs($docente)->post(route('evaluacion-docente.rubricas.importar'), ['archivo' => $this->csv()]);
        $this->get(route('evaluacion-docente.rubricas.revision'))->assertSee('Esta rúbrica está bloqueada');
        $this->post(route('evaluacion-docente.rubricas.guardar', $evidencia), [
            'confirmar_reemplazo' => '1',
            'criterios' => [['nombre_criterio' => 'Nuevo', 'puntaje_maximo' => 100, 'evaluable_ia' => '1']],
        ])->assertStatus(409);
        $this->assertSame('Original', $evidencia->criteriosRubrica()->first()->nombre_criterio);
    }
}
