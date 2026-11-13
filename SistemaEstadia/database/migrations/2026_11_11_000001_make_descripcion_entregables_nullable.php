<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El formulario ya no captura los entregables; la rúbrica define qué se evalúa.
        if (Schema::hasColumn('evidencias', 'descripcion_entregables')) {
            Schema::table('evidencias', function (Blueprint $table) {
                $table->text('descripcion_entregables')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Las tablas pueden formar parte de un esquema institucional preexistente.
    }
};
