<?php

namespace App\Services\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Ayudantes comunes de los reportes en Excel.
trait EscribeExcel
{
    // Todo texto se guarda como cadena: un título que empiece con "=" no se interpreta como fórmula en Excel.
    private function texto(Worksheet $hoja, string $celda, string $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }

    private function estiloEncabezado(Worksheet $hoja, string $rango): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A6E');
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }
}
