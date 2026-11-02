<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetOvkExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetOvkExportTest extends TestCase
{
    public function test_exported_ovk_tables_are_filterable_on_separate_sheets(): void
    {
        $export = new SheetOvkExport([
            'summary_rows' => [
                ['MONTH', 'Farm A', 'Grand Total'],
                [1, 100, 100],
                ['Grand Total', 100, 100],
            ],
            'detail_rows' => [
                [
                    'Akun Analitik/Akun Analitik',
                    'MONTH',
                    'Rekening/Kode',
                    'Rekening/Nama',
                    'Sum of MUTASI',
                ],
                ['Farm A', 1, '1-130-3', 'Pakan', 100],
                ['Grand Total', null, null, null, 100],
            ],
            'table_spacing_rows' => 3,
        ]);

        $workbook = $this->openWorkbook($export);
        $summarySheet = $workbook->getSheetByName('OVK Ringkasan');
        $detailSheet = $workbook->getSheetByName('OVK Detail');
        $summaryTables = array_values(
            iterator_to_array($summarySheet->getTableCollection())
        );
        $detailTables = array_values(
            iterator_to_array($detailSheet->getTableCollection())
        );

        $this->assertSame(2, $workbook->getSheetCount());
        $this->assertSame(
            ['A2:C3'],
            array_map(fn ($table) => $table->getRange(), $summaryTables)
        );
        $this->assertSame(
            ['A2:C3'],
            array_map(
                fn ($table) => $table->getAutoFilter()->getRange(),
                $summaryTables
            )
        );
        $this->assertSame(
            ['A2:E3'],
            array_map(fn ($table) => $table->getRange(), $detailTables)
        );
        $this->assertSame(
            'Rekening/Nama',
            $detailSheet->getCell('D2')->getValue()
        );
        $this->assertSame(
            'Sum of MUTASI',
            $detailSheet->getCell('E2')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $summarySheet->getCell('A4')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $detailSheet->getCell('A4')->getValue()
        );
        $this->assertSame(
            '=SUBTOTAL(9,B3:B3)',
            $summarySheet->getCell('B4')->getValue()
        );
        $this->assertSame(
            '=SUBTOTAL(9,E3:E3)',
            $detailSheet->getCell('E4')->getValue()
        );
    }

    private function openWorkbook(SheetOvkExport $export): Spreadsheet
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ovk-export-');
        if (! is_string($temporaryFile)) {
            self::fail('Unable to create a temporary Excel file.');
        }

        try {
            file_put_contents(
                $temporaryFile,
                Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX)
            );

            return IOFactory::load($temporaryFile);
        } finally {
            unlink($temporaryFile);
        }
    }
}
