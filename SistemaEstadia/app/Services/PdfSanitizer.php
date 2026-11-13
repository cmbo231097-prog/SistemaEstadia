<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Elimina la primera página (carátula institucional) de un PDF para anonimizarlo.
 * El trabajo con el PDF lo hace scripts/pdf_sanitizer.py (Python + pypdf); aquí solo se invoca.
 */
class PdfSanitizer
{
    /** @return int Páginas del PDF resultante. */
    public function sanitizar(string $origen, string $destino): int
    {
        return (int) ($this->ejecutar(['sanitizar', $origen, $destino])['paginas'] ?? 0);
    }

    public function contarPaginas(string $ruta): ?int
    {
        return $this->contarPaginasLote([$ruta])[$ruta] ?? null;
    }

    /**
     * Cuenta las páginas de varios PDFs con un solo proceso de Python.
     *
     * @param  array<int, string>  $rutas
     * @return array<string, int|null> Ruta => páginas (null si el archivo no se pudo leer o no existe).
     */
    public function contarPaginasLote(array $rutas): array
    {
        $rutas = array_values(array_unique($rutas));
        if ($rutas === []) {
            return [];
        }

        try {
            $paginas = $this->ejecutar(['contar', ...$rutas])['paginas'] ?? [];
        } catch (RuntimeException $e) {
            report($e);

            return array_fill_keys($rutas, null);
        }

        return array_map(fn ($valor) => is_int($valor) ? $valor : null, $paginas) + array_fill_keys($rutas, null);
    }

    /**
     * @param  array<int, string>  $argumentos
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function ejecutar(array $argumentos): array
    {
        $proceso = new Process([
            config('services.python.binary'),
            config('services.python.sanitizer_script'),
            ...$argumentos,
        ]);
        $proceso->setTimeout((float) config('services.python.timeout'));

        try {
            $proceso->run();
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo ejecutar Python para procesar el PDF: '.$e->getMessage(), 0, $e);
        }

        $respuesta = json_decode($proceso->getOutput(), true);
        if (! is_array($respuesta)) {
            // Sin JSON suele indicar que falta Python o el módulo pypdf (ver scripts/requirements.txt).
            $detalle = trim($proceso->getErrorOutput());
            throw new RuntimeException('Python no devolvió un resultado válido: '.($detalle !== '' ? $detalle : 'sin detalle'));
        }
        if (! $proceso->isSuccessful() || isset($respuesta['error'])) {
            throw new RuntimeException((string) ($respuesta['error'] ?? 'No se pudo procesar el PDF.'));
        }

        return $respuesta;
    }
}
