<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El esquema MySQL institucional ya contiene estas tablas; solo se crean en instalaciones nuevas o pruebas.
    public function up(): void
    {
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
        $entero = static fn (Blueprint $table, string $nombre, bool $sinSigno) => $sinSigno
            ? $table->unsignedInteger($nombre)
            : $table->integer($nombre);

        if (! Schema::hasTable('evaluaciones_comparativas')) {
            Schema::create('evaluaciones_comparativas', function (Blueprint $table) use ($sinSigno, $entero) {
                $table->increments('id_comparativa');
                $entero($table, 'id_corpus', $sinSigno('corpus_documentos', 'id_corpus'));
                $entero($table, 'id_eval_docente', $sinSigno('evaluaciones_docente', 'id_eval_docente'));
                $entero($table, 'id_eval_ia', $sinSigno('evaluaciones_ia', 'id_eval_ia'));
                $table->decimal('discrepancia_absoluta', 5, 2);
                $table->decimal('discrepancia_porcentual', 5, 2);
                $table->decimal('varianza', 6, 4);
                $table->decimal('diferencia_tiempos_minutos', 6, 2);
                $table->boolean('alerta_discrepancia')->nullable()->default(false);
                $table->timestamp('fecha_calculo')->nullable()->useCurrent();

                // Una comparación por documento.
                $table->unique('id_corpus');
                $table->foreign('id_corpus')->references('id_corpus')->on('corpus_documentos')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('id_eval_docente')->references('id_eval_docente')->on('evaluaciones_docente')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('id_eval_ia')->references('id_eval_ia')->on('evaluaciones_ia')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('comparativa_criterios_detalle')) {
            Schema::create('comparativa_criterios_detalle', function (Blueprint $table) use ($sinSigno, $entero) {
                $entero($table, 'id_comparativa', $sinSigno('evaluaciones_comparativas', 'id_comparativa'));
                $entero($table, 'id_evidencia', $sinSigno('criterios_rubrica', 'id_evidencia'));
                $entero($table, 'id_criterio', $sinSigno('criterios_rubrica', 'id_criterio'));
                $table->decimal('calificacion_docente', 5, 2);
                $table->decimal('calificacion_ia', 5, 2);
                $table->decimal('discrepancia_criterio', 5, 2);
                $table->text('comentarios_criterio')->nullable();

                $table->primary(['id_comparativa', 'id_evidencia', 'id_criterio']);
                $table->foreign('id_comparativa')->references('id_comparativa')->on('evaluaciones_comparativas')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign(['id_evidencia', 'id_criterio'])->references(['id_evidencia', 'id_criterio'])->on('criterios_rubrica')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Las tablas pertenecen al esquema institucional; no se eliminan.
    }
};
