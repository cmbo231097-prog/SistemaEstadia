<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipos')) {
            Schema::create('equipos', function (Blueprint $table) {
                $table->increments('id_equipo');
                $table->string('nombre_equipo', 80);
                $table->unsignedInteger('id_grupo');
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('id_grupo')->references('id_grupo')->on('grupos')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        // Tabla puente sin tope de filas: el rango de integrantes (2 a 6) se valida en la aplicación.
        if (! Schema::hasTable('equipo_estudiantes')) {
            Schema::create('equipo_estudiantes', function (Blueprint $table) {
                $table->unsignedInteger('id_equipo');
                $table->unsignedInteger('id_estudiante');
                $table->timestamp('fecha_asignacion')->nullable()->useCurrent();

                $table->primary(['id_equipo', 'id_estudiante']);
                $table->foreign('id_equipo')->references('id_equipo')->on('equipos')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('id_estudiante')->references('id_usuario')->on('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('grupo_estudiantes')) {
            Schema::create('grupo_estudiantes', function (Blueprint $table) {
                $table->unsignedInteger('id_grupo');
                $table->unsignedInteger('id_estudiante');
                $table->timestamp('fecha_inscripcion')->nullable()->useCurrent();

                $table->primary(['id_grupo', 'id_estudiante']);
                $table->foreign('id_grupo')->references('id_grupo')->on('grupos')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('id_estudiante')->references('id_usuario')->on('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Tablas compartidas con el esquema base: no se eliminan.
    }
};
