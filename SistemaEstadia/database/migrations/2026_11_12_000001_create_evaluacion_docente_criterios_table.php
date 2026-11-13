<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Calificación y retroalimentación opcional que el docente asigna a cada criterio de la rúbrica.
    public function up(): void
    {
        if (Schema::hasTable('evaluacion_docente_criterios')) {
            return;
        }

        // En MySQL las llaves foráneas exigen el mismo signo que la columna referenciada.
        $sinSigno = static function (string $tabla, string $columna): bool {
            if (DB::getDriverName() !== 'mysql') {
                return false;
            }

            $tipo = DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $tabla)
                ->where('COLUMN_NAME', $columna)
                ->value('COLUMN_TYPE');

            return is_string($tipo) && str_contains(strtolower($tipo), 'unsigned');
        };

        Schema::create('evaluacion_docente_criterios', function (Blueprint $table) use ($sinSigno) {
            $table->increments('id_eval_docente_criterio');
            $sinSigno('evaluaciones_docente', 'id_eval_docente')
                ? $table->unsignedInteger('id_eval_docente')
                : $table->integer('id_eval_docente');
            $sinSigno('evidencias', 'id_evidencia')
                ? $table->unsignedInteger('id_evidencia')
                : $table->integer('id_evidencia');
            $sinSigno('criterios_rubrica', 'id_criterio')
                ? $table->unsignedInteger('id_criterio')
                : $table->integer('id_criterio');
            $table->decimal('calificacion', 5, 2);
            $table->text('retroalimentacion')->nullable();

            $table->unique(['id_eval_docente', 'id_criterio']);
            $table->foreign('id_eval_docente')
                ->references('id_eval_docente')->on('evaluaciones_docente')
                ->cascadeOnDelete()->cascadeOnUpdate();
            // Igual que en la evaluación de IA: los criterios con calificaciones registradas son historial.
            $table->foreign(['id_evidencia', 'id_criterio'])
                ->references(['id_evidencia', 'id_criterio'])->on('criterios_rubrica')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_docente_criterios');
    }
};
