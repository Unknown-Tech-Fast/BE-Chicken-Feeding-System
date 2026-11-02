<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetAyamExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetAyamExportTest extends TestCase
{
    public function test_exported_ayam_tables_are_filterable_on_separate_sheets(): void
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
        $transactionSheet = $workbook->getSheetByName('AYAM Transaksi');
        $monthlySheet = $workbook->getSheetByName('AYAM Rekap Bulanan');
        $transactionTables = array_values(
            iterator_to_array($transactionSheet->getTableCollection())
        );
        $monthlyTables = array_values(
            iterator_to_array($monthlySheet->getTableCollection())
        );

        $this->assertSame(2, $workbook->getSheetCount());
        $this->assertSame(
            ['A2:H3'],
            array_map(fn ($table) => $table->getRange(), $transactionTables)
        );
        $this->assertSame(
            ['A1:N2'],
            array_map(
                fn ($table) => $table->getAutoFilter()->getRange(),
                $monthlyTables
            )
        );
        $this->assertSame(
            'PEMBELIAN - Farm A',
            $transactionSheet->getCell('D2')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $transactionSheet->getCell('A4')->getValue()
        );
        $this->assertSame(
            'Grand Total',
            $monthlySheet->getCell('A3')->getValue()
        );
        $this->assertSame('=SUBTOTAL(9,B3:B3)', $transactionSheet->getCell('B4')->getValue());
        $this->assertSame(
            '=SUBTOTAL(9,C2:C2)',
            $monthlySheet->getCell('C3')->getValue()
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
