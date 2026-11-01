<?php

namespace Tests\Feature;

use App\Models\RespaldoBd;
use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RespaldoBdTest extends TestCase
{
    use RefreshDatabase;

    private FakeBackup $fake;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fake = new FakeBackup();
        $this->app->instance(DatabaseBackup::class, $this->fake);
    }

    private function usuario(string $rol): User
    {
        static $n = 0;
        $m = 'USR'.str_pad((string) ++$n, 4, '0', STR_PAD_LEFT);

        return User::create([
            'matricula' => $m, 'nombre' => 'N', 'apellidos' => $m,
            'correo_institucional' => strtolower($m).'@upemor.edu.mx',
            'password_hash' => 'password123', 'id_rol' => Role::idOCrearPorClave($rol),
            'estatus' => User::ESTATUS_ACTIVO,
        ]);
    }

    public function test_solo_el_lider_accede(): void
    {
        $this->get(route('respaldos.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario(User::ROLE_PROFESOR))->get(route('respaldos.index'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_ESTUDIANTE))->post(route('respaldos.store'))->assertForbidden();
        $this->actingAs($this->usuario(User::ROLE_LIDER))->get(route('respaldos.index'))
            ->assertOk()->assertSee('Mantenimiento y Respaldo de Base de Datos');
    }

    public function test_genera_respaldo_registra_metadatos_y_permite_descargar(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER);

        $this->actingAs($lider)->post(route('respaldos.store'))->assertRedirect(route('respaldos.index'))->assertSessionHas('status');

        $r = RespaldoBd::firstOrFail();
        $this->assertMatchesRegularExpression('/^backup_upemor_\d{4}_\d{2}_\d{2}_\d{4}\.sql$/', $r->nombre_archivo);
        $this->assertSame($lider->id_usuario, $r->id_usuario_creador);
        $this->assertSame('Completo (3 tablas)', $r->alcance_tablas);
        Storage::assertExists('backups/'.$r->nombre_archivo);

        $this->actingAs($lider)->get(route('respaldos.index'))->assertSee($r->nombre_archivo)->assertSee('Completo (3 tablas)');
        $this->actingAs($lider)->get(route('respaldos.descargar', $r))->assertOk()->assertDownload($r->nombre_archivo);
    }

    public function test_error_de_volcado_muestra_alerta_y_no_registra(): void
    {
        $this->fake->fallarVolcado = true;

        $this->actingAs($this->usuario(User::ROLE_LIDER))->post(route('respaldos.store'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('respaldos_bd', 0);
    }

    public function test_espacio_insuficiente_cancela(): void
    {
        $this->fake->espacio = 1024;

        $this->actingAs($this->usuario(User::ROLE_LIDER))->post(route('respaldos.store'))
            ->assertSessionHas('error', 'Espacio insuficiente en disco para generar el respaldo.');
        $this->assertDatabaseCount('respaldos_bd', 0);
    }

    public function test_restaura_desde_archivo_valido_con_copia_previa(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER);
        $archivo = UploadedFile::fake()->createWithContent('copia.sql', "-- MySQL dump 10.13\nCREATE TABLE a (id int);\n-- Dump completed\n");

        $this->actingAs($lider)->post(route('respaldos.restaurar-archivo'), ['archivo' => $archivo])
            ->assertSessionHas('status');

        $this->assertCount(1, $this->fake->cargados);
        $this->assertDatabaseCount('respaldos_bd', 1);
        $this->assertStringContainsString('_previo_restauracion', RespaldoBd::first()->nombre_archivo);
        $this->assertSame([], Storage::files('backups/tmp'));
    }

    public function test_rechaza_archivos_invalidos_sin_tocar_la_base(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER);

        $this->actingAs($lider)->post(route('respaldos.restaurar-archivo'), [
            'archivo' => UploadedFile::fake()->create('foto.png', 10),
        ])->assertSessionHasErrors('archivo');

        $this->actingAs($lider)->post(route('respaldos.restaurar-archivo'), [
            'archivo' => UploadedFile::fake()->createWithContent('malo.sql', 'DROP DATABASE x;'),
        ])->assertSessionHas('error');

        $this->actingAs($lider)->post(route('respaldos.restaurar-archivo'), [
            'archivo' => UploadedFile::fake()->createWithContent('trunco.sql', "-- MySQL dump 10.13\nCREATE TABLE a (id int);\n"),
        ])->assertSessionHas('error');

        $this->assertSame([], $this->fake->cargados);
        $this->assertDatabaseCount('respaldos_bd', 0);
    }

    public function test_si_la_restauracion_falla_se_revierte_con_la_copia_previa(): void
    {
        $this->fake->fallarCarga = true;
        $archivo = UploadedFile::fake()->createWithContent('copia.sql', "-- MySQL dump 10.13\nCREATE TABLE a (id int);\n-- Dump completed\n");

        $this->actingAs($this->usuario(User::ROLE_LIDER))->post(route('respaldos.restaurar-archivo'), ['archivo' => $archivo])
            ->assertSessionHas('error');

        $this->assertCount(2, $this->fake->cargados); // intento fallido + reversión
        $this->assertStringContainsString('_previo_restauracion', $this->fake->cargados[1]);
    }

    public function test_restaura_y_elimina_desde_historial(): void
    {
        $lider = $this->usuario(User::ROLE_LIDER);
        $this->actingAs($lider)->post(route('respaldos.store'));
        $r = RespaldoBd::firstOrFail();

        $this->actingAs($lider)->post(route('respaldos.restaurar', $r))->assertSessionHas('status');
        $this->assertDatabaseCount('respaldos_bd', 2);

        $this->actingAs($lider)->delete(route('respaldos.destroy', $r))->assertSessionHas('status');
        Storage::assertMissing('backups/'.$r->nombre_archivo);
        $this->assertDatabaseCount('respaldos_bd', 1);
    }
}

/** Sustituye mysqldump/mysql: las pruebas corren sobre SQLite. */
class FakeBackup extends DatabaseBackup
{
    public bool $fallarVolcado = false;
    public bool $fallarCarga = false;
    public int $espacio = PHP_INT_MAX;
    public array $cargados = [];

    public function tablas(): array { return ['a', 'b', 'c']; }
    public function tamanoMb(): float { return 1.0; }
    public function gestor(): array { return ['motor' => 'MariaDB', 'version' => '10.11']; }
    public function espacioLibreBytes(string $directorio): int { return $this->espacio; }

    public function volcar(string $destino, bool $comprimir = false): void
    {
        if ($this->fallarVolcado) {
            throw new RuntimeException('sin privilegios');
        }
        file_put_contents($destino, "-- MySQL dump 10.13\nCREATE TABLE a (id int);\n-- Dump completed\n");
    }

    public function cargar(string $origen): void
    {
        $this->cargados[] = $origen;
        if ($this->fallarCarga && count($this->cargados) === 1) {
            throw new RuntimeException('error sql');
        }
    }
}
