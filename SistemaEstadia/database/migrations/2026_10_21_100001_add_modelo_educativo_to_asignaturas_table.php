<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asignaturas', 'modelo_educativo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->string('modelo_educativo', 20)->default('actual')->after('clave_asignatura');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('asignaturas', 'modelo_educativo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->dropColumn('modelo_educativo');
            });
        }
    }
};
