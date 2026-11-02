<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ExcelFilterTable
{
    public static function add(
        Worksheet $sheet,
        string $range,
        string $name
    ): void {
        $table = new Table($range, $name);
        $table->getStyle()->setTheme(TableStyle::TABLE_STYLE_NONE);

        $sheet->addTable($table);
    }

    public static function setSubtotalValues(
        Worksheet $sheet,
        int $totalRow,
        int $firstDataRow,
        int $lastDataRow,
        string $firstColumn,
        string $lastColumn
    ): void {
        $firstColumnIndex = Coordinate::columnIndexFromString($firstColumn);
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);

        for ($columnIndex = $firstColumnIndex; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->setCellValue(
                "{$column}{$totalRow}",
                "=SUBTOTAL(9,{$column}{$firstDataRow}:{$column}{$lastDataRow})"
            );
        }
    }
}
