<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetAyamExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetAyamExportTest extends TestCase
{
    public function test_exported_ayam_sheet_has_filterable_transaction_and_partner_tables(): void
    {
        $export = new SheetAyamExport([
            'header_top' => [
                'Row Labels',
                'MATI/JUAL',
                'MATI/JUAL Total',
                'PEMBELIAN',
                'PEMBELIAN Total',
                '(blank)',
                '(blank) Total',
                'Grand Total',
            ],
            'header_bottom' => ['', '', '', 'Farm A', '', '', '', ''],
            'export_rows' => [
                [1, 10, 10, 20, 20, 0, 0, 30],
                ['Grand Total', 10, 10, 20, 20, 0, 0, 30],
            ],
            'location_count' => 1,
            'rekap_rows' => [
                ['NPWP A', 'Partner A', ...array_fill(1, 12, 100)],
                ['Grand Total', '', ...array_fill(1, 12, 100)],
            ],
        ]);

        $workbook = $this->openWorkbook($export);
        $sheet = $workbook->getSheetByName('AYAM');
        $tables = array_values(iterator_to_array($sheet->getTableCollection()));

        $this->assertSame(
            ['A2:H3', 'AF2:AS3'],
            array_map(fn ($table) => $table->getRange(), $tables)
        );
        $this->assertSame(
            ['A2:H3', 'AF2:AS3'],
            array_map(
                fn ($table) => $table->getAutoFilter()->getRange(),
                $tables
            )
        );
        $this->assertSame(
            'PEMBELIAN - Farm A',
            $sheet->getCell('D2')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $sheet->getCell('A4')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $sheet->getCell('AF4')->getValue()
        );
        $this->assertSame('=SUBTOTAL(9,B3:B3)', $sheet->getCell('B4')->getValue());
        $this->assertSame(
            '=SUBTOTAL(9,AH3:AH3)',
            $sheet->getCell('AH4')->getValue()
        );
    }

    private function openWorkbook(SheetAyamExport $export): Spreadsheet
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ayam-export-');
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
