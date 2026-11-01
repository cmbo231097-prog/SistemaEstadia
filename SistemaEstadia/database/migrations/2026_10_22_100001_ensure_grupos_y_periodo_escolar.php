<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('periodos_escolares')) {
            Schema::create('periodos_escolares', function (Blueprint $table) {
                $table->increments('id_periodo');
                $table->string('codigo_periodo', 30)->unique();
                $table->string('nombre_periodo', 80);
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->boolean('activo')->nullable()->default(true);
            });
        }

        // grupos.id_periodo es obligatorio: se garantiza al menos un periodo vigente para poder registrar grupos.
        if (DB::table('periodos_escolares')->count() === 0) {
            DB::table('periodos_escolares')->insert([
                'codigo_periodo' => '2026-3',
                'nombre_periodo' => 'Septiembre - Diciembre 2026',
                'fecha_inicio' => '2026-09-01',
                'fecha_fin' => '2026-12-31',
                'activo' => 1,
            ]);
        }

        if (! Schema::hasTable('grupos')) {
            Schema::create('grupos', function (Blueprint $table) {
                $table->increments('id_grupo');
                $table->string('clave_grupo', 20);
                $table->unsignedInteger('id_asignatura');
                $table->unsignedInteger('id_docente');
                $table->unsignedInteger('id_periodo');

                $table->foreign('id_asignatura')->references('id_asignatura')->on('asignaturas')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('id_docente')->references('id_usuario')->on('usuarios')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('id_periodo')->references('id_periodo')->on('periodos_escolares')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Tablas compartidas con el esquema base: no se eliminan.
    }
};
