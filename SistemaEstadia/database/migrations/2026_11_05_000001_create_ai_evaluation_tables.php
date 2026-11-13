<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $idIsUnsigned = static function (string $tableName, string $columnName): bool {
            if (DB::getDriverName() !== 'mysql') {
                return false;
            }

            $columnType = DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $tableName)
                ->where('COLUMN_NAME', $columnName)
                ->value('COLUMN_TYPE');

            if (! is_string($columnType)) {
                throw new RuntimeException("No se encontró {$tableName}.{$columnName}.");
            }

            return str_contains(strtolower($columnType), 'unsigned');
        };

        if (! Schema::hasTable('criterios_rubrica')) {
            Schema::create('criterios_rubrica', function (Blueprint $table) use ($idIsUnsigned) {
                if ($idIsUnsigned('evidencias', 'id_evidencia')) {
                    $table->unsignedInteger('id_evidencia');
                } else {
                    $table->integer('id_evidencia');
                }
                $table->integer('id_criterio');
                $table->string('nombre_criterio', 100);
                $table->text('descripcion_criterio');
                $table->decimal('puntaje_maximo', 5, 2);

                $table->primary(['id_evidencia', 'id_criterio']);
                $table->foreign('id_evidencia')
                    ->references('id_evidencia')
                    ->on('evidencias')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('evaluaciones_ia')) {
            Schema::create('evaluaciones_ia', function (Blueprint $table) use ($idIsUnsigned) {
                $table->increments('id_eval_ia');
                if ($idIsUnsigned('corpus_documentos', 'id_corpus')) {
                    $table->unsignedInteger('id_corpus');
                } else {
                    $table->integer('id_corpus');
                }
                $table->string('modelo_llm', 80);
                $table->decimal('calificacion_global', 5, 2);
                $table->text('retroalimentacion_cualitativa');
                $table->decimal('tiempo_procesamiento_segundos', 6, 2);
                $table->timestamp('fecha_procesamiento')->useCurrent();
                $table->unique('id_corpus');

                $table->foreign('id_corpus')
                    ->references('id_corpus')
                    ->on('corpus_documentos')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        // El diagrama no incluye una tabla hija para guardar el puntaje de IA por criterio.
        $detalleYaExiste = Schema::hasTable('evaluacion_ia_criterios');
        if (! $detalleYaExiste) {
            Schema::create('evaluacion_ia_criterios', function (Blueprint $table) use ($idIsUnsigned) {
                $table->increments('id_eval_ia_criterio');
                if ($idIsUnsigned('evaluaciones_ia', 'id_eval_ia')) {
                    $table->unsignedInteger('id_eval_ia');
                } else {
                    $table->integer('id_eval_ia');
                }
                if ($idIsUnsigned('evidencias', 'id_evidencia')) {
                    $table->unsignedInteger('id_evidencia');
                } else {
                    $table->integer('id_evidencia');
                }
                if ($idIsUnsigned('criterios_rubrica', 'id_criterio')) {
                    $table->unsignedInteger('id_criterio');
                } else {
                    $table->integer('id_criterio');
                }
                $table->decimal('calificacion', 5, 2);
                $table->text('retroalimentacion')->nullable();

                $table->foreign('id_eval_ia')
                    ->references('id_eval_ia')
                    ->on('evaluaciones_ia')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->foreign(['id_evidencia', 'id_criterio'])
                    ->references(['id_evidencia', 'id_criterio'])
                    ->on('criterios_rubrica')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->unique(['id_eval_ia', 'id_criterio']);
            });
        } elseif (DB::getDriverName() === 'mysql') {
            if (! Schema::hasColumn('evaluacion_ia_criterios', 'id_evidencia')) {
                if (DB::table('evaluacion_ia_criterios')->exists()) {
                    throw new RuntimeException(
                        'No se puede reparar evaluacion_ia_criterios: hay filas que no tienen id_evidencia.'
                    );
                }

                $evidenciaUnsigned = $idIsUnsigned('evidencias', 'id_evidencia');
                $tipoEvidencia = $evidenciaUnsigned ? 'INT UNSIGNED' : 'INT';
                DB::statement(
                    "ALTER TABLE `evaluacion_ia_criterios` ADD COLUMN `id_evidencia` {$tipoEvidencia} NOT NULL"
                );
            }

            foreach ([
                ['id_eval_ia', 'evaluaciones_ia', 'id_eval_ia'],
                ['id_evidencia', 'evidencias', 'id_evidencia'],
                ['id_criterio', 'criterios_rubrica', 'id_criterio'],
            ] as [$columnName, $referencedTable, $referencedColumn]) {
                $unsigned = $idIsUnsigned($referencedTable, $referencedColumn);
                $expectedType = $unsigned ? 'INT UNSIGNED NOT NULL' : 'INT NOT NULL';
                $currentType = DB::table('information_schema.COLUMNS')
                    ->whereRaw('TABLE_SCHEMA = DATABASE()')
                    ->where('TABLE_NAME', 'evaluacion_ia_criterios')
                    ->where('COLUMN_NAME', $columnName)
                    ->value('COLUMN_TYPE');

                if (! is_string($currentType)) {
                    throw new RuntimeException("No se encontró evaluacion_ia_criterios.{$columnName}.");
                }

                $currentIsUnsigned = str_contains(strtolower($currentType), 'unsigned');
                if ($currentIsUnsigned !== $unsigned) {
                    DB::statement(
                        "ALTER TABLE `evaluacion_ia_criterios` MODIFY `{$columnName}` {$expectedType}"
                    );
                }
            }

            $evaluationForeignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'evaluacion_ia_criterios')
                ->where('COLUMN_NAME', 'id_eval_ia')
                ->where('REFERENCED_TABLE_NAME', 'evaluaciones_ia')
                ->where('REFERENCED_COLUMN_NAME', 'id_eval_ia')
                ->exists();

            if (! $evaluationForeignKeyExists) {
                DB::statement(
                    'ALTER TABLE `evaluacion_ia_criterios` '
                    .'ADD CONSTRAINT `evaluacion_ia_criterios_id_eval_ia_foreign` '
                    .'FOREIGN KEY (`id_eval_ia`) REFERENCES `evaluaciones_ia` (`id_eval_ia`) '
                    .'ON DELETE CASCADE ON UPDATE CASCADE'
                );
            }

            $criterionForeignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'evaluacion_ia_criterios')
                ->where('COLUMN_NAME', 'id_evidencia')
                ->where('REFERENCED_TABLE_NAME', 'criterios_rubrica')
                ->where('REFERENCED_COLUMN_NAME', 'id_evidencia')
                ->exists();

            if (! $criterionForeignKeyExists) {
                DB::statement(
                    'ALTER TABLE `evaluacion_ia_criterios` '
                    .'ADD CONSTRAINT `evaluacion_ia_criterios_criterio_foreign` '
                    .'FOREIGN KEY (`id_evidencia`, `id_criterio`) '
                    .'REFERENCES `criterios_rubrica` (`id_evidencia`, `id_criterio`) '
                    .'ON DELETE RESTRICT ON UPDATE CASCADE'
                );
            }
        }
    }

    public function down(): void
    {
        // Las tablas pueden formar parte de un esquema institucional preexistente.
    }
};
