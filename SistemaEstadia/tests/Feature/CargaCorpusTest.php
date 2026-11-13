<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\CorpusDocumento;
use App\Models\Equipo;
use App\Models\Evidencia;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\User;
use App\Services\PdfSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class CargaCorpusTest extends TestCase
{
    use RefreshDatabase;

    private int $consecutivo = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function usuario(string $rol): User
    {
        $m = 'USR'.str_pad((string) ++$this->consecutivo, 4, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $m,
            'nombre' => 'Nombre',
            'apellidos' => $m,
            'correo_institucional' => strtolower($m).'@upemor.edu.mx',
            'password_hash' => 'password123',
            'id_rol' => Role::idOCrearPorClave($rol),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    /** Escenario: docente con grupo, 2 equipos y una evidencia asignada al grupo. */
    private function escenario(?User $docente = null): array
    {
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
            'activo' => 1,
        ]);
        $grupo = Grupo::create([
            'clave_grupo' => '9A',
            'id_asignatura' => $asignatura->id_asignatura,
            'id_docente' => $docente->id_usuario,
            'id_periodo' => $periodo->id_periodo,
        ]);
        $e1 = Equipo::create(['nombre_equipo' => 'Equipo 1', 'id_grupo' => $grupo->id_grupo]);
        $e2 = Equipo::create(['nombre_equipo' => 'Equipo 2', 'id_grupo' => $grupo->id_grupo]);
        $evidencia = Evidencia::create([
            'titulo' => 'Práctica 3: Árboles Binarios',
            'tipo_evidencia' => 'reporte',
            'descripcion_entregables' => 'Reporte en PDF',
            'fecha_limite' => '2026-11-01 23:59:00',
            'id_docente' => $docente->id_usuario,
        ]);
        $evidencia->grupos()->attach($grupo->id_grupo);

        return compact('docente', 'grupo', 'e1', 'e2', 'evidencia');
    }

    private function pdf(string $nombre, int $paginas = 3): UploadedFile
    {
        $pdf = new Fpdi();
        for ($i = 1; $i <= $paginas; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, $i === 1 ? 'PORTADA Alumno Secreto' : "Contenido pagina {$i}");
        }
        $ruta = sys_get_temp_dir().'/'.uniqid('t_').'.pdf';
        file_put_contents($ruta, $pdf->Output('S'));

        return new UploadedFile($ruta, $nombre, 'application/pdf', null, true);
    }

    private function subir(array $e, array $archivos, array $equipos)
    {
        return $this->actingAs($e['docente'])->post(route('corpus.store'), [
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_grupo' => $e['grupo']->id_grupo,
            'archivos' => $archivos,
            'equipos' => $equipos,
        ]);
    }

    public function test_acceso_por_rol(): void
    {
        $this->get(route('corpus.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE))->get(route('corpus.index'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_PROFESOR))->get(route('corpus.index'))
            ->assertOk()->assertSee('Carga y Sanitización de Corpus Documental');
    }

    public function test_sube_y_suprime_la_portada_guardando_en_disco(): void
    {
        $e = $this->escenario();

        $this->subir($e, [$this->pdf('Practica3_Equipo1.pdf', 4)], [$e['e1']->id_equipo])
            ->assertSessionHas('status');

        $doc = CorpusDocumento::firstOrFail();
        $this->assertSame('sanitizado', $doc->estado_sanitizacion);
        $this->assertSame('Practica3_Equipo1.pdf', $doc->nombre_archivo_original);
        $this->assertSame($e['e1']->id_equipo, $doc->id_equipo);
        Storage::assertExists($doc->ruta_archivo_original);
        Storage::assertExists($doc->ruta_archivo_sanitizado);

        $sanitizador = app(PdfSanitizer::class);
        $this->assertSame(4, $sanitizador->contarPaginas(Storage::path($doc->ruta_archivo_original)));
        $this->assertSame(3, $sanitizador->contarPaginas(Storage::path($doc->ruta_archivo_sanitizado)));
        $this->assertStringNotContainsString('Alumno Secreto', (string) file_get_contents(Storage::path($doc->ruta_archivo_sanitizado)));
    }

    public function test_rechaza_formatos_que_no_son_pdf(): void
    {
        $e = $this->escenario();
        $falso = UploadedFile::fake()->create('tarea.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $camuflado = UploadedFile::fake()->createWithContent('malo.pdf', 'esto no es un pdf');

        $this->subir($e, [$falso], [$e['e1']->id_equipo])->assertSessionHasErrors('archivos.0');
        $this->subir($e, [$camuflado], [$e['e1']->id_equipo])->assertSessionHasErrors();
        $this->assertDatabaseCount('corpus_documentos', 0);
    }

    public function test_pdf_de_una_sola_pagina_queda_en_error_y_se_puede_reintentar_o_eliminar(): void
    {
        $e = $this->escenario();

        $this->subir($e, [$this->pdf('Equipo1.pdf', 1)], [$e['e1']->id_equipo])->assertSessionHas('error');
        $doc = CorpusDocumento::firstOrFail();
        $this->assertSame('error', $doc->estado_sanitizacion);

        $this->actingAs($e['docente'])->delete(route('corpus.destroy', $doc))->assertSessionHas('status');
        $this->assertDatabaseCount('corpus_documentos', 0);
        Storage::assertMissing($doc->ruta_archivo_original);
    }

    public function test_valida_equipos_ajenos_y_duplicados(): void
    {
        $e = $this->escenario();
        $otro = $this->escenario();

        $this->subir($e, [$this->pdf('a.pdf')], [$otro['e1']->id_equipo])->assertSessionHasErrors('equipos');
        $this->subir($e, [$this->pdf('a.pdf'), $this->pdf('b.pdf')], [$e['e1']->id_equipo, $e['e1']->id_equipo])->assertSessionHasErrors('equipos');

        $this->subir($e, [$this->pdf('a.pdf')], [$e['e1']->id_equipo])->assertSessionHas('status');
        $this->subir($e, [$this->pdf('b.pdf')], [$e['e1']->id_equipo])->assertSessionHasErrors('equipos');
        $this->assertDatabaseCount('corpus_documentos', 1);
    }

    public function test_un_profesor_no_puede_cargar_ni_ver_corpus_ajeno(): void
    {
        $e = $this->escenario();
        $intruso = $this->usuario(User::ROLE_PROFESOR);

        $this->actingAs($intruso)->post(route('corpus.store'), [
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_grupo' => $e['grupo']->id_grupo,
            'archivos' => [$this->pdf('a.pdf')],
            'equipos' => [$e['e1']->id_equipo],
        ])->assertForbidden();

        $this->subir($e, [$this->pdf('a.pdf')], [$e['e1']->id_equipo]);
        $doc = CorpusDocumento::firstOrFail();
        $this->actingAs($intruso)->get(route('corpus.ver', $doc))->assertForbidden();
        $this->actingAs($intruso)->delete(route('corpus.destroy', $doc))->assertForbidden();
        $this->actingAs($e['docente'])->get(route('corpus.ver', $doc))->assertOk();
    }

    public function test_avisa_por_que_no_se_puede_anonimizar_si_el_grupo_no_tiene_equipos(): void
    {
        $e = $this->escenario();
        Equipo::where('id_grupo', $e['grupo']->id_grupo)->delete();

        $this->actingAs($e['docente'])
            ->get(route('corpus.index', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]))
            ->assertOk()
            ->assertSee('No se puede cargar ni anonimizar documentos todavía.')
            ->assertSee('no tiene equipos registrados: sin equipos no hay PDFs que anonimizar.')
            ->assertSee(route('equipos.index'));
    }

    public function test_con_equipos_no_muestra_el_aviso_de_equipos_faltantes(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['docente'])
            ->get(route('corpus.index', ['grupo' => $e['grupo']->id_grupo, 'evidencia' => $e['evidencia']->id_evidencia]))
            ->assertOk()
            ->assertDontSee('No se puede cargar ni anonimizar documentos todavía.')
            ->assertSee('Esta evidencia aún no tiene PDFs cargados.');
    }

    public function test_sanitizar_lote_procesa_pendientes_y_la_vista_lo_muestra(): void
    {
        $e = $this->escenario();
        $ruta = Storage::putFileAs('corpus/x', $this->pdf('Equipo2.pdf', 3), 'orig.pdf');
        CorpusDocumento::create([
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_equipo' => $e['e2']->id_equipo,
            'nombre_archivo_original' => 'Equipo2.pdf',
            'ruta_archivo_original' => $ruta,
        ]);

        $this->actingAs($e['docente'])->get(route('corpus.index'))
            ->assertOk()->assertSee('Pendiente de recorte')->assertSee('Equipo2.pdf');

        $this->actingAs($e['docente'])->post(route('corpus.sanitizar-lote'), [
            'id_evidencia' => $e['evidencia']->id_evidencia,
            'id_grupo' => $e['grupo']->id_grupo,
        ])->assertSessionHas('status');

        $this->assertSame('sanitizado', CorpusDocumento::first()->estado_sanitizacion);
        $this->actingAs($e['docente'])->get(route('corpus.index'))->assertSee('Portada suprimida')->assertSee('2 págs. restantes');
    }
}
