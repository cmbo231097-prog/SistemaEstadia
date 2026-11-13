<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Seguimiento del envío de un lote de PDFs a la IA (un documento a la vez).
        if (! Schema::hasTable('lotes_evaluacion_ia')) {
            Schema::create('lotes_evaluacion_ia', function (Blueprint $table) {
                $table->increments('id_lote');
                $table->integer('id_usuario');
                $table->integer('id_evidencia');
                $table->integer('id_grupo');
                $table->string('estado', 20)->default('en_cola');
                $table->unsignedInteger('total');
                $table->unsignedInteger('exitosos')->default(0);
                $table->unsignedInteger('fallidos')->default(0);
                // [{id_corpus, equipo, estado: pendiente|procesando|ok|error, mensaje}]
                $table->json('documentos');
                $table->timestamp('finalizado_en')->nullable();
                $table->timestamps();

                $table->index(['id_evidencia', 'id_grupo', 'estado']);
            });
        }

        // El esquema MySQL institucional ya contiene esta tabla; solo se crea en instalaciones nuevas o pruebas.
        if (! Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->increments('id_notificacion');
                $table->integer('id_usuario');
                $table->string('titulo', 120);
                $table->text('mensaje');
                $table->string('tipo_evento', 60);
                $table->string('canal', 20)->nullable()->default('sistema');
                $table->boolean('leido')->nullable()->default(false);
                $table->timestamp('fecha_envio')->nullable()->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_evaluacion_ia');
    }
};
