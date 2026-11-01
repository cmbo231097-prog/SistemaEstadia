<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El esquema MySQL ya existe; solo se crea si falta (p. ej. entornos nuevos o pruebas).
    public function up(): void
    {
        if (Schema::hasTable('corpus_documentos')) {
            return;
        }

        Schema::create('corpus_documentos', function (Blueprint $table) {
            $table->increments('id_corpus');
            $table->unsignedInteger('id_evidencia');
            $table->unsignedInteger('id_estudiante')->nullable();
            $table->unsignedInteger('id_equipo')->nullable();
            $table->string('nombre_archivo_original', 255);
            $table->string('ruta_archivo_original', 500);
            $table->string('ruta_archivo_sanitizado', 500)->nullable();
            $table->enum('estado_sanitizacion', ['pendiente', 'sanitizado', 'error'])->default('pendiente');
            $table->timestamp('fecha_subida')->nullable()->useCurrent();

            $table->foreign('id_evidencia')->references('id_evidencia')->on('evidencias')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('id_estudiante')->references('id_usuario')->on('usuarios')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('id_equipo')->references('id_equipo')->on('equipos')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // No se elimina: la tabla pertenece al esquema base del sistema.
    }
};
