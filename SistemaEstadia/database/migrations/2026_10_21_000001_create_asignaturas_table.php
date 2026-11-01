<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asignaturas')) {
            Schema::create('asignaturas', function (Blueprint $table) {
                $table->increments('id_asignatura');
                $table->string('clave_asignatura', 20)->unique();
                $table->string('nombre_asignatura', 120);
                $table->string('programa_educativo', 100);
                $table->unsignedTinyInteger('cuatrimestre');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};
