<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetOvkExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetOvkExportTest extends TestCase
{
    public function test_exported_ovk_sheet_has_filterable_summary_and_detail_tables(): void
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
        $sheet = $workbook->getSheetByName('OVK');
        $tables = array_values(iterator_to_array($sheet->getTableCollection()));

        $this->assertSame(
            ['A2:C3', 'A9:E10'],
            array_map(fn ($table) => $table->getRange(), $tables)
        );
        $this->assertSame(
            ['A2:C3', 'A9:E10'],
            array_map(
                fn ($table) => $table->getAutoFilter()->getRange(),
                $tables
            )
        );
        $this->assertSame(
            'Rekening/Nama',
            $sheet->getCell('D9')->getValue()
        );
        $this->assertSame(
            'Sum of MUTASI',
            $sheet->getCell('E9')->getValue()
        );
        $this->assertSame('Grand Total', $sheet->getCell('A4')->getValue());
        $this->assertSame('Grand Total', $sheet->getCell('A11')->getValue());
        $this->assertSame('=SUBTOTAL(9,B3:B3)', $sheet->getCell('B4')->getValue());
        $this->assertSame('=SUBTOTAL(9,E10:E10)', $sheet->getCell('E11')->getValue());
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
