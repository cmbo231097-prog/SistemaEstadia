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
use App\Services\RendimientoGrupalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class RendimientoGrupalTest extends TestCase
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

    /**
     * Grupo con una evidencia de 3 rubros (Contenido 60 y Redacción 25 los evalúa la IA; Desempeño 15 solo el docente) y:
     * - Equipo 1: docente [54, 20, 10] = 84; IA [50, 20] = 70
     * - Equipo 2: docente [40, 15, 12] = 67; IA [45, 22] = 67
     * - Equipo 3: evaluación docente antigua (solo total 75), sin IA
     * - Equipo 4: sin documento (no aparece)
     */
    private function escenario(?User $profesor = null, array $nombres = []): array
    {
        $profesor ??= $this->usuario(User::ROLE_PROFESOR);
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.++$this->consecutivo, 'modelo_educativo' => Asignatura::MODELO_ACTUAL, 'nombre_asignatura' => 'Estructuras de Datos '.$this->consecutivo,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital', 'cuatrimestre' => 5,
        ]);
        $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'activo' => true,
        ]);
        $grupo = Grupo::create([
            'clave_grupo' => '9'.chr(64 + $this->consecutivo), 'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $profesor->id_usuario, 'id_periodo' => $periodo->id_periodo,
        ]);
        $evidencia = Evidencia::create(['titulo' => 'Reporte de árboles '.$this->consecutivo, 'tipo_evidencia' => 'reporte', 'fecha_limite' => now(), 'id_docente' => $profesor->id_usuario]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        foreach ([[1, $nombres[1] ?? 'Contenido', 60, true], [2, 'Redacción', 25, true], [3, 'Desempeño', 15, false]] as [$id, $nombre, $puntos, $ia]) {
            $evidencia->criteriosRubrica()->create(['id_criterio' => $id, 'nombre_criterio' => $nombre, 'descripcion_criterio' => $nombre, 'puntaje_maximo' => $puntos, 'evaluable_ia' => $ia]);
        }

        $crear = function (string $equipo, ?array $docente, ?array $ia, ?float $global = null) use ($evidencia, $grupo, $profesor) {
            $doc = CorpusDocumento::create([
                'id_evidencia' => $evidencia->id_evidencia,
                'id_equipo' => Equipo::create(['nombre_equipo' => $equipo, 'id_grupo' => $grupo->id_grupo])->id_equipo,
                'nombre_archivo_original' => 'x.pdf', 'ruta_archivo_original' => 'corpus/x.pdf', 'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
            ]);
            if ($docente !== null || $global !== null) {
                $eval = EvaluacionDocente::create([
                    'id_corpus' => $doc->id_corpus, 'id_docente' => $profesor->id_usuario, 'calificacion_global' => $global ?? array_sum($docente),
                    'tiempo_evaluacion_minutos' => 10, 'fecha_evaluacion' => now(),
                ]);
                foreach ($docente ?? [] as $id => $puntos) {
                    $eval->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos, 'retroalimentacion' => $id === 1 ? 'Buen contenido' : null]);
                }
            }
            if ($ia !== null) {
                $evalIa = EvaluacionIa::create([
                    'id_corpus' => $doc->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => array_sum($ia),
                    'retroalimentacion_cualitativa' => 'IA', 'tiempo_procesamiento_segundos' => 30, 'fecha_procesamiento' => now(),
                ]);
                foreach ($ia as $id => $puntos) {
                    $evalIa->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => $id, 'calificacion' => $puntos, 'retroalimentacion' => $id === 1 ? 'Contenido sólido' : null]);
                }
            }
        };
        $crear($nombres['equipo1'] ?? 'Equipo 1', [1 => 54, 2 => 20, 3 => 10], [1 => 50, 2 => 20]);
        $crear('Equipo 2', [1 => 40, 2 => 15, 3 => 12], [1 => 45, 2 => 22]);
        $crear('Equipo 3', null, null, 75);
        Equipo::create(['nombre_equipo' => 'Equipo 4', 'id_grupo' => $grupo->id_grupo]);

        return compact('profesor', 'grupo', 'evidencia', 'asignatura');
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('rendimiento-grupal.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE));
        $this->get(route('rendimiento-grupal.index'))->assertForbidden();
        $this->get(route('rendimiento-grupal.excel'))->assertForbidden();

        $e = $this->escenario();
        $this->actingAs($e['profesor'])->get(route('rendimiento-grupal.index'))->assertOk()->assertSee('Reporte de Rendimiento Grupal');
        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('rendimiento-grupal.index'))->assertOk();
    }

    public function test_calcula_promedios_por_rubro_y_totales_del_grupo(): void
    {
        $e = $this->escenario();

        $datos = app(RendimientoGrupalService::class)->generar($e['grupo'], $e['evidencia']);

        $this->assertSame(['Equipo 1', 'Equipo 2', 'Equipo 3'], $datos['equipos']->pluck('equipo')->all());   // el equipo sin documento no aparece
        [$contenido, $redaccion, $desempeno] = $datos['rubros']->all();
        $this->assertSame(47.0, $contenido['docente_prom']);           // (54 + 40) / 2
        $this->assertSame(78.3, $contenido['docente_pct']);            // 47 / 60
        $this->assertSame(47.5, $contenido['ia_prom']);                // (50 + 45) / 2
        $this->assertSame(0.5, $contenido['diferencia']);
        $this->assertSame(17.5, $redaccion['docente_prom']);
        $this->assertSame(21.0, $redaccion['ia_prom']);
        $this->assertSame(11.0, $desempeno['docente_prom']);           // (10 + 12) / 2
        $this->assertNull($desempeno['ia_prom']);                      // la IA no califica este rubro
        $this->assertNull($desempeno['diferencia']);

        $r = $datos['resumen'];
        $this->assertSame(3, $r['equipos']);
        $this->assertSame(3, $r['con_docente']);
        $this->assertSame(2, $r['con_ia']);
        $this->assertSame(75.33, $r['docente_prom']);                  // (84 + 67 + 75) / 3
        $this->assertSame(68.5, $r['ia_prom']);                        // (70 + 67) / 2
        $this->assertSame(85.0, $r['ia_maximo']);
        $this->assertSame(64.5, $r['docente_en_rubros_ia']);           // (74 + 55) / 2: mismos rubros que la IA
        $this->assertSame(2, $r['comparados']);
    }

    public function test_arma_una_barra_apilada_por_equipo_y_fuente(): void
    {
        $e = $this->escenario();

        $barras = app(RendimientoGrupalService::class)->generar($e['grupo'], $e['evidencia'])['barras'];

        $this->assertSame(['Equipo 1 Docente', 'Equipo 1 IA', 'Equipo 2 Docente', 'Equipo 2 IA', 'Equipo 3 Docente'], $barras->map(fn ($b) => $b['equipo'].' '.$b['fuente'])->all());
        $this->assertSame([1 => 54.0, 2 => 20.0, 3 => 10.0], $barras[0]['segmentos']);
        $this->assertSame([1 => 50.0, 2 => 20.0], $barras[1]['segmentos']);   // la IA no trae el rubro de desempeño
        $this->assertTrue($barras[4]['sin_desglose']);
        $this->assertSame(75.0, $barras[4]['total']);
    }

    public function test_la_pantalla_muestra_grafica_rubros_y_detalle(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor'])->get(route('rendimiento-grupal.index'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Gráfica de barras apiladas', false)
            ->assertSee('Desempeño por rubro')
            ->assertSee('Solo docente')
            ->assertSee('Sin desglose por rubro')
            ->assertSee('75.33')
            ->assertSee('Buen contenido')
            ->assertSee('Contenido sólido')
            ->assertSee(htmlspecialchars(route('rendimiento-grupal.excel', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia])), false);
    }

    public function test_el_profesor_solo_ve_sus_grupos_y_no_descarga_los_ajenos(): void
    {
        $mio = $this->escenario();
        $ajeno = $this->escenario(nombres: ['equipo1' => 'Equipo Ajeno']);
        $this->actingAs($mio['profesor']);

        $this->get(route('rendimiento-grupal.index'))->assertOk()->assertDontSee('Equipo Ajeno')->assertSee($mio['evidencia']->titulo);
        $this->get(route('rendimiento-grupal.index', ['grupo' => $ajeno['grupo']->id_grupo, 'evidencia' => $ajeno['evidencia']->id_evidencia]))
            ->assertOk()->assertDontSee('Equipo Ajeno');

        $this->get(route('rendimiento-grupal.excel', ['grupo' => $ajeno['grupo']->id_grupo, 'evidencia' => $ajeno['evidencia']->id_evidencia]))->assertForbidden();
        // Evidencia propia pero de otro grupo, y evidencia ajena en un grupo propio.
        $this->get(route('rendimiento-grupal.excel', ['grupo' => $mio['grupo']->id_grupo, 'evidencia' => $ajeno['evidencia']->id_evidencia]))->assertNotFound();
        $this->get(route('rendimiento-grupal.excel'))->assertSessionHasErrors(['grupo', 'evidencia']);
    }

    public function test_el_excel_trae_resumen_grafica_apilada_y_detalle(): void
    {
        $e = $this->escenario();

        $respuesta = $this->actingAs($e['profesor'])
            ->get(route('rendimiento-grupal.excel', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]))
            ->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="reporte-rendimiento-grupal_', $respuesta->headers->get('Content-Disposition'));

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($ruta, $respuesta->getContent());
        $libro = IOFactory::load($ruta);
        $this->assertSame(['Resumen', 'Gráfica', 'Detalle por rubro'], $libro->getSheetNames());

        $resumen = $libro->getSheetByName('Resumen');
        $this->assertSame($e['evidencia']->titulo, $resumen->getCell('B4')->getValue());
        $this->assertEquals(3, $resumen->getCell('B7')->getValue());                    // equipos con documento
        $this->assertEquals(75.33, $resumen->getCell('B10')->getValue());               // promedio docente
        $this->assertEquals(68.5, $resumen->getCell('B11')->getValue());                // promedio IA
        $this->assertSame('Contenido', $resumen->getCell('A16')->getValue());           // primera fila de rubros
        $this->assertEquals(47, $resumen->getCell('D16')->getValue());
        $this->assertEquals(47.5, $resumen->getCell('F16')->getValue());
        $this->assertNull($resumen->getCell('F18')->getValue());                        // la IA no califica Desempeño

        $grafica = $libro->getSheetByName('Gráfica');
        $this->assertSame('Equipo · fuente', $grafica->getCell('A1')->getValue());
        $this->assertSame('Sin desglose por rubro', $grafica->getCell('E1')->getValue());
        $this->assertSame('Equipo 1 · Docente', $grafica->getCell('A2')->getValue());
        $this->assertEquals(54, $grafica->getCell('B2')->getValue());
        $this->assertEquals(10, $grafica->getCell('D2')->getValue());
        $this->assertSame('Equipo 1 · IA', $grafica->getCell('A3')->getValue());
        $this->assertNull($grafica->getCell('D3')->getValue());                         // sin rubro de desempeño para la IA
        $this->assertEquals(75, $grafica->getCell('E6')->getValue());                   // docente sin desglose

        $detalle = $libro->getSheetByName('Detalle por rubro');
        $this->assertSame('Equipo 1', $detalle->getCell('A2')->getValue());
        $this->assertSame('Buen contenido', $detalle->getCell('H2')->getValue());
        $this->assertEquals(-4, $detalle->getCell('G2')->getValue());                   // IA 50 − docente 54

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));
        $xml = (string) $zip->getFromName('xl/charts/chart1.xml');
        $zip->close();
        unlink($ruta);
        $this->assertStringContainsString('<c:barDir val="col"/>', $xml);
        $this->assertStringContainsString('<c:grouping val="stacked"/>', $xml);
        $this->assertStringContainsString('<c:overlap val="100"/>', $xml);              // sin esto Excel no apila las columnas
        $this->assertSame(4, substr_count($xml, '<c:ser>'));                            // 3 rubros + «sin desglose»
    }

    public function test_el_excel_no_interpreta_nombres_como_formulas(): void
    {
        $e = $this->escenario(nombres: [1 => '=1+1', 'equipo1' => '+SUMA(A1)']);

        $respuesta = $this->actingAs($e['profesor'])
            ->get(route('rendimiento-grupal.excel', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]))->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($ruta, $respuesta->getContent());
        $libro = IOFactory::load($ruta);
        unlink($ruta);

        foreach ([['Resumen', 'A16', '=1+1'], ['Resumen', 'A22', '+SUMA(A1)'], ['Gráfica', 'B1', '=1+1'], ['Detalle por rubro', 'B2', '=1+1'], ['Detalle por rubro', 'A2', '+SUMA(A1)']] as [$hoja, $celda, $esperado]) {
            $c = $libro->getSheetByName($hoja)->getCell($celda);
            $this->assertSame(DataType::TYPE_STRING, $c->getDataType(), "{$hoja}!{$celda} debe ser texto");
            $this->assertSame($esperado, $c->getValue());
        }
    }

    public function test_sin_documentos_no_genera_excel(): void
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR);
        $e = $this->escenario($profesor);
        CorpusDocumento::where('id_evidencia', $e['evidencia']->id_evidencia)->each(function ($d) {
            EvaluacionDocente::where('id_corpus', $d->id_corpus)->each(fn ($ev) => $ev->criterios()->delete());
            EvaluacionDocente::where('id_corpus', $d->id_corpus)->delete();
            EvaluacionIa::where('id_corpus', $d->id_corpus)->each(fn ($ev) => $ev->criterios()->delete());
            EvaluacionIa::where('id_corpus', $d->id_corpus)->delete();
            $d->delete();
        });

        $this->actingAs($profesor)->get(route('rendimiento-grupal.index'))->assertOk()->assertSee('aún no tiene documentos');
        $this->get(route('rendimiento-grupal.excel', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]))
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_el_menu_y_el_tablero_del_profesor_enlazan_al_reporte(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor'])->get(route('dashboard'))->assertOk()->assertSee(route('rendimiento-grupal.index'), false);
    }

    public function test_el_lider_ve_el_reporte_en_menu_y_tablero_y_consulta_grupos_de_cualquier_profesor(): void
    {
        $e = $this->escenario(nombres: ['equipo1' => 'Equipo Ajeno']);
        $lider = $this->usuario(User::ROLE_LIDER);
        $this->actingAs($lider);

        $this->get(route('dashboard'))->assertOk()->assertSee(route('rendimiento-grupal.index'), false);

        $parametros = ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia];
        $this->get(route('rendimiento-grupal.index', $parametros))->assertOk()
            ->assertSee('Rendimiento Grupal (XLSX)')
            ->assertSee('Equipo Ajeno');
        $this->get(route('rendimiento-grupal.excel', $parametros))->assertOk();
    }
}
