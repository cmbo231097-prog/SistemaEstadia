<?php

namespace App\Services\Pdf;

use FPDF;

// Documento PDF con encabezado institucional y pie con numeración; base de los reportes del sistema.
class ReportePdf extends FPDF
{
    public string $tituloReporte = '';

    public string $subtituloReporte = '';

    /** Convierte UTF-8 a la codificación que usan las fuentes estándar de FPDF (Latin-1). */
    public static function t(string $texto): string
    {
        $texto = strtr($texto, [
            '—' => '-', '–' => '-', '…' => '...', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            '≈' => '~', 'Δ' => 'Dif.', '✓' => '', '⚠' => '', '≥' => '>=', '≤' => '<=',
        ]);

        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    /** Recorta el texto con puntos suspensivos para que quepa en el ancho indicado (mm). Devuelve Latin-1. */
    public function ajustar(string $texto, float $ancho): string
    {
        $texto = self::t($texto);
        if ($this->GetStringWidth($texto) <= $ancho) {
            return $texto;
        }
        while ($texto !== '' && $this->GetStringWidth($texto.'...') > $ancho) {
            $texto = substr($texto, 0, -1);
        }

        return rtrim($texto).'...';
    }

    /**
     * Divide el texto en hasta $maxLineas líneas que caben en $ancho (mm), cortando por palabras.
     * Si sobra texto, la última línea termina en puntos suspensivos. Devuelve Latin-1.
     *
     * @return array<int, string>
     */
    public function lineas(string $texto, float $ancho, int $maxLineas = 2): array
    {
        $todas = [];
        $actual = '';
        foreach (preg_split('/\s+/', trim($texto)) ?: [] as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;
            if ($actual === '' || $this->GetStringWidth(self::t($prueba)) <= $ancho) {
                $actual = $prueba;

                continue;
            }
            $todas[] = $actual;
            $actual = $palabra;
        }
        if ($actual !== '') {
            $todas[] = $actual;
        }

        $salida = [];
        foreach (array_slice($todas, 0, $maxLineas - 1) as $linea) {
            $salida[] = $this->ajustar($linea, $ancho);
        }
        if (count($todas) >= $maxLineas) {
            $salida[] = $this->ajustar(implode(' ', array_slice($todas, $maxLineas - 1)), $ancho);
        }

        return $salida;
    }

    public function Header(): void
    {
        $this->SetFillColor(30, 58, 110);
        $this->Rect(0, 0, $this->GetPageWidth(), 17, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetXY(12, 3.5);
        $this->Cell(0, 6, self::t($this->tituloReporte));
        $this->SetFont('Helvetica', '', 8);
        $this->SetXY(12, 10.5);
        $this->Cell(0, 4, self::t($this->subtituloReporte));
        $this->SetTextColor(0, 0, 0);
        $this->SetY(23);
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, self::t('Página '.$this->PageNo().' de {nb}'), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }
}
