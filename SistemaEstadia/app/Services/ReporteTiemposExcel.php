<?php

namespace App\Services;

use App\Services\Concerns\EscribeExcel;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Genera el Excel del Reporte de Eficiencia de Tiempos con una gráfica de columnas comparativas nativa de Excel.
class ReporteTiemposExcel
{
    use EscribeExcel;

    private const NAVY = '1E3A6E';

    private const VERDE = '3F9A6B';

    /**
     * @param  array{filas: Collection<int, array<string, mixed>>, grupos: Collection<int, array<string, mixed>>, resumen: array<string, float|int>|null}  $reporte
     * @param  array{desde: ?CarbonInterface, hasta: ?CarbonInterface, asignatura: ?string, generado_por: string, generado_en: CarbonInterface}  $filtros
     */
    public function generar(array $reporte, array $filtros): string
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setTitle('Reporte de Eficiencia de Tiempos')->setCreator('UPEMOR');

        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');
        $this->hojaResumen($resumen, $reporte, $filtros);
        $this->hojaDetalle($libro->createSheet(), $reporte['filas']);
        $libro->setActiveSheetIndex(0);

        $escritor = new Xlsx($libro);
        $escritor->setIncludeCharts(true);
        $ruta = tempnam(sys_get_temp_dir(), 'tiempos_');
        try {
            $escritor->save($ruta);

            return (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
            $libro->disconnectWorksheets();
        }
    }

    private function hojaResumen(Worksheet $hoja, array $reporte, array $filtros): void
    {
        $desde = $filtros['desde']?->format('d/m/Y');
        $hasta = $filtros['hasta']?->format('d/m/Y');
        $rango = match (true) {
            $desde && $hasta => "{$desde} al {$hasta}",
            (bool) $desde => "Desde el {$desde}",
            (bool) $hasta => "Hasta el {$hasta}",
            default => 'Todas las fechas',
        };

        $this->texto($hoja, 'A1', 'Reporte de Eficiencia de Tiempos');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
        $this->texto($hoja, 'A2', 'Rango de fechas');
        $this->texto($hoja, 'B2', $rango);
        $this->texto($hoja, 'A3', 'Asignatura');
        $this->texto($hoja, 'B3', $filtros['asignatura'] ?? 'Todas las asignaturas');
        $this->texto($hoja, 'A4', 'Generado');
        $this->texto($hoja, 'B4', $filtros['generado_en']->format('d/m/Y H:i').' por '.$filtros['generado_por']);
        $hoja->getStyle('A2:A4')->getFont()->setBold(true);

        $r = $reporte['resumen'];
        $grupos = $reporte['grupos'];
        $anchoColumnas = ['A' => 46, 'B' => 30, 'C' => 13, 'D' => 20, 'E' => 20, 'F' => 16, 'G' => 14];
        foreach ($anchoColumnas as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        if ($r === null) {
            $this->texto($hoja, 'A6', 'No hay documentos con evaluación del docente y de Gemini con los filtros seleccionados.');

            return;
        }

        $indicadores = [
            ['Documentos comparados', $r['documentos'], '0'],
            ['Evidencias', $r['evidencias'], '0'],
            ['Asignaturas', $r['asignaturas'], '0'],
            ['Revisión docente promedio (min)', $r['docente_prom_min'], '0.00'],
            ['Respuesta de la API promedio (min)', $r['ia_prom_min'], '0.00'],
            ['Respuesta de la API promedio (seg)', $r['ia_prom_seg'], '0.0'],
            ['Tiempo ahorrado por documento (min)', $r['ahorro_prom_min'], '0.00'],
            ['Tiempo ahorrado (%)', $r['ahorro_pct'] / 100, '0.0%'],
            ['Tiempo total de revisión docente (h)', $r['docente_total_horas'], '0.00'],
            ['Tiempo total de respuesta de la API (h)', $r['ia_total_horas'], '0.00'],
        ];
        $fila = 6;
        foreach ($indicadores as [$etiqueta, $valor, $formato]) {
            $this->texto($hoja, "A{$fila}", $etiqueta);
            $hoja->setCellValue("B{$fila}", $valor);
            $hoja->getStyle("B{$fila}")->getNumberFormat()->setFormatCode($formato);
            $hoja->getStyle("B{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $fila++;
        }
        $hoja->getStyle('A6:A'.($fila - 1))->getFont()->setBold(true);
        $hoja->getStyle('A6:B'.($fila - 1))->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D9E1EC');

        // Tabla por evidencia: es la fuente de la gráfica.
        $cabecera = $fila + 2;
        $encabezados = ['Evidencia', 'Asignatura', 'Documentos', 'Revisión docente (min)', 'Respuesta API (min)', 'Ahorro (min)', 'Ahorro (%)'];
        foreach ($encabezados as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).$cabecera, $texto);
        }
        $this->estiloEncabezado($hoja, "A{$cabecera}:G{$cabecera}");

        $primera = $cabecera + 1;
        foreach ($grupos->values() as $i => $g) {
            $f = $primera + $i;
            $this->texto($hoja, "A{$f}", $g['evidencia']);
            $this->texto($hoja, "B{$f}", $g['asignatura']);
            $hoja->setCellValue("C{$f}", $g['documentos']);
            $hoja->setCellValue("D{$f}", $g['docente_min']);
            $hoja->setCellValue("E{$f}", $g['ia_min']);
            $hoja->setCellValue("F{$f}", $g['ahorro_min']);
            $hoja->setCellValue("G{$f}", $g['ahorro_pct'] / 100);
        }
        $ultima = $primera + $grupos->count() - 1;
        $hoja->getStyle("D{$primera}:F{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("G{$primera}:G{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
        $hoja->getStyle("A{$primera}:G{$ultima}")->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D9E1EC');

        $this->grafica($hoja, $grupos, $cabecera, $primera, $ultima);
    }

    /** @param  Collection<int, array<string, mixed>>  $grupos */
    private function grafica(Worksheet $hoja, Collection $grupos, int $cabecera, int $primera, int $ultima): void
    {
        $n = $grupos->count();
        $ref = fn (string $columna, int $desde, ?int $hasta = null) => "'Resumen'!\${$columna}\${$desde}".($hasta ? ":\${$columna}\${$hasta}" : '');

        $categorias = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('A', $primera, $ultima), null, $n, $grupos->pluck('evidencia')->all())];
        $etiquetas = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('D', $cabecera), null, 1, ['Revisión docente (min)']),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('E', $cabecera), null, 1, ['Respuesta API (min)']),
        ];
        $valores = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $ref('D', $primera, $ultima), null, $n, $grupos->pluck('docente_min')->all()),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $ref('E', $primera, $ultima), null, $n, $grupos->pluck('ia_min')->all()),
        ];
        $valores[0]->setFillColor(self::NAVY);
        $valores[1]->setFillColor(self::VERDE);

        $serie = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0, 1], $etiquetas, $categorias, $valores);
        $serie->setPlotDirection(DataSeries::DIRECTION_COL);

        $grafica = new Chart(
            'graficaTiempos',
            new Title('Tiempo de revisión docente vs. respuesta de la API por evidencia (promedio, minutos)'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$serie]),
            true,
            'gap',
            new Title('Evidencia'),
            new Title('Minutos')
        );

        $inicio = $ultima + 3;
        $columnaFinal = Coordinate::stringFromColumnIndex(max(8, min(26, 4 + $n)));
        $grafica->setTopLeftPosition("A{$inicio}");
        $grafica->setBottomRightPosition("{$columnaFinal}".($inicio + 24));
        $hoja->addChart($grafica);
    }

    /** @param  Collection<int, array<string, mixed>>  $filas */
    private function hojaDetalle(Worksheet $hoja, Collection $filas): void
    {
        $hoja->setTitle('Detalle');
        $encabezados = ['Equipo', 'Evidencia', 'Asignatura', 'Evaluación docente', 'Revisión docente (min)', 'Respuesta API (seg)', 'Respuesta API (min)', 'Ahorro (min)', 'Ahorro (%)'];
        foreach ($encabezados as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).'1', $texto);
        }
        $this->estiloEncabezado($hoja, 'A1:I1');

        foreach ($filas->values() as $i => $f) {
            $r = $i + 2;
            $this->texto($hoja, "A{$r}", $f['equipo']);
            $this->texto($hoja, "B{$r}", $f['evidencia']);
            $this->texto($hoja, "C{$r}", $f['asignatura']);
            $this->texto($hoja, "D{$r}", $f['fecha']?->format('d/m/Y H:i') ?? '');
            $hoja->setCellValue("E{$r}", round($f['docente_min'], 2));
            $hoja->setCellValue("F{$r}", round($f['ia_seg'], 1));
            $hoja->setCellValue("G{$r}", round($f['ia_min'], 2));
            $hoja->setCellValue("H{$r}", round($f['ahorro_min'], 2));
            $hoja->setCellValue("I{$r}", round($f['ahorro_pct'] / 100, 4));
        }

        $ultima = max(2, $filas->count() + 1);
        $hoja->getStyle("E2:E{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("F2:F{$ultima}")->getNumberFormat()->setFormatCode('0.0');
        $hoja->getStyle("G2:H{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("I2:I{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
        foreach (['A' => 22, 'B' => 46, 'C' => 30, 'D' => 20, 'E' => 22, 'F' => 20, 'G' => 20, 'H' => 14, 'I' => 12] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:I{$ultima}");
    }
}
