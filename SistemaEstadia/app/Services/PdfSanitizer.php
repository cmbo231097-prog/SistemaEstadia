<?php

namespace App\Services;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Throwable;

/** Elimina la primera página (carátula institucional) de un PDF para anonimizarlo. */
class PdfSanitizer
{
    /** @return int Páginas del PDF resultante. */
    public function sanitizar(string $origen, string $destino): int
    {
        try {
            return $this->recortar($origen, $destino);
        } catch (Throwable $e) {
            // PDFs con referencias comprimidas (1.5+): se normalizan con Ghostscript si está instalado.
            $normalizado = $this->normalizarConGhostscript($origen);
            if ($normalizado === null) {
                throw $e;
            }
            try {
                return $this->recortar($normalizado, $destino);
            } finally {
                @unlink($normalizado);
            }
        }
    }

    public function contarPaginas(string $ruta): ?int
    {
        try {
            return (new Fpdi())->setSourceFile($ruta);
        } catch (Throwable) {
            $normalizado = $this->normalizarConGhostscript($ruta);
            if ($normalizado === null) {
                return null;
            }
            try {
                return (new Fpdi())->setSourceFile($normalizado);
            } catch (Throwable) {
                return null;
            } finally {
                @unlink($normalizado);
            }
        }
    }

    private function recortar(string $origen, string $destino): int
    {
        $pdf = new Fpdi();
        $total = $pdf->setSourceFile($origen);

        if ($total < 2) {
            throw new RuntimeException('El PDF solo tiene una página; al suprimir la portada no quedaría contenido.');
        }

        for ($pagina = 2; $pagina <= $total; $pagina++) {
            $plantilla = $pdf->importPage($pagina);
            $tamano = $pdf->getTemplateSize($plantilla);
            $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
            $pdf->useTemplate($plantilla);
        }

        $pdf->Output('F', $destino);

        return $total - 1;
    }

    private function normalizarConGhostscript(string $origen): ?string
    {
        $salida = tempnam(sys_get_temp_dir(), 'corpus_').'.pdf';
        $proceso = new Process([
            'gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4', '-sOutputFile='.$salida, $origen,
        ]);
        $proceso->setTimeout(60);

        try {
            $proceso->run();
        } catch (Throwable) {
            @unlink($salida);

            return null;
        }

        if (! $proceso->isSuccessful() || ! is_file($salida) || filesize($salida) === 0) {
            @unlink($salida);

            return null;
        }

        return $salida;
    }
}
