<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evidencias')) {
            Schema::create('evidencias', function (Blueprint $table) {
                $table->increments('id_evidencia');
                $table->string('titulo', 120);
                $table->enum('tipo_evidencia', ['ensayo', 'reporte', 'codigo', 'proyecto']);
                $table->enum('nivel_retroalimentacion_ia', ['superficial', 'exhaustiva'])->nullable()->default('exhaustiva');
                $table->text('descripcion_entregables');
                $table->dateTime('fecha_limite');
                $table->unsignedInteger('id_docente');
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('id_docente')->references('id_usuario')->on('usuarios')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('evidencia_grupos')) {
            Schema::create('evidencia_grupos', function (Blueprint $table) {
                $table->unsignedInteger('id_evidencia');
                $table->unsignedInteger('id_grupo');
                $table->timestamp('fecha_asignacion')->nullable()->useCurrent();

                $table->primary(['id_evidencia', 'id_grupo']);
                $table->foreign('id_evidencia')->references('id_evidencia')->on('evidencias')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('id_grupo')->references('id_grupo')->on('grupos')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Tablas compartidas con el esquema base: no se eliminan.
    }
};
