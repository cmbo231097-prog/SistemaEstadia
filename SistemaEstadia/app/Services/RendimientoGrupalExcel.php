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

// Genera el Excel del Reporte de Rendimiento Grupal con una gráfica de columnas apiladas nativa de Excel.
class RendimientoGrupalExcel
{
    use EscribeExcel;

    private const HOJA_GRAFICA = 'Gráfica';

    /**
     * @param  array<string, mixed>  $reporte  Resultado de RendimientoGrupalService::generar().
     * @param  array{asignatura: string, grupo: string, evidencia: string, generado_por: string, generado_en: CarbonInterface}  $contexto
     */
    public function generar(array $reporte, array $contexto): string
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setTitle('Reporte de Rendimiento Grupal')->setCreator('UPEMOR');

        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');
        $this->hojaResumen($resumen, $reporte, $contexto);
        $this->hojaGrafica($libro->createSheet(), $reporte);
        $this->hojaDetalle($libro->createSheet(), $reporte);
        $libro->setActiveSheetIndex(0);

        $escritor = new Xlsx($libro);
        $escritor->setIncludeCharts(true);
        $ruta = tempnam(sys_get_temp_dir(), 'rendimiento_');
        try {
            $escritor->save($ruta);

            return (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
            $libro->disconnectWorksheets();
        }
    }

    private function hojaResumen(Worksheet $hoja, array $reporte, array $contexto): void
    {
        $this->texto($hoja, 'A1', 'Reporte de Rendimiento Grupal');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('1E3A6E');
        foreach ([['Asignatura', $contexto['asignatura']], ['Grupo', $contexto['grupo']], ['Evidencia', $contexto['evidencia']],
            ['Generado', $contexto['generado_en']->format('d/m/Y H:i').' por '.$contexto['generado_por']]] as $i => [$etiqueta, $valor]) {
            $this->texto($hoja, 'A'.(2 + $i), $etiqueta);
            $this->texto($hoja, 'B'.(2 + $i), $valor);
        }
        $hoja->getStyle('A2:A5')->getFont()->setBold(true);
        foreach (['A' => 40, 'B' => 28, 'C' => 16, 'D' => 20, 'E' => 14, 'F' => 18, 'G' => 14, 'H' => 22] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $r = $reporte['resumen'];
        $indicadores = [
            ['Equipos con documento', $r['equipos'], '0'],
            ['Equipos evaluados por el docente', $r['con_docente'], '0'],
            ['Equipos evaluados por la IA', $r['con_ia'], '0'],
            ['Calificación promedio del docente (sobre 100)', $r['docente_prom'], '0.00'],
            ['Calificación promedio de la IA (sobre '.rtrim(rtrim(number_format($r['ia_maximo'], 2, '.', ''), '0'), '.').')', $r['ia_prom'], '0.00'],
            ['Docente en los rubros que evalúa la IA (mismo máximo)', $r['docente_en_rubros_ia'], '0.00'],
        ];
        $fila = 7;
        foreach ($indicadores as [$etiqueta, $valor, $formato]) {
            $this->texto($hoja, "A{$fila}", $etiqueta);
            if ($valor === null) {
                $this->texto($hoja, "B{$fila}", 'Sin datos');
            } else {
                $hoja->setCellValue("B{$fila}", $valor);
                $hoja->getStyle("B{$fila}")->getNumberFormat()->setFormatCode($formato);
            }
            $hoja->getStyle("B{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $fila++;
        }
        $hoja->getStyle('A7:A'.($fila - 1))->getFont()->setBold(true);
        $hoja->getStyle('A7:B'.($fila - 1))->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D9E1EC');

        // Desempeño por rubro.
        $fila += 1;
        $this->texto($hoja, "A{$fila}", 'Desempeño por rubro (promedio del grupo)');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12);
        $fila++;
        $encabezados = ['Rubro', 'Máximo', 'Lo evalúa la IA', 'Promedio docente', '% docente', 'Promedio IA', '% IA', 'Diferencia (IA − docente)'];
        foreach ($encabezados as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).$fila, $texto);
        }
        $this->estiloEncabezado($hoja, "A{$fila}:H{$fila}");
        $primera = ++$fila;
        foreach ($reporte['rubros'] as $rubro) {
            $this->texto($hoja, "A{$fila}", $rubro['nombre']);
            $hoja->setCellValue("B{$fila}", $rubro['maximo']);
            $this->texto($hoja, "C{$fila}", $rubro['ia'] ? 'Sí' : 'No');
            $this->numero($hoja, "D{$fila}", $rubro['docente_prom']);
            $this->numero($hoja, "E{$fila}", $rubro['docente_pct'] === null ? null : $rubro['docente_pct'] / 100);
            $this->numero($hoja, "F{$fila}", $rubro['ia_prom']);
            $this->numero($hoja, "G{$fila}", $rubro['ia_pct'] === null ? null : $rubro['ia_pct'] / 100);
            $this->numero($hoja, "H{$fila}", $rubro['diferencia']);
            $fila++;
        }
        if ($fila > $primera) {
            $ultima = $fila - 1;
            $hoja->getStyle("B{$primera}:B{$ultima}")->getNumberFormat()->setFormatCode('0.00');
            $hoja->getStyle("D{$primera}:D{$ultima}")->getNumberFormat()->setFormatCode('0.00');
            $hoja->getStyle("F{$primera}:F{$ultima}")->getNumberFormat()->setFormatCode('0.00');
            $hoja->getStyle("H{$primera}:H{$ultima}")->getNumberFormat()->setFormatCode('+0.00;-0.00;0.00');
            $hoja->getStyle("E{$primera}:E{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
            $hoja->getStyle("G{$primera}:G{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
            $hoja->getStyle("A{$primera}:H{$ultima}")->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D9E1EC');
        }

        // Calificación total por equipo.
        $fila += 1;
        $this->texto($hoja, "A{$fila}", 'Calificación total por equipo');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12);
        $fila++;
        foreach (['Equipo', 'Docente (sobre 100)', 'IA'] as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).$fila, $texto);
        }
        $this->estiloEncabezado($hoja, "A{$fila}:C{$fila}");
        $fila++;
        $inicio = $fila;
        foreach ($reporte['equipos'] as $equipo) {
            $this->texto($hoja, "A{$fila}", $equipo['equipo']);
            $this->numero($hoja, "B{$fila}", $equipo['docente']['total'] ?? null);
            $this->numero($hoja, "C{$fila}", $equipo['ia']['total'] ?? null);
            $fila++;
        }
        if ($fila > $inicio) {
            $hoja->getStyle("B{$inicio}:C".($fila - 1))->getNumberFormat()->setFormatCode('0.00');
            $hoja->getStyle("A{$inicio}:C".($fila - 1))->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D9E1EC');
        }
    }

    /** La tabla de esta hoja es la fuente de la gráfica: una fila por equipo y fuente, una columna por rubro. */
    private function hojaGrafica(Worksheet $hoja, array $reporte): void
    {
        $hoja->setTitle(self::HOJA_GRAFICA);
        /** @var Collection<int, array<string, mixed>> $criterios */
        $criterios = $reporte['criterios'];
        $barras = $reporte['barras'];
        $haySinDesglose = $barras->contains('sin_desglose', true);

        $encabezados = ['Equipo · fuente', ...$criterios->pluck('nombre')->all()];
        if ($haySinDesglose) {
            $encabezados[] = 'Sin desglose por rubro';
        }
        $encabezados[] = 'Total';
        foreach ($encabezados as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).'1', $texto);
        }
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));
        $this->estiloEncabezado($hoja, "A1:{$ultimaColumna}1");
        $hoja->getColumnDimension('A')->setWidth(30);
        for ($c = 2; $c <= count($encabezados); $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        }

        foreach ($barras->values() as $i => $barra) {
            $fila = $i + 2;
            $this->texto($hoja, "A{$fila}", $barra['equipo'].' · '.$barra['fuente']);
            foreach ($criterios->values() as $k => $criterio) {
                $valor = $barra['segmentos'][$criterio['id']] ?? null;
                if ($valor !== null) {
                    $hoja->setCellValue(Coordinate::stringFromColumnIndex($k + 2).$fila, $valor);
                }
            }
            $columnaTotal = Coordinate::stringFromColumnIndex(count($encabezados));
            if ($haySinDesglose && $barra['sin_desglose']) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($criterios->count() + 2).$fila, $barra['total']);
            }
            $hoja->setCellValue("{$columnaTotal}{$fila}", $barra['total']);
        }
        $ultima = $barras->count() + 1;
        if ($barras->isNotEmpty()) {
            $hoja->getStyle("B2:{$ultimaColumna}{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        }
        $hoja->freezePane('B2');

        if ($barras->isEmpty()) {
            $this->texto($hoja, 'A3', 'Aún no hay evaluaciones del docente ni de la IA para graficar.');

            return;
        }

        // Series: un segmento por rubro (y uno extra si hay evaluaciones del docente sin desglose).
        $columnasSerie = $criterios->count() + ($haySinDesglose ? 1 : 0);
        $ref = fn (string $col, int $desde, ?int $hasta = null) => "'".self::HOJA_GRAFICA."'!\${$col}\${$desde}".($hasta ? ":\${$col}\${$hasta}" : '');
        $nombres = $criterios->pluck('nombre')->all();
        if ($haySinDesglose) {
            $nombres[] = 'Sin desglose por rubro';
        }

        $etiquetas = $valores = [];
        for ($k = 0; $k < $columnasSerie; $k++) {
            $col = Coordinate::stringFromColumnIndex($k + 2);
            $etiquetas[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref($col, 1), null, 1, [$nombres[$k]]);
            $punto = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $ref($col, 2, $ultima),
                null,
                $barras->count(),
                $barras->map(fn ($b) => $k < $criterios->count() ? ($b['segmentos'][$criterios[$k]['id']] ?? null) : ($b['sin_desglose'] ? $b['total'] : null))->all()
            );
            $punto->setFillColor($k < $criterios->count() ? RendimientoGrupalService::color($k) : '94A3B8');
            $valores[] = $punto;
        }
        $categorias = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('A', 2, $ultima), null, $barras->count(), $barras->map(fn ($b) => $b['equipo'].' · '.$b['fuente'])->all())];

        $serie = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STACKED, range(0, $columnasSerie - 1), $etiquetas, $categorias, $valores);
        $serie->setPlotDirection(DataSeries::DIRECTION_COL);

        $grafica = new Chart(
            'graficaRendimiento',
            new Title('Calificación por rubro: docente vs. IA por equipo (puntos)'),
            new Legend(Legend::POSITION_RIGHT, null, false),
            new PlotArea(null, [$serie]),
            true,
            'gap',
            new Title('Equipo · fuente'),
            new Title('Puntos')
        );
        $inicio = $ultima + 3;
        $columnaFinal = Coordinate::stringFromColumnIndex(max(10, min(30, 4 + $barras->count())));
        $grafica->setTopLeftPosition("A{$inicio}");
        $grafica->setBottomRightPosition("{$columnaFinal}".($inicio + 26));
        $hoja->addChart($grafica);
    }

    /** Detalle en formato largo (equipo × rubro): fácil de filtrar y de usar en tablas dinámicas. */
    private function hojaDetalle(Worksheet $hoja, array $reporte): void
    {
        $hoja->setTitle('Detalle por rubro');
        $encabezados = ['Equipo', 'Rubro', 'Máximo', 'Lo evalúa la IA', 'Docente', 'IA', 'Diferencia (IA − docente)', 'Retroalimentación del docente', 'Retroalimentación de la IA'];
        foreach ($encabezados as $i => $texto) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).'1', $texto);
        }
        $this->estiloEncabezado($hoja, 'A1:I1');

        $fila = 2;
        foreach ($reporte['equipos'] as $equipo) {
            foreach ($reporte['criterios'] as $criterio) {
                $docente = $equipo['docente']['criterios'][$criterio['id']] ?? null;
                $ia = $equipo['ia']['criterios'][$criterio['id']] ?? null;
                $this->texto($hoja, "A{$fila}", $equipo['equipo']);
                $this->texto($hoja, "B{$fila}", $criterio['nombre']);
                $hoja->setCellValue("C{$fila}", $criterio['maximo']);
                $this->texto($hoja, "D{$fila}", $criterio['ia'] ? 'Sí' : 'No');
                $this->numero($hoja, "E{$fila}", $docente);
                $this->numero($hoja, "F{$fila}", $ia);
                $this->numero($hoja, "G{$fila}", $docente !== null && $ia !== null ? round($ia - $docente, 2) : null);
                $this->texto($hoja, "H{$fila}", (string) ($equipo['docente']['retro'][$criterio['id']] ?? ''));
                $this->texto($hoja, "I{$fila}", (string) ($equipo['ia']['retro'][$criterio['id']] ?? ''));
                $fila++;
            }
        }
        $ultima = max(2, $fila - 1);
        $hoja->getStyle("C2:C{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("E2:F{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle("G2:G{$ultima}")->getNumberFormat()->setFormatCode('+0.00;-0.00;0.00');
        $hoja->getStyle("H2:I{$ultima}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach (['A' => 22, 'B' => 34, 'C' => 11, 'D' => 15, 'E' => 11, 'F' => 11, 'G' => 18, 'H' => 50, 'I' => 50] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:I{$ultima}");
    }

    // Una celda sin valor queda vacía (no se escribe un cero que parezca una calificación).
    private function numero(Worksheet $hoja, string $celda, ?float $valor): void
    {
        if ($valor !== null) {
            $hoja->setCellValue($celda, $valor);
        }
    }
}
