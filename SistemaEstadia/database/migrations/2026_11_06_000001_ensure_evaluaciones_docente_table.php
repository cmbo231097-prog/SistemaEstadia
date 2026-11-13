<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El esquema MySQL ya contiene esta tabla; solo se crea en instalaciones nuevas o pruebas.
    public function up(): void
    {
        if (Schema::hasTable('evaluaciones_docente')) {
            return;
        }

        Schema::create('evaluaciones_docente', function (Blueprint $table) {
            $table->increments('id_eval_docente');
            $table->unsignedInteger('id_corpus');
            $table->unsignedInteger('id_docente');
            $table->decimal('calificacion_global', 5, 2);
            $table->text('comentarios_cualitativos')->nullable();
            $table->decimal('tiempo_evaluacion_minutos', 6, 2);
            $table->timestamp('fecha_evaluacion')->nullable()->useCurrent();

            // Una evaluación humana por documento; la comparación con la IA se hace por id_corpus.
            $table->unique('id_corpus');
            $table->foreign('id_corpus')->references('id_corpus')->on('corpus_documentos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('id_docente')->references('id_usuario')->on('usuarios')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // No se elimina: la tabla pertenece al esquema base del sistema.
    }
};
