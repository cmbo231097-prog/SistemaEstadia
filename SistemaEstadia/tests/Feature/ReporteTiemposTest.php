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
use App\Services\ReporteTiemposService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ReporteTiemposTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $clave): User
    {
        $matricula = 'USR'.str_pad((string) ++$this->consecutivo, 4, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $matricula, 'nombre' => 'Usuario', 'apellidos' => $matricula,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx', 'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave), 'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    private function asignatura(string $nombre): Asignatura
    {
        return Asignatura::create([
            'clave_asignatura' => 'IID-'.++$this->consecutivo, 'modelo_educativo' => Asignatura::MODELO_ACTUAL, 'nombre_asignatura' => $nombre,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital', 'cuatrimestre' => 5,
        ]);
    }

    /** Documento de la asignatura con evaluación docente (minutos) y de la IA (segundos). */
    private function documento(Asignatura $asignatura, string $titulo, float $minutosDocente, float $segundosIa, string $fecha, string $equipo = 'Equipo 1', bool $conIa = true, ?CorpusDocumento $mismaEvidenciaQue = null): CorpusDocumento
    {
        if ($mismaEvidenciaQue) {
            $evidencia = $mismaEvidenciaQue->evidencia;
            $grupo = $mismaEvidenciaQue->equipo->grupo;
            $profesor = $evidencia->docente;
        } else {
            $profesor = $this->usuario(User::ROLE_PROFESOR);
            $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
                'nombre_periodo' => 'Septiembre - Diciembre 2026', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'activo' => true,
            ]);
            $grupo = Grupo::create([
                'clave_grupo' => 'G'.++$this->consecutivo, 'id_asignatura' => $asignatura->id_asignatura,
                'id_docente' => $profesor->id_usuario, 'id_periodo' => $periodo->id_periodo,
            ]);
            $evidencia = Evidencia::create(['titulo' => $titulo, 'tipo_evidencia' => 'reporte', 'fecha_limite' => now(), 'id_docente' => $profesor->id_usuario]);
            $evidencia->grupos()->attach($grupo->id_grupo);
        }
        $doc = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => Equipo::create(['nombre_equipo' => $equipo, 'id_grupo' => $grupo->id_grupo])->id_equipo,
            'nombre_archivo_original' => 'x.pdf', 'ruta_archivo_original' => 'corpus/x.pdf', 'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);
        if ($conIa) {
            EvaluacionIa::create([
                'id_corpus' => $doc->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => 80,
                'retroalimentacion_cualitativa' => 'IA', 'tiempo_procesamiento_segundos' => $segundosIa, 'fecha_procesamiento' => now(),
            ]);
        }
        EvaluacionDocente::create([
            'id_corpus' => $doc->id_corpus, 'id_docente' => $profesor->id_usuario, 'calificacion_global' => 80,
            'tiempo_evaluacion_minutos' => $minutosDocente, 'fecha_evaluacion' => $fecha,
        ]);

        return $doc;
    }

    public function test_solo_el_lider_accede_al_reporte_y_a_su_excel(): void
    {
        $this->get(route('reportes.tiempos.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_PROFESOR, User::ROLE_ESTUDIANTE] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('reportes.tiempos.index'))->assertForbidden();
            $this->get(route('reportes.tiempos.excel'))->assertForbidden();
        }
        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('reportes.tiempos.index'))->assertOk()
            ->assertSee('Reporte de Eficiencia de Tiempos');
    }

    public function test_calcula_tiempos_ahorro_y_totales(): void
    {
        $edd = $this->asignatura('Estructuras de Datos');
        $primero = $this->documento($edd, 'Reporte de árboles', 20, 30, '2026-10-10 12:00:00', 'Equipo 1');   // IA 0.5 min
        $this->documento($edd, 'Reporte de árboles', 10, 90, '2026-10-11 12:00:00', 'Equipo 2', mismaEvidenciaQue: $primero);   // IA 1.5 min
        $this->documento($edd, 'Reporte de árboles', 15, 0, '2026-10-12 12:00:00', 'Equipo 3', conIa: false, mismaEvidenciaQue: $primero);

        $datos = app(ReporteTiemposService::class)->generar(null, null, null);

        $this->assertCount(2, $datos['filas']);                        // el documento sin IA no cuenta
        $this->assertSame(1, $datos['grupos']->count());
        $grupo = $datos['grupos'][0];
        $this->assertSame(2, $grupo['documentos']);
        $this->assertSame(15.0, $grupo['docente_min']);                // (20 + 10) / 2
        $this->assertSame(1.0, $grupo['ia_min']);                      // (0.5 + 1.5) / 2
        $this->assertSame(14.0, $grupo['ahorro_min']);
        $this->assertSame(93.3, $grupo['ahorro_pct']);                 // 14 / 15
        $r = $datos['resumen'];
        $this->assertSame(2, $r['documentos']);
        $this->assertSame(60.0, $r['ia_prom_seg']);
        $this->assertSame(0.5, $r['docente_total_horas']);             // 30 min
        $this->assertSame(0.03, $r['ia_total_horas']);                 // 2 min
    }

    public function test_filtra_por_asignatura_y_por_rango_de_fechas(): void
    {
        $edd = $this->asignatura('Estructuras de Datos');
        $bd = $this->asignatura('Bases de Datos');
        $this->documento($edd, 'Evidencia de EDD', 20, 30, '2026-10-10 12:00:00');
        $this->documento($bd, 'Evidencia de BD', 10, 30, '2026-11-20 09:00:00');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('reportes.tiempos.index'))->assertSee('Evidencia de EDD')->assertSee('Evidencia de BD');
        $this->get(route('reportes.tiempos.index', ['asignatura' => $edd->id_asignatura]))
            ->assertSee('Evidencia de EDD')->assertDontSee('Evidencia de BD');
        $this->get(route('reportes.tiempos.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Evidencia de BD')->assertDontSee('Evidencia de EDD');
        $this->get(route('reportes.tiempos.index', ['desde' => '2026-10-10', 'hasta' => '2026-10-10']))
            ->assertSee('Evidencia de EDD')->assertDontSee('Evidencia de BD');
        $this->get(route('reportes.tiempos.index', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertSee('No hay documentos')->assertDontSee('<svg', false);
    }

    public function test_muestra_la_grafica_de_columnas_y_los_indicadores(): void
    {
        $this->documento($this->asignatura('Estructuras de Datos'), 'Reporte de árboles', 20, 30, '2026-10-10 12:00:00');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('reportes.tiempos.index'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Gráfica de columnas comparativas', false)
            ->assertSee('Ahorro 97.5 %')                                   // (20 − 0.5) / 20
            ->assertSee('Revisión docente promedio')
            ->assertSee('Estructuras de Datos')
            ->assertSee(route('reportes.tiempos.excel'), false);
    }

    public function test_valida_fechas_y_asignatura(): void
    {
        $this->documento($this->asignatura('Estructuras de Datos'), 'Evidencia', 20, 30, '2026-10-10 12:00:00');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('reportes.tiempos.index', ['desde' => '2026-11-10', 'hasta' => '2026-11-01']))
            ->assertOk()->assertSee('no puede ser anterior')->assertDontSee('<svg', false);
        $this->get(route('reportes.tiempos.index', ['desde' => 'ayer']))->assertOk()->assertSee('fecha inicial no es válida');
        $this->get(route('reportes.tiempos.index', ['asignatura' => 99999]))->assertOk()->assertSee('Selecciona una asignatura');
        $this->get(route('reportes.tiempos.excel', ['hasta' => '2026-01-01', 'desde' => '2026-02-01']))
            ->assertRedirect()->assertSessionHasErrors('hasta');
    }

    public function test_el_excel_trae_resumen_detalle_y_una_grafica_de_columnas_nativa(): void
    {
        $edd = $this->asignatura('Estructuras de Datos');
        $primero = $this->documento($edd, 'Reporte de árboles', 20, 30, '2026-10-10 12:00:00', 'Equipo 1');
        $this->documento($edd, 'Reporte de árboles', 10, 90, '2026-10-11 12:00:00', 'Equipo 2', mismaEvidenciaQue: $primero);
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $respuesta = $this->get(route('reportes.tiempos.excel', ['asignatura' => $edd->id_asignatura, 'desde' => '2026-10-01']))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="reporte-eficiencia-tiempos_', $respuesta->headers->get('Content-Disposition'));

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($ruta, $respuesta->getContent());

        $libro = IOFactory::load($ruta);
        $this->assertSame(['Resumen', 'Detalle'], $libro->getSheetNames());
        $resumen = $libro->getSheetByName('Resumen');
        $this->assertSame('Reporte de Eficiencia de Tiempos', $resumen->getCell('A1')->getValue());
        $this->assertSame('Estructuras de Datos', $resumen->getCell('B3')->getValue());
        $this->assertSame(2, (int) $resumen->getCell('B6')->getValue());              // documentos comparados
        // Fila 18 = encabezado de la tabla por evidencia (6 + 10 indicadores + 2 de separación).
        $this->assertSame('Evidencia', $resumen->getCell('A18')->getValue());
        $this->assertSame('Reporte de árboles', $resumen->getCell('A19')->getValue());
        $this->assertEquals(15, $resumen->getCell('D19')->getValue());
        $this->assertEquals(1, $resumen->getCell('E19')->getValue());

        $detalle = $libro->getSheetByName('Detalle');
        $this->assertSame('Equipo 1', $detalle->getCell('A2')->getValue());
        $this->assertEquals(20, $detalle->getCell('E2')->getValue());
        $this->assertEquals(30, $detalle->getCell('F2')->getValue());
        $this->assertSame('Equipo 2', $detalle->getCell('A3')->getValue());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));
        $grafica = (string) $zip->getFromName('xl/charts/chart1.xml');
        $zip->close();
        unlink($ruta);
        $this->assertStringContainsString('<c:barChart>', $grafica);
        $this->assertStringContainsString('<c:barDir val="col"/>', $grafica);        // columnas verticales
        $this->assertStringContainsString("'Resumen'!\$D\$19:\$D\$19", $grafica);
        $this->assertStringContainsString("'Resumen'!\$E\$19:\$E\$19", $grafica);
    }

    public function test_el_excel_no_interpreta_titulos_como_formulas(): void
    {
        $this->documento($this->asignatura('=HYPERLINK("http://malo.example","x")'), '=1+1', 20, 30, '2026-10-10 12:00:00', '+SUMA(A1)');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $respuesta = $this->get(route('reportes.tiempos.excel'))->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($ruta, $respuesta->getContent());
        $libro = IOFactory::load($ruta);
        unlink($ruta);

        foreach ([['Resumen', 'A19', '=1+1'], ['Resumen', 'B19', '=HYPERLINK("http://malo.example","x")'], ['Detalle', 'B2', '=1+1'], ['Detalle', 'A2', '+SUMA(A1)']] as [$hoja, $celda, $esperado]) {
            $c = $libro->getSheetByName($hoja)->getCell($celda);
            $this->assertSame(DataType::TYPE_STRING, $c->getDataType(), "{$hoja}!{$celda} debe ser texto");
            $this->assertSame($esperado, $c->getValue());
        }
    }

    public function test_sin_datos_no_genera_excel(): void
    {
        $this->actingAs($this->usuario(User::ROLE_LIDER))
            ->get(route('reportes.tiempos.excel'))
            ->assertRedirect(route('reportes.tiempos.index'))
            ->assertSessionHas('error');
    }

    public function test_los_enlaces_del_tablero_y_del_menu_apuntan_al_reporte(): void
    {
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('dashboard'))->assertOk()->assertSee(route('reportes.tiempos.index'), false);
    }
}
