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
use App\Services\ComparativaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteDiscrepanciaTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    private function usuario(string $clave, string $nombre = 'Usuario'): User
    {
        $matricula = 'USR'.str_pad((string) ++$this->consecutivo, 4, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $matricula,
            'nombre' => $nombre,
            'apellidos' => 'Apellido'.$this->consecutivo,
            'correo_institucional' => strtolower($matricula).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($clave),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    /**
     * Crea una evidencia del profesor con un documento ya comparado.
     * Rúbrica de un solo criterio evaluable (100 pts): IA y docente califican ese criterio.
     */
    private function comparacion(User $profesor, string $titulo, float $docente, float $ia, string $fecha, string $equipo = 'Equipo 1'): CorpusDocumento
    {
        $asignatura = Asignatura::create([
            'clave_asignatura' => 'IID-'.++$this->consecutivo,
            'modelo_educativo' => Asignatura::MODELO_ACTUAL,
            'nombre_asignatura' => 'Asignatura '.$this->consecutivo,
            'programa_educativo' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'cuatrimestre' => 5,
        ]);
        $periodo = Periodo::firstOrCreate(['codigo_periodo' => '2026-3'], [
            'nombre_periodo' => 'Septiembre - Diciembre 2026', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'activo' => true,
        ]);
        $grupo = Grupo::create([
            'clave_grupo' => 'G'.$this->consecutivo, 'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $profesor->id_usuario, 'id_periodo' => $periodo->id_periodo,
        ]);
        $evidencia = Evidencia::create([
            'titulo' => $titulo, 'tipo_evidencia' => 'reporte', 'fecha_limite' => now(), 'id_docente' => $profesor->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);
        $evidencia->criteriosRubrica()->create([
            'id_criterio' => 1, 'nombre_criterio' => 'Contenido', 'descripcion_criterio' => 'Contenido', 'puntaje_maximo' => 100, 'evaluable_ia' => true,
        ]);
        $doc = CorpusDocumento::create([
            'id_evidencia' => $evidencia->id_evidencia,
            'id_equipo' => Equipo::create(['nombre_equipo' => $equipo, 'id_grupo' => $grupo->id_grupo])->id_equipo,
            'nombre_archivo_original' => 'x.pdf', 'ruta_archivo_original' => 'corpus/x.pdf', 'estado_sanitizacion' => CorpusDocumento::SANITIZADO,
        ]);
        $evalIa = EvaluacionIa::create([
            'id_corpus' => $doc->id_corpus, 'modelo_llm' => 'gemini-test', 'calificacion_global' => $ia,
            'retroalimentacion_cualitativa' => 'IA', 'tiempo_procesamiento_segundos' => 30, 'fecha_procesamiento' => now(),
        ]);
        $evalIa->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => 1, 'calificacion' => $ia]);
        $evalDocente = EvaluacionDocente::create([
            'id_corpus' => $doc->id_corpus, 'id_docente' => $profesor->id_usuario, 'calificacion_global' => $docente,
            'tiempo_evaluacion_minutos' => 10, 'fecha_evaluacion' => $fecha,
        ]);
        $evalDocente->criterios()->create(['id_evidencia' => $evidencia->id_evidencia, 'id_criterio' => 1, 'calificacion' => $docente]);
        app(ComparativaService::class)->actualizar($doc);

        return $doc;
    }

    public function test_solo_el_lider_accede_al_reporte_y_a_su_pdf(): void
    {
        $this->get(route('reportes.discrepancia.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_PROFESOR, User::ROLE_ESTUDIANTE] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('reportes.discrepancia.index'))->assertForbidden();
            $this->get(route('reportes.discrepancia.pdf'))->assertForbidden();
        }
        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('reportes.discrepancia.index'))->assertOk()
            ->assertSee('Reporte de Discrepancia Evaluativa');
    }

    public function test_muestra_la_grafica_de_barras_y_la_diferencia_porcentual(): void
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR, 'Ana');
        $this->comparacion($profesor, 'Reporte de árboles', 80, 88, '2026-10-10 12:00:00');
        $lider = $this->usuario(User::ROLE_LIDER);

        $this->actingAs($lider)->get(route('reportes.discrepancia.index'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Gráfica de barras comparativas', false)
            ->assertSee('Reporte de árboles')
            ->assertSee('Ana')
            ->assertSee('Dif. 10.0 %')                 // |88 − 80| / 80 × 100
            ->assertSee('Diferencia porcentual promedio')
            ->assertSee(route('reportes.discrepancia.pdf'), false);
    }

    public function test_filtra_por_profesor_y_por_rango_de_fechas(): void
    {
        $ana = $this->usuario(User::ROLE_PROFESOR, 'Ana');
        $beto = $this->usuario(User::ROLE_PROFESOR, 'Beto');
        $this->comparacion($ana, 'Evidencia de Ana', 80, 88, '2026-10-10 12:00:00');
        $this->comparacion($beto, 'Evidencia de Beto', 70, 60, '2026-11-20 09:00:00');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('reportes.discrepancia.index'))->assertSee('Evidencia de Ana')->assertSee('Evidencia de Beto');

        $this->get(route('reportes.discrepancia.index', ['profesor' => $ana->id_usuario]))
            ->assertSee('Evidencia de Ana')->assertDontSee('Evidencia de Beto');

        $this->get(route('reportes.discrepancia.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Evidencia de Beto')->assertDontSee('Evidencia de Ana');

        // El día final se incluye completo.
        $this->get(route('reportes.discrepancia.index', ['desde' => '2026-10-10', 'hasta' => '2026-10-10']))
            ->assertSee('Evidencia de Ana')->assertDontSee('Evidencia de Beto');

        $this->get(route('reportes.discrepancia.index', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertSee('No hay comparaciones')->assertDontSee('<svg', false);
    }

    public function test_valida_fechas_y_profesor(): void
    {
        $ana = $this->usuario(User::ROLE_PROFESOR, 'Ana');
        $lider = $this->usuario(User::ROLE_LIDER);
        $this->comparacion($ana, 'Evidencia de Ana', 80, 88, '2026-10-10 12:00:00');
        $this->actingAs($lider);

        $this->get(route('reportes.discrepancia.index', ['desde' => '2026-11-10', 'hasta' => '2026-11-01']))
            ->assertOk()->assertSee('no puede ser anterior')->assertDontSee('<svg', false);
        $this->get(route('reportes.discrepancia.index', ['desde' => 'ayer']))->assertOk()->assertSee('fecha inicial no es válida');
        $this->get(route('reportes.discrepancia.index', ['profesor' => $lider->id_usuario]))->assertOk()->assertSee('Selecciona un profesor');
        $this->get(route('reportes.discrepancia.index', ['profesor' => 'x']))->assertOk()->assertSee('Selecciona un profesor');

        $this->get(route('reportes.discrepancia.pdf', ['hasta' => '2026-01-01', 'desde' => '2026-02-01']))
            ->assertRedirect()->assertSessionHasErrors('hasta');
    }

    public function test_descarga_el_pdf_con_la_grafica_y_respeta_los_filtros(): void
    {
        $ana = $this->usuario(User::ROLE_PROFESOR, 'Ana');
        $this->comparacion($ana, 'Reporte de árboles', 80, 88, '2026-10-10 12:00:00');
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $respuesta = $this->get(route('reportes.discrepancia.pdf', ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'profesor' => $ana->id_usuario]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="reporte-discrepancia_', $respuesta->headers->get('Content-Disposition'));

        $pdf = $respuesta->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    public function test_sin_comparaciones_no_genera_pdf(): void
    {
        $this->actingAs($this->usuario(User::ROLE_LIDER))
            ->get(route('reportes.discrepancia.pdf'))
            ->assertRedirect(route('reportes.discrepancia.index'))
            ->assertSessionHas('error');
    }

    public function test_el_pdf_soporta_muchas_evidencias_acentos_y_textos_largos(): void
    {
        $profesor = $this->usuario(User::ROLE_PROFESOR, 'José Ángel');
        foreach (range(1, 19) as $i) {
            $this->comparacion($profesor, "Evidencia número {$i}: análisis de algoritmos y estructuras de datos avanzadas — ñandú “prueba”", 60 + $i, 95 - $i, '2026-10-10 12:00:00', "Equipo ñ{$i}");
        }
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $respuesta = $this->get(route('reportes.discrepancia.pdf'))->assertOk();

        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        // 19 evidencias = 3 gráficas de hasta 8 barras, más las tablas: el documento ocupa varias páginas.
        preg_match_all('#/Type /Page\b#', $respuesta->getContent(), $paginas);
        $this->assertGreaterThanOrEqual(3, count($paginas[0]));
    }

    public function test_el_tablero_del_lider_enlaza_al_reporte(): void
    {
        $this->actingAs($this->usuario(User::ROLE_LIDER));

        $this->get(route('dashboard'))->assertOk()->assertSee(route('reportes.discrepancia.index'), false);
    }
}
