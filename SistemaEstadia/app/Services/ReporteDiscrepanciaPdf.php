<?php

namespace App\Services;

use App\Services\Pdf\ReportePdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

// Genera el PDF del Reporte de Discrepancia Evaluativa (gráfica de barras comparativas docente vs. Gemini).
class ReporteDiscrepanciaPdf
{
    private const IZQ = 12.0;

    private const ANCHO = 255.4;

    // Evidencias por gráfica: más barras en una hoja harían ilegibles las etiquetas.
    private const GRUPOS_POR_GRAFICA = 8;

    private const ALTO_GRAFICA = 82.0;

    private const NAVY = [30, 58, 110];

    private const VERDE = [63, 154, 107];

    private const ROJO = [185, 28, 28];

    /**
     * @param  array{filas: Collection<int, array<string, mixed>>, grupos: Collection<int, array<string, mixed>>, resumen: array<string, float|int>|null}  $reporte
     * @param  array{desde: ?CarbonInterface, hasta: ?CarbonInterface, profesor: ?string, generado_por: string, generado_en: CarbonInterface}  $filtros
     */
    public function generar(array $reporte, array $filtros, float $umbral): string
    {
        $pdf = new ReportePdf('L', 'mm', 'Letter');
        $pdf->tituloReporte = 'Reporte de Discrepancia Evaluativa';
        $pdf->subtituloReporte = 'UPEMOR | Plataforma de Evaluación y Sanitización de Corpus Docente con IA';
        $pdf->SetMargins(self::IZQ, 20, self::IZQ);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AliasNbPages();
        $pdf->SetTitle(ReportePdf::t('Reporte de Discrepancia Evaluativa'));
        $pdf->AddPage();

        $this->datosGenerales($pdf, $filtros);

        if ($reporte['resumen'] === null) {
            $pdf->Ln(6);
            $pdf->SetFont('Helvetica', 'I', 11);
            $pdf->Cell(0, 8, ReportePdf::t('No hay comparaciones entre el docente y Gemini con los filtros seleccionados.'));

            return $pdf->Output('S');
        }

        $this->indicadores($pdf, $reporte['resumen'], $umbral);
        $this->graficas($pdf, $reporte['grupos'], $umbral);
        $this->tablaEvidencias($pdf, $reporte['grupos'], $umbral);
        $this->tablaDocumentos($pdf, $reporte['filas'], $umbral);

        return $pdf->Output('S');
    }

    private function datosGenerales(ReportePdf $pdf, array $filtros): void
    {
        $desde = $filtros['desde']?->format('d/m/Y');
        $hasta = $filtros['hasta']?->format('d/m/Y');
        $rango = match (true) {
            $desde && $hasta => "{$desde} al {$hasta}",
            (bool) $desde => "Desde el {$desde}",
            (bool) $hasta => "Hasta el {$hasta}",
            default => 'Todas las fechas',
        };

        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(34, 5.5, ReportePdf::t('Rango de fechas:'));
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(70, 5.5, ReportePdf::t($rango));
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(18, 5.5, ReportePdf::t('Profesor:'));
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(0, 5.5, $pdf->ajustar($filtros['profesor'] ?? 'Todos los profesores', 130), 0, 1);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 4.5, ReportePdf::t(
            'Generado el '.$filtros['generado_en']->format('d/m/Y H:i').' por '.$filtros['generado_por']
            .'. Las fechas corresponden al registro de la evaluación del docente; las calificaciones se comparan sobre los criterios que evalúa Gemini, en escala de 100.'
        ), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);
    }

    private function indicadores(ReportePdf $pdf, array $r, float $umbral): void
    {
        $cajas = [
            ['Documentos comparados', (string) $r['documentos'], "{$r['evidencias']} evidencia(s) · {$r['profesores']} profesor(es)", false],
            ['Calificación promedio docente', number_format($r['docente_promedio'], 1), 'sobre 100', false],
            ['Calificación promedio Gemini', number_format($r['ia_promedio'], 1), 'sobre 100', false],
            ['Diferencia porcentual promedio', number_format($r['margen_promedio'], 1).' %', 'máxima '.number_format($r['margen_maximo'], 1).' %', false],
            ['Documentos con alerta', (string) $r['alertas'], 'diferencia mayor a '.number_format($umbral, 0).' %', $r['alertas'] > 0],
        ];
        $gap = 4.0;
        $ancho = (self::ANCHO - $gap * (count($cajas) - 1)) / count($cajas);
        $y = $pdf->GetY();

        foreach ($cajas as $i => [$etiqueta, $valor, $nota, $alerta]) {
            $x = self::IZQ + $i * ($ancho + $gap);
            $pdf->SetDrawColor(217, 225, 236);
            $pdf->SetFillColor(...($alerta ? [255, 250, 240] : [248, 250, 252]));
            $pdf->Rect($x, $y, $ancho, 19, 'DF');
            $pdf->SetXY($x + 2.5, $y + 2);
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($ancho - 5, 3.5, $pdf->ajustar($etiqueta, $ancho - 5));
            $pdf->SetXY($x + 2.5, $y + 6.5);
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->SetTextColor(...($alerta ? self::ROJO : [15, 23, 42]));
            $pdf->Cell($ancho - 5, 6, ReportePdf::t($valor));
            $pdf->SetXY($x + 2.5, $y + 13.5);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($ancho - 5, 3.5, $pdf->ajustar($nota, $ancho - 5));
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($y + 19 + 5);
    }

    /** @param  Collection<int, array<string, mixed>>  $grupos */
    private function graficas(ReportePdf $pdf, Collection $grupos, float $umbral): void
    {
        $bloques = $grupos->chunk(self::GRUPOS_POR_GRAFICA);
        foreach ($bloques as $indice => $bloque) {
            if ($pdf->GetY() + self::ALTO_GRAFICA + 10 > $pdf->GetPageHeight() - 14) {
                $pdf->AddPage();
            }

            $pdf->SetFont('Helvetica', 'B', 10.5);
            $titulo = 'Calificación del docente vs. Gemini por evidencia (promedio, sobre 100)';
            if ($bloques->count() > 1) {
                $titulo .= ' - parte '.($indice + 1).' de '.$bloques->count();
            }
            $pdf->Cell(0, 6, ReportePdf::t($titulo), 0, 1);
            $this->leyenda($pdf);
            $this->barras($pdf, $bloque->values(), $pdf->GetY() + 1, $umbral);
            $pdf->SetY($pdf->GetY() + self::ALTO_GRAFICA + 4);
        }
    }

    private function leyenda(ReportePdf $pdf): void
    {
        $y = $pdf->GetY() + 0.5;
        $x = self::IZQ;
        $pdf->SetFont('Helvetica', '', 8);
        foreach ([[self::NAVY, 'Docente'], [self::VERDE, 'Gemini']] as [$color, $texto]) {
            $pdf->SetFillColor(...$color);
            $pdf->Rect($x, $y + 0.8, 3.5, 3.5, 'F');
            $pdf->SetXY($x + 5, $y);
            $pdf->Cell(20, 5, ReportePdf::t($texto));
            $x += 28;
        }
        $pdf->SetXY($x, $y);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 5, ReportePdf::t('Sobre cada par: diferencia porcentual frente a la calificación del docente'));
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($y + 6);
    }

    /** @param  Collection<int, array<string, mixed>>  $grupos */
    private function barras(ReportePdf $pdf, Collection $grupos, float $y0, float $umbral): void
    {
        $izqPlano = self::IZQ + 11;
        $anchoPlano = self::ANCHO - 11;
        $arriba = $y0 + 9;                       // espacio para las etiquetas de diferencia
        $alto = self::ALTO_GRAFICA - 9 - 15;     // 15 mm abajo para los nombres de evidencia
        $base = $arriba + $alto;

        // Cuadrícula y eje vertical de 0 a 100.
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(100, 116, 139);
        foreach ([0, 20, 40, 60, 80, 100] as $valor) {
            $y = $base - $alto * $valor / 100;
            $pdf->Line($izqPlano, $y, self::IZQ + self::ANCHO, $y);
            $pdf->SetXY(self::IZQ, $y - 1.8);
            $pdf->Cell(9, 3.6, (string) $valor, 0, 0, 'R');
        }

        $ranura = $anchoPlano / max($grupos->count(), 4);
        $anchoBarra = min(15.0, $ranura * 0.3);

        foreach ($grupos as $i => $grupo) {
            $centro = $izqPlano + $ranura * ($i + 0.5);
            $barras = [[self::NAVY, (float) $grupo['docente'], $centro - $anchoBarra - 0.6], [self::VERDE, (float) $grupo['ia'], $centro + 0.6]];

            foreach ($barras as [$color, $valor, $x]) {
                $altura = $alto * max(0, min($valor, 100)) / 100;
                $pdf->SetFillColor(...$color);
                $pdf->Rect($x, $base - $altura, $anchoBarra, $altura, 'F');
                $pdf->SetFont('Helvetica', '', 7);
                $pdf->SetTextColor(51, 65, 85);
                $pdf->SetXY($x - 2, $base - $altura - 3.6);
                $pdf->Cell($anchoBarra + 4, 3.4, number_format($valor, 1), 0, 0, 'C');
            }

            $alerta = $grupo['diferencia_pct'] > $umbral;
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->SetTextColor(...($alerta ? self::ROJO : [30, 58, 110]));
            $techo = $base - $alto * max($grupo['docente'], $grupo['ia'], 0) / 100;
            $pdf->SetXY($centro - $ranura / 2, max($y0 + 1, $techo - 8.2));
            $pdf->Cell($ranura, 3.8, ReportePdf::t('Dif. '.number_format($grupo['diferencia_pct'], 1).' %'), 0, 0, 'C');

            // Nombre de la evidencia (hasta 2 líneas) y profesor.
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(15, 23, 42);
            foreach ($pdf->lineas($grupo['evidencia'], $ranura - 2, 2) as $n => $linea) {
                $pdf->SetXY($centro - $ranura / 2, $base + 1.2 + $n * 3.4);
                $pdf->Cell($ranura, 3.4, $linea, 0, 0, 'C');
            }
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetFont('Helvetica', 'I', 6.5);
            $pdf->SetXY($centro - $ranura / 2, $base + 8.2);
            $pdf->Cell($ranura, 3.4, $pdf->ajustar($grupo['profesor'], $ranura - 2), 0, 0, 'C');
        }

        $pdf->SetDrawColor(100, 116, 139);
        $pdf->Line($izqPlano, $base, self::IZQ + self::ANCHO, $base);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
    }

    /** @param  Collection<int, array<string, mixed>>  $grupos */
    private function tablaEvidencias(ReportePdf $pdf, Collection $grupos, float $umbral): void
    {
        $this->titulo($pdf, 'Resumen por evidencia');
        $columnas = [['Evidencia', 88, 'L'], ['Profesor', 62, 'L'], ['Documentos', 24, 'R'], ['Docente', 26, 'R'], ['Gemini', 26, 'R'], ['Dif. %', 29.4, 'R']];
        $this->tabla($pdf, $columnas, $grupos->map(fn ($g) => [
            'celdas' => [$g['evidencia'], $g['profesor'], (string) $g['documentos'], number_format($g['docente'], 1), number_format($g['ia'], 1), number_format($g['diferencia_pct'], 1).' %'],
            'alerta' => $g['diferencia_pct'] > $umbral,
        ])->all());
    }

    /** @param  Collection<int, array<string, mixed>>  $filas */
    private function tablaDocumentos(ReportePdf $pdf, Collection $filas, float $umbral): void
    {
        $pdf->Ln(4);
        $this->titulo($pdf, 'Detalle por documento');
        $columnas = [['Equipo', 38, 'L'], ['Evidencia', 62, 'L'], ['Profesor', 50, 'L'], ['Evaluación docente', 28, 'L'], ['Docente', 20, 'R'], ['Gemini', 20, 'R'], ['Dif. pts', 17, 'R'], ['Dif. %', 20.4, 'R']];
        $this->tabla($pdf, $columnas, $filas->map(fn ($f) => [
            'celdas' => [
                $f['equipo'], $f['evidencia'], $f['profesor'], $f['fecha']?->format('d/m/Y H:i') ?? '-',
                number_format($f['docente'], 1), number_format($f['ia'], 1), number_format($f['diferencia_pts'], 1), number_format($f['diferencia_pct'], 1).' %',
            ],
            'alerta' => $f['alerta'],
        ])->all());

        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'I', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 4, ReportePdf::t('En rojo: diferencia porcentual mayor a '.number_format($umbral, 0).' %. Dif. % = |Gemini - docente| / docente x 100.'));
        $pdf->SetTextColor(0, 0, 0);
    }

    private function titulo(ReportePdf $pdf, string $texto): void
    {
        if ($pdf->GetY() + 30 > $pdf->GetPageHeight() - 14) {
            $pdf->AddPage();
        }
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 6, ReportePdf::t($texto), 0, 1);
    }

    /**
     * @param  array<int, array{0: string, 1: float, 2: string}>  $columnas
     * @param  array<int, array{celdas: array<int, string>, alerta: bool}>  $filas
     */
    private function tabla(ReportePdf $pdf, array $columnas, array $filas): void
    {
        $alto = 6.2;
        $encabezado = function () use ($pdf, $columnas, $alto) {
            $pdf->SetFillColor(...self::NAVY);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 8);
            foreach ($columnas as [$nombre, $ancho, $alin]) {
                $pdf->Cell($ancho, $alto, ReportePdf::t($nombre), 0, 0, $alin, true);
            }
            $pdf->Ln();
        };

        $encabezado();
        foreach ($filas as $i => $fila) {
            if ($pdf->GetY() + $alto > $pdf->GetPageHeight() - 14) {
                $pdf->AddPage();
                $encabezado();
            }
            $pdf->SetFillColor(...($i % 2 === 0 ? [255, 255, 255] : [244, 247, 251]));
            $pdf->SetFont('Helvetica', '', 8);
            foreach ($columnas as $n => [$nombre, $ancho, $alin]) {
                $esUltima = $n === count($columnas) - 1;
                $pdf->SetTextColor(...($fila['alerta'] && $esUltima ? self::ROJO : [15, 23, 42]));
                $pdf->SetFont('Helvetica', $fila['alerta'] && $esUltima ? 'B' : '', 8);
                $pdf->Cell($ancho, $alto, $pdf->ajustar($fila['celdas'][$n], $ancho - 2), 0, 0, $alin, true);
            }
            $pdf->Ln();
        }
        $pdf->SetTextColor(0, 0, 0);
    }
}
