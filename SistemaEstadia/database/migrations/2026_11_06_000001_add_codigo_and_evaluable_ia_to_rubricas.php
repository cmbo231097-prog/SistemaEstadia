<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Código con el que el archivo de rúbricas identifica la evidencia (ej. GDS-EP1).
        if (! Schema::hasColumn('evidencias', 'codigo')) {
            Schema::table('evidencias', function (Blueprint $table) {
                $table->string('codigo', 30)->nullable();
            });
        }

        // Los criterios de desempeño no se evalúan desde el PDF, por lo que no se envían a la IA.
        if (! Schema::hasColumn('criterios_rubrica', 'evaluable_ia')) {
            Schema::table('criterios_rubrica', function (Blueprint $table) {
                $table->boolean('evaluable_ia')->default(true);
            });
        }
    }

    public function down(): void
    {
        // Las tablas pueden formar parte de un esquema institucional preexistente.
    }
};
