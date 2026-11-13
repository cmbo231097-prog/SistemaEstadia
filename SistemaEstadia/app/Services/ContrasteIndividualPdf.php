<?php

namespace App\Services;

use App\Services\Pdf\ReportePdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

// Genera el PDF del Reporte de Contraste Evaluativo Individual (barras horizontales: profesor vs. IA por rubro).
class ContrasteIndividualPdf
{
    private const IZQ = 14.0;

    private const ANCHO = 187.9;

    private const ETIQUETA = 60.0;     // ancho de la columna con el nombre del rubro

    private const BARRAS = 92.0;       // ancho del área de barras (100 % del rubro)

    private const ALTO_FILA = 11.6;

    private const NAVY = [30, 58, 110];

    private const VERDE = [63, 154, 107];

    private const GRIS = [100, 116, 139];

    /**
     * @param  array<string, mixed>  $reporte  Resultado de ContrasteIndividualService::generar().
     * @param  array{estudiante: string, matricula: string, asignatura: string, grupo: string, equipo: string, profesor: string, evidencia: string, generado_en: CarbonInterface}  $contexto
     */
    public function generar(array $reporte, array $contexto): string
    {
        $pdf = new ReportePdf('P', 'mm', 'Letter');
        $pdf->tituloReporte = 'Reporte de Contraste Evaluativo Individual';
        $pdf->subtituloReporte = 'UPEMOR | Plataforma de Evaluación y Sanitización de Corpus Docente con IA';
        $pdf->SetMargins(self::IZQ, 20, self::IZQ);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AliasNbPages();
        $pdf->SetTitle(ReportePdf::t('Reporte de Contraste Evaluativo Individual'));
        $pdf->AddPage();

        $this->datos($pdf, $contexto);
        $this->indicadores($pdf, $reporte['resumen']);
        $this->grafica($pdf, $reporte);
        $this->retroalimentacion($pdf, $reporte);

        return $pdf->Output('S');
    }

    private function datos(ReportePdf $pdf, array $c): void
    {
        $filas = [
            ['Estudiante', "{$c['estudiante']} ({$c['matricula']})"],
            ['Asignatura', $c['asignatura']],
            ['Grupo / Equipo', "{$c['grupo']} / {$c['equipo']}"],
            ['Profesor', $c['profesor']],
            ['Evidencia', $c['evidencia']],
        ];
        $pdf->SetTextColor(15, 23, 42);
        foreach ($filas as [$etiqueta, $valor]) {
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $pdf->Cell(32, 5.4, ReportePdf::t($etiqueta.':'));
            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->Cell(0, 5.4, $pdf->ajustar($valor, self::ANCHO - 32), 0, 1);
        }
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...self::GRIS);
        $pdf->Cell(0, 5, ReportePdf::t('Generado el '.$c['generado_en']->format('d/m/Y H:i')), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
    }

    private function indicadores(ReportePdf $pdf, array $r): void
    {
        $ia = $this->num($r['ia_maximo']);
        $diferencia = $r['diferencia'] === null ? '-' : ($r['diferencia'] > 0 ? '+' : '').$this->num($r['diferencia']).' pts';
        $cajas = [
            ['Calificación del profesor', $this->num($r['docente_total']), 'sobre 100'],
            ['Calificación de Gemini', $r['ia_total'] === null ? '-' : $this->num($r['ia_total']), "sobre {$ia} (rubros que evalúa la IA)"],
            ['Profesor en los rubros de la IA', $r['docente_en_rubros_ia'] === null ? '-' : $this->num($r['docente_en_rubros_ia']), "sobre {$ia}"],
            ['Diferencia IA - profesor', $diferencia, $r['diferencia_pct'] === null ? 'en los mismos rubros' : $this->num($r['diferencia_pct']).' % frente al profesor'],
        ];
        $gap = 4.0;
        $ancho = (self::ANCHO - $gap * (count($cajas) - 1)) / count($cajas);
        $y = $pdf->GetY();

        foreach ($cajas as $i => [$etiqueta, $valor, $nota]) {
            $x = self::IZQ + $i * ($ancho + $gap);
            $pdf->SetDrawColor(217, 225, 236);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->Rect($x, $y, $ancho, 20, 'DF');
            $pdf->SetXY($x + 2.5, $y + 2);
            $pdf->SetFont('Helvetica', '', 7.2);
            $pdf->SetTextColor(...self::GRIS);
            $pdf->Cell($ancho - 5, 3.5, $pdf->ajustar($etiqueta, $ancho - 5));
            $pdf->SetXY($x + 2.5, $y + 7);
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell($ancho - 5, 6, ReportePdf::t($valor));
            $pdf->SetXY($x + 2.5, $y + 14.5);
            $pdf->SetFont('Helvetica', '', 6.8);
            $pdf->SetTextColor(...self::GRIS);
            $pdf->Cell($ancho - 5, 3.5, $pdf->ajustar($nota, $ancho - 5));
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($y + 20 + 6);
    }

    private function grafica(ReportePdf $pdf, array $reporte): void
    {
        /** @var Collection<int, array<string, mixed>> $rubros */
        $rubros = $reporte['rubros'];
        $r = $reporte['resumen'];

        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 6, ReportePdf::t('Contraste por rubro: profesor vs. Gemini'), 0, 1);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...self::GRIS);
        $pdf->Cell(0, 4.5, ReportePdf::t('La longitud de cada barra es el porcentaje de la calificación máxima de cada rubro.'), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $this->leyenda($pdf);

        // Primera fila: total en los rubros que evalúa la IA, para ver la diferencia global de un vistazo.
        $filas = collect();
        if ($r['docente_en_rubros_ia'] !== null && $r['ia_maximo'] > 0) {
            $filas->push([
                'nombre' => 'TOTAL (rubros que evalúa la IA)',
                'docente_pct' => $r['docente_en_rubros_ia'] / $r['ia_maximo'] * 100,
                'ia_pct' => $r['ia_total'] / $r['ia_maximo'] * 100,
                'docente_txt' => $this->num($r['docente_en_rubros_ia']).' / '.$this->num($r['ia_maximo']),
                'ia_txt' => $this->num($r['ia_total']).' / '.$this->num($r['ia_maximo']),
                'total' => true,
            ]);
        }
        foreach ($rubros as $rubro) {
            $filas->push([
                'nombre' => $rubro['nombre'],
                'docente_pct' => $rubro['docente_pct'],
                'ia_pct' => $rubro['ia_pct'],
                'docente_txt' => $rubro['docente'] === null ? '-' : $this->num($rubro['docente']).' / '.$this->num($rubro['maximo']),
                'ia_txt' => $rubro['ia'] === null ? ($rubro['ia_evalua'] ? '-' : 'no la evalúa la IA') : $this->num($rubro['ia']).' / '.$this->num($rubro['maximo']),
                'total' => false,
            ]);
        }

        $this->ejes($pdf);
        foreach ($filas as $fila) {
            if ($pdf->GetY() + self::ALTO_FILA > $pdf->GetPageHeight() - 14) {
                $pdf->AddPage();
                $this->ejes($pdf);
            }
            $this->fila($pdf, $fila);
        }
        $pdf->Ln(5);
    }

    private function leyenda(ReportePdf $pdf): void
    {
        $y = $pdf->GetY() + 0.5;
        $x = self::IZQ;
        $pdf->SetFont('Helvetica', '', 8);
        foreach ([[self::NAVY, 'Profesor'], [self::VERDE, 'Gemini (IA)']] as [$color, $texto]) {
            $pdf->SetFillColor(...$color);
            $pdf->Rect($x, $y + 0.9, 3.5, 3.5, 'F');
            $pdf->SetXY($x + 5, $y);
            $pdf->Cell(24, 5.2, ReportePdf::t($texto));
            $x += 32;
        }
        $pdf->SetY($y + 6);
    }

    // Encabezado del eje horizontal (0 a 100 %) que se repite en cada hoja.
    private function ejes(ReportePdf $pdf): void
    {
        $x0 = self::IZQ + self::ETIQUETA;
        $pdf->SetFont('Helvetica', '', 6.8);
        $pdf->SetTextColor(...self::GRIS);
        $y = $pdf->GetY();
        foreach ([0, 25, 50, 75, 100] as $marca) {
            $pdf->SetXY($x0 + self::BARRAS * $marca / 100 - 6, $y);
            $pdf->Cell(12, 3.5, $marca.' %', 0, 0, 'C');
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($y + 4.2);
    }

    private function fila(ReportePdf $pdf, array $f): void
    {
        $y = $pdf->GetY();
        $x0 = self::IZQ + self::ETIQUETA;
        $anchoTexto = self::ANCHO - self::ETIQUETA - self::BARRAS - 2;

        // Cuadrícula vertical.
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.2);
        foreach ([0, 25, 50, 75, 100] as $marca) {
            $pdf->Line($x0 + self::BARRAS * $marca / 100, $y, $x0 + self::BARRAS * $marca / 100, $y + self::ALTO_FILA);
        }
        $pdf->SetDrawColor(241, 245, 249);
        $pdf->Line(self::IZQ, $y + self::ALTO_FILA, self::IZQ + self::ANCHO, $y + self::ALTO_FILA);

        // Nombre del rubro (hasta 2 líneas).
        $pdf->SetFont('Helvetica', $f['total'] ? 'B' : '', 7.8);
        $pdf->SetTextColor(15, 23, 42);
        foreach ($pdf->lineas($f['nombre'], self::ETIQUETA - 3, 2) as $n => $linea) {
            $pdf->SetXY(self::IZQ, $y + 1.8 + $n * 3.6);
            $pdf->Cell(self::ETIQUETA - 2, 3.6, $linea);
        }

        // Barras y cifras: profesor arriba, IA abajo.
        foreach ([[self::NAVY, $f['docente_pct'], $f['docente_txt'], $y + 1.3], [self::VERDE, $f['ia_pct'], $f['ia_txt'], $y + 5.9]] as [$color, $pct, $texto, $yBarra]) {
            if ($pct !== null) {
                $pdf->SetFillColor(...$color);
                $pdf->Rect($x0, $yBarra, max(0.6, self::BARRAS * min($pct, 100) / 100), 3.9, 'F');
            }
            $pdf->SetFont('Helvetica', $pct === null ? 'I' : '', 7.4);
            $pdf->SetTextColor(...($pct === null ? self::GRIS : [51, 65, 85]));
            $pdf->SetXY($x0 + self::BARRAS + 2, $yBarra - 0.1);
            $pdf->Cell($anchoTexto, 4.1, $pdf->ajustar($texto, $anchoTexto - 1));
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($y + self::ALTO_FILA);
    }

    private function retroalimentacion(ReportePdf $pdf, array $reporte): void
    {
        $bloques = collect($reporte['rubros'])->filter(fn ($r) => $r['retro_docente'] || $r['retro_ia']);
        $docente = $reporte['docente']['comentarios'] ?? null;
        $ia = $reporte['ia']['comentarios'] ?? null;
        if ($bloques->isEmpty() && ! filled($docente) && ! filled($ia)) {
            return;
        }

        if ($pdf->GetY() + 30 > $pdf->GetPageHeight() - 14) {
            $pdf->AddPage();
        }
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 6.5, ReportePdf::t('Retroalimentación'), 0, 1);

        foreach ([['Comentarios generales del profesor', $docente, self::NAVY], ['Retroalimentación general de Gemini', $ia, self::VERDE]] as [$titulo, $texto, $color]) {
            if (filled($texto)) {
                $this->comentario($pdf, $titulo, $texto, $color, true);
            }
        }
        foreach ($bloques as $rubro) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Ln(1);
            $pdf->Cell(0, 5.4, $pdf->ajustar($rubro['nombre'], self::ANCHO), 0, 1);
            if ($rubro['retro_docente']) {
                $this->comentario($pdf, 'Profesor', $rubro['retro_docente'], self::NAVY, false);
            }
            if ($rubro['retro_ia']) {
                $this->comentario($pdf, 'Gemini', $rubro['retro_ia'], self::VERDE, false);
            }
        }
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'I', 7.5);
        $pdf->SetTextColor(...self::GRIS);
        $pdf->MultiCell(self::ANCHO, 3.8, ReportePdf::t('La IA solo califica los rubros evaluables desde el producto (el contenido del documento); los demás, como el desempeño del equipo, los califica únicamente el profesor.'));
        $pdf->SetTextColor(0, 0, 0);
    }

    private function comentario(ReportePdf $pdf, string $titulo, string $texto, array $color, bool $general): void
    {
        $sangria = $general ? 0 : 4;
        if ($pdf->GetY() + 14 > $pdf->GetPageHeight() - 14) {
            $pdf->AddPage();
        }
        $pdf->SetX(self::IZQ + $sangria);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$color);
        $pdf->Cell(0, 4.8, ReportePdf::t($titulo.($general ? '' : ':')), 0, 1);
        $pdf->SetX(self::IZQ + $sangria + 3);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->MultiCell(self::ANCHO - $sangria - 3, 4.4, ReportePdf::t($texto));
        $pdf->SetTextColor(0, 0, 0);
    }

    private function num(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.') ?: '0';
    }
}
