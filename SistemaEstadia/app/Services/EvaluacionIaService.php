<?php

namespace App\Services;

use App\Models\CorpusDocumento;
use App\Models\EvaluacionIa;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

// Guarda en la base de datos el resultado validado de Gemini y mantiene al día la comparación con el docente.
class EvaluacionIaService
{
    // Subir el PDF y esperar a Gemini (con reintentos por HTTP 503) puede superar el límite habitual de PHP.
    public const LIMITE_SEGUNDOS = 300;

    public function __construct(
        private readonly ComparativaService $comparativas,
        private readonly GeminiEvaluationService $gemini,
    ) {}

    /**
     * Evalúa un documento sanitizado con Gemini y guarda el resultado. Lanza excepciones con
     * mensajes aptos para el usuario (ver mensajeDeError) para que el llamador decida cómo informarlas.
     */
    public function evaluarDocumento(CorpusDocumento $documento): EvaluacionIa
    {
        $documento->loadMissing('evidencia');
        $evidencia = $documento->evidencia;

        if (! filled(config('services.gemini.api_key'))) {
            throw new RuntimeException('Configura GEMINI_API_KEY en el archivo .env antes de evaluar con IA.');
        }
        if (! $documento->estaSanitizado() || ! $documento->ruta_archivo_sanitizado || ! Storage::exists($documento->ruta_archivo_sanitizado)) {
            throw new RuntimeException('El PDF debe estar sanitizado y disponible antes de evaluarlo.');
        }
        $criterios = $evidencia->criteriosRubrica()->where('evaluable_ia', true)->orderBy('id_criterio')->get();
        if ($criterios->isEmpty()) {
            throw new RuntimeException('La evidencia no tiene una rúbrica con criterios evaluables desde el producto.');
        }

        // Evita enviar dos veces el mismo documento a Gemini (por ejemplo, evaluación individual y lote a la vez).
        $candado = Cache::lock('evaluacion-ia-'.$documento->id_corpus, self::LIMITE_SEGUNDOS + 60);
        if (! $candado->get()) {
            throw new RuntimeException('Este documento ya se está evaluando.');
        }

        try {
            set_time_limit(self::LIMITE_SEGUNDOS);
            $inicio = microtime(true);
            $resultado = $this->gemini->evaluar(Storage::path($documento->ruta_archivo_sanitizado), $evidencia->titulo, $criterios->all());

            return $this->guardar($documento, $resultado, round(microtime(true) - $inicio, 2));
        } finally {
            $candado->release();
        }
    }

    /** Texto para el usuario a partir del error ocurrido al evaluar con Gemini. */
    public function mensajeDeError(Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $estado = $e->response->status();

            return match (true) {
                in_array($estado, [400, 401, 403], true) => "Gemini rechazó la solicitud (HTTP {$estado}). Revisa GEMINI_API_KEY y GEMINI_MODEL en .env.",
                $estado === 429 => 'Gemini alcanzó su límite de uso (HTTP 429). Espera unos minutos e inténtalo de nuevo.',
                $estado === 503 => 'Gemini está saturado (HTTP 503) y no respondió tras 5 reintentos (1, 2, 4, 8 y 16 s). Inténtalo de nuevo en unos minutos.',
                default => "Gemini respondió con un error (HTTP {$estado}). Inténtalo de nuevo en unos minutos.",
            };
        }
        if ($e instanceof ConnectionException) {
            return 'No se pudo conectar con Gemini. Revisa tu conexión a Internet e inténtalo de nuevo.';
        }
        if ($e instanceof QueryException) {
            return 'La IA evaluó el documento, pero no se pudo guardar el resultado en la base de datos. Inténtalo de nuevo.';
        }
        // Los RuntimeException del servicio ya traen un mensaje pensado para el usuario.
        if ($e instanceof RuntimeException) {
            return $e->getMessage();
        }

        return 'Ocurrió un error inesperado al evaluar el documento. Revisa el registro de la aplicación.';
    }

    /**
     * @param  array{calificacion_global: float, retroalimentacion_cualitativa: string, criterios: array<int, array{id_criterio: int, calificacion: float, retroalimentacion: string}>}  $resultado
     */
    public function guardar(CorpusDocumento $documento, array $resultado, float $duracionSegundos): EvaluacionIa
    {
        $evaluacion = DB::transaction(function () use ($documento, $resultado, $duracionSegundos) {
            // La tabla permite una sola evaluación de IA por documento: una reevaluación reemplaza la anterior.
            $evaluacion = EvaluacionIa::updateOrCreate(
                ['id_corpus' => $documento->id_corpus],
                [
                    'modelo_llm' => config('services.gemini.model'),
                    'calificacion_global' => $resultado['calificacion_global'],
                    'retroalimentacion_cualitativa' => $resultado['retroalimentacion_cualitativa'],
                    'tiempo_procesamiento_segundos' => $duracionSegundos,
                    'fecha_procesamiento' => now(),
                ]
            );
            $evaluacion->criterios()->delete();

            foreach ($resultado['criterios'] as $criterio) {
                $evaluacion->criterios()->create([
                    ...$criterio,
                    'id_evidencia' => $documento->id_evidencia,
                ]);
            }

            return $evaluacion;
        });

        $this->comparativas->actualizar($documento);

        return $evaluacion;
    }
}
