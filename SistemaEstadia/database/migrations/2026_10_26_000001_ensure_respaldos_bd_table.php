<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El esquema MySQL ya existe; solo se crea si falta (entornos nuevos o pruebas).
    public function up(): void
    {
        if (Schema::hasTable('respaldos_bd')) {
            return;
        }

        Schema::create('respaldos_bd', function (Blueprint $table) {
            $table->increments('id_respaldo');
            $table->string('nombre_archivo', 180);
            $table->decimal('tamano_mb', 6, 2);
            $table->string('alcance_tablas', 80)->nullable()->default('Completo (18 tablas)');
            $table->unsignedInteger('id_usuario_creador');
            $table->timestamp('fecha_respaldo')->nullable()->useCurrent();

            $table->foreign('id_usuario_creador')->references('id_usuario')->on('usuarios')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // No se elimina: la tabla pertenece al esquema base del sistema.
    }
};
