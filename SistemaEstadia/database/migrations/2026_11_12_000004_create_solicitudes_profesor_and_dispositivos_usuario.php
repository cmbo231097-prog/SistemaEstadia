<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solicitudes de un estudiante para convertirse en profesor; las atiende el Líder cambiando su rol.
        if (! Schema::hasTable('solicitudes_profesor')) {
            Schema::create('solicitudes_profesor', function (Blueprint $table) {
                $table->increments('id_solicitud');
                $table->integer('id_usuario');
                $table->string('estado', 20)->default('pendiente');
                $table->timestamps();

                $table->index(['id_usuario', 'estado']);
            });
        }

        // Navegadores desde los que cada usuario ya inició sesión (solo se guarda el hash del identificador).
        if (! Schema::hasTable('dispositivos_usuario')) {
            Schema::create('dispositivos_usuario', function (Blueprint $table) {
                $table->increments('id_dispositivo');
                $table->integer('id_usuario');
                $table->char('token_hash', 64);
                $table->string('descripcion', 120);
                $table->string('ip', 45)->nullable();
                $table->timestamp('ultimo_acceso')->nullable();
                $table->timestamps();

                $table->unique(['id_usuario', 'token_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_usuario');
        Schema::dropIfExists('solicitudes_profesor');
    }
};
