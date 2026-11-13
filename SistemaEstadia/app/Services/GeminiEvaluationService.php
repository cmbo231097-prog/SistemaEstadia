<?php

namespace App\Services;

use App\Models\CriterioRubrica;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class GeminiEvaluationService
{
    /** Espera (ms) antes de cada reintento cuando Gemini responde HTTP 503: 1, 2, 4, 8 y 16 s. */
    public const ESPERAS_503_MS = [1000, 2000, 4000, 8000, 16000];

    /**
     * Evalúa el PDF ya anonimizado según los criterios y devuelve datos validados.
     *
     * @param  array<int, CriterioRubrica>  $criterios
     * @return array{calificacion_global: float, retroalimentacion_cualitativa: string, criterios: array<int, array{id_criterio: int, calificacion: float, retroalimentacion: string}>}
     */
    public function evaluar(string $rutaPdf, string $titulo, array $criterios): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $modelo = (string) config('services.gemini.model');

        if ($apiKey === '' || $modelo === '') {
            throw new RuntimeException('Configura GEMINI_API_KEY y GEMINI_MODEL antes de solicitar una evaluación.');
        }

        if (! is_file($rutaPdf) || ! is_readable($rutaPdf)) {
            throw new RuntimeException('El PDF sanitizado no está disponible para evaluación.');
        }

        $archivo = $this->subirPdf($rutaPdf);

        try {
            $archivo = $this->esperarArchivoDisponible($archivo);
            $respuesta = $this->solicitarEvaluacion($archivo, $titulo, $criterios, $modelo);

            return $this->validarRespuesta($respuesta, $criterios);
        } finally {
            $this->eliminarArchivoRemoto($archivo['name']);
        }
    }

    /**
     * @return array{name: string, uri: string, state?: string}
     */
    private function subirPdf(string $rutaPdf): array
    {
        $tamano = filesize($rutaPdf);
        if ($tamano === false) {
            throw new RuntimeException('No fue posible leer el tamaño del PDF sanitizado.');
        }

        $inicio = $this->cliente(30)->withHeaders([
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command' => 'start',
            'X-Goog-Upload-Header-Content-Length' => (string) $tamano,
            'X-Goog-Upload-Header-Content-Type' => 'application/pdf',
        ])->post($this->baseUrl().'/upload/v1beta/files', [
            'file' => ['display_name' => 'corpus-anonimizado.pdf'],
        ]);
        $inicio->throw();

        $urlCarga = $inicio->header('X-Goog-Upload-URL');
        if (! is_string($urlCarga) || $urlCarga === '') {
            throw new RuntimeException('Gemini no devolvió una URL para cargar el PDF.');
        }

        $contenido = file_get_contents($rutaPdf);
        if ($contenido === false) {
            throw new RuntimeException('No fue posible leer el PDF sanitizado.');
        }

        $carga = $this->cliente(120)->withHeaders([
            'X-Goog-Upload-Offset' => '0',
            'X-Goog-Upload-Command' => 'upload, finalize',
            'Content-Length' => (string) $tamano,
        ])->withBody($contenido, 'application/pdf')->post($urlCarga);
        $carga->throw();

        $archivo = $carga->json('file');
        if (! is_array($archivo) || empty($archivo['name']) || empty($archivo['uri'])) {
            throw new RuntimeException('Gemini no confirmó el archivo PDF cargado.');
        }

        return [
            'name' => $archivo['name'],
            'uri' => $archivo['uri'],
            'state' => $archivo['state'] ?? null,
        ];
    }

    /**
     * La API puede tardar en preparar PDFs grandes; espera a que el archivo quede activo.
     *
     * @param  array<string, mixed>  $archivo
     * @return array<string, mixed>
     */
    private function esperarArchivoDisponible(array $archivo): array
    {
        $nombre = (string) $archivo['name'];
        $limite = microtime(true) + 45;
        while (($archivo['state'] ?? null) === 'PROCESSING' && microtime(true) < $limite) {
            sleep(1);
            $consulta = $this->cliente(10)->get($this->baseUrl().'/v1beta/'.ltrim($nombre, '/'));
            $consulta->throw();
            $archivo = $consulta->json('file') ?? $consulta->json() ?? [];
        }

        if (($archivo['state'] ?? null) !== 'ACTIVE' || empty($archivo['uri'])) {
            throw new RuntimeException('Gemini no pudo preparar el PDF para evaluarlo.');
        }

        return $archivo;
    }

    /**
     * @param  array{name: string, uri: string}  $archivo
     * @param  array<int, CriterioRubrica>  $criterios
     */
    private function solicitarEvaluacion(
        array $archivo,
        string $titulo,
        array $criterios,
        string $modelo
    ): array {
        $listaCriterios = array_map(fn (CriterioRubrica $criterio) => [
            'id_criterio' => (int) $criterio->id_criterio,
            'nombre' => $criterio->nombre_criterio,
            'descripcion' => $criterio->descripcion_criterio,
            'puntaje_maximo' => (float) $criterio->puntaje_maximo,
        ], $criterios);

        $prompt = implode("\n", [
            'Actúa como asistente de evaluación académica. Evalúa únicamente el contenido del PDF anonimizado según la actividad y la rúbrica.',
            'No inventes contenido. Si no hay evidencia suficiente para un criterio, asigna cero o el puntaje que la evidencia permita y explica la limitación.',
            'No infieras identidad, género, edad u otros datos personales. No reveles ni intentes reconstruir identidades.',
            'Los puntajes de la rúbrica están en una escala de 100 puntos para toda la evidencia; no conviertas la escala.',
            'La calificación global debe corresponder a la suma de los puntajes por criterio. Respeta el máximo de cada criterio.',
            'La retroalimentación debe ser breve, concreta y mencionar errores notorios o mejoras accionables.',
            'Título de la actividad: '.$titulo,
            'Rúbrica JSON: '.json_encode($listaCriterios, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        $response = $this->cliente(150)->post(
            $this->baseUrl().'/v1beta/models/'.rawurlencode($modelo).':generateContent',
            [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $archivo['uri']]],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->esquemaRespuesta(),
                    'temperature' => 0.1,
                ],
            ]
        );
        $response->throw();

        $texto = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($texto) || trim($texto) === '') {
            throw new RuntimeException('Gemini devolvió una respuesta vacía o bloqueada.');
        }

        try {
            $resultado = json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Gemini devolvió una evaluación con formato JSON inválido.', previous: $e);
        }

        if (! is_array($resultado)) {
            throw new RuntimeException('Gemini no devolvió el objeto de evaluación esperado.');
        }

        return $resultado;
    }

    /**
     * Revalida el contenido generado para no persistir criterios omitidos, duplicados o fuera de escala.
     *
     * @param  array<string, mixed>  $respuesta
     * @param  array<int, CriterioRubrica>  $criterios
     * @return array{calificacion_global: float, retroalimentacion_cualitativa: string, criterios: array<int, array{id_criterio: int, calificacion: float, retroalimentacion: string}>}
     */
    private function validarRespuesta(array $respuesta, array $criterios): array
    {
        $comentario = $respuesta['retroalimentacion_cualitativa'] ?? null;
        $puntajes = $respuesta['criterios'] ?? null;

        if (! is_string($comentario) || trim($comentario) === '' || ! is_array($puntajes)) {
            throw new RuntimeException('La evaluación de Gemini no contiene retroalimentación y puntajes válidos.');
        }

        $rubrica = [];
        foreach ($criterios as $criterio) {
            $rubrica[(int) $criterio->id_criterio] = (float) $criterio->puntaje_maximo;
        }

        $vistos = [];
        $puntajeGlobal = 0.0;
        $detalle = [];
        foreach ($puntajes as $puntaje) {
            if (! is_array($puntaje) || ! isset($puntaje['id_criterio'], $puntaje['calificacion'])) {
                throw new RuntimeException('Gemini devolvió un puntaje de criterio incompleto.');
            }

            $idCriterio = filter_var($puntaje['id_criterio'], FILTER_VALIDATE_INT);
            $calificacion = filter_var($puntaje['calificacion'], FILTER_VALIDATE_FLOAT);
            if ($idCriterio === false || $calificacion === false
                || ! array_key_exists($idCriterio, $rubrica)
                || isset($vistos[$idCriterio])
                || $calificacion < 0
                || $calificacion > $rubrica[$idCriterio]) {
                throw new RuntimeException('Gemini devolvió un puntaje fuera de la rúbrica definida.');
            }

            $retroalimentacion = $puntaje['retroalimentacion'] ?? '';
            if (! is_string($retroalimentacion)) {
                throw new RuntimeException('La retroalimentación por criterio no tiene un formato válido.');
            }

            $vistos[$idCriterio] = true;
            $puntajeGlobal += $calificacion;
            $detalle[] = [
                'id_criterio' => $idCriterio,
                'calificacion' => round($calificacion, 2),
                'retroalimentacion' => trim($retroalimentacion),
            ];
        }

        if (count($vistos) !== count($rubrica)) {
            throw new RuntimeException('Gemini no evaluó todos los criterios de la rúbrica.');
        }

        return [
            'calificacion_global' => round($puntajeGlobal, 2),
            'retroalimentacion_cualitativa' => trim($comentario),
            'criterios' => $detalle,
        ];
    }

    private function esquemaRespuesta(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'retroalimentacion_cualitativa' => ['type' => 'STRING'],
                'criterios' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'id_criterio' => ['type' => 'INTEGER'],
                            'calificacion' => ['type' => 'NUMBER'],
                            'retroalimentacion' => ['type' => 'STRING'],
                        ],
                        'required' => ['id_criterio', 'calificacion', 'retroalimentacion'],
                    ],
                ],
            ],
            'required' => ['retroalimentacion_cualitativa', 'criterios'],
        ];
    }

    private function eliminarArchivoRemoto(string $nombre): void
    {
        try {
            $this->cliente(20)->delete($this->baseUrl().'/v1beta/'.ltrim($nombre, '/'))->throw();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function cliente(int $timeout): PendingRequest
    {
        $apiKey = (string) config('services.gemini.api_key');

        $intentos = 0;

        return Http::acceptJson()
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout($timeout)
            ->connectTimeout(15)
            ->retry(self::ESPERAS_503_MS, 0, function (Throwable $e) use (&$intentos): bool {
                // Solo la saturación temporal del servicio (503) se reintenta; cualquier otro error se informa de inmediato.
                $reintentar = $e instanceof RequestException && $e->response->status() === 503;
                if ($reintentar && ++$intentos <= count(self::ESPERAS_503_MS)) {
                    Log::warning('Gemini respondió HTTP 503; reintento '.$intentos.' de '.count(self::ESPERAS_503_MS).' en '.(self::ESPERAS_503_MS[$intentos - 1] / 1000).' s.');
                }

                return $reintentar;
            });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.gemini.base_url'), '/');
    }
}
