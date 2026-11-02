<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetPakanOvdExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetPakanOvdExportTest extends TestCase
{
    public function test_exported_pakan_ovd_sheet_has_filterable_pivot_tables(): void
    {
        $export = new SheetPakanOvdExport([
            'pivot1' => [
                'data_rows' => [[1, ...array_fill(0, 11, 10)]],
                'grand_total' => ['Grand Total', ...array_fill(0, 11, 10)],
            ],
            'pivot2' => [
                'locations' => ['Farm A'],
                'data_rows' => [[
                    1,
                    '1-130-3',
                    'Persediaan Pakan',
                    10,
                    10,
                    20,
                    20,
                    30,
                ]],
                'grand_total' => ['Grand Total', '', '', 10, 10, 20, 20, 30],
            ],
            'pivot3' => [
                'headers' => [
                    'Rekening/Kode',
                    'Rekening/Nama',
                    'Akun Analitik/Akun Analitik',
                    'C',
                    '(blank)',
                    'PEMAKAIAN',
                    'PEMBELIAN',
                    'Grand Total',
                ],
                'data_rows' => [[
                    '1-130-3',
                    'Persediaan Pakan',
                    'Farm A',
                    1,
                    0,
                    10,
                    20,
                    30,
                ]],
                'grand_total' => ['Grand Total', '', '', '', 0, 10, 20, 30],
            ],
            'pivot4' => [
                'check_rows' => [
                    'rekap_pembelian' => array_fill(1, 12, 100),
                    'pembelian_pivot1' => array_fill(1, 12, 100),
                    'selisih' => array_fill(1, 12, 0),
                ],
                'headers' => [
                    'Partner Key',
                    'Kategori',
                    'Rekanan/Nama',
                    ...array_map('strval', range(1, 12)),
                    'Grand Total',
                ],
                'data_rows' => [
                    ['Partner A', 'PAKAN', 'Partner A', ...array_fill(0, 12, 10), 120],
                ],
                'grand_total' => [
                    'Grand Total',
                    '',
                    '',
                    ...array_fill(0, 12, 10),
                    120,
                ],
            ],
        ]);

        $workbook = $this->openWorkbook($export);
        $sheet = $workbook->getSheetByName('PAKAN.OVD');
        $tables = array_values(iterator_to_array($sheet->getTableCollection()));

        $this->assertSame(
            ['A4:L5', 'A11:H12', 'A17:H18', 'A27:P28'],
            array_map(fn ($table) => $table->getRange(), $tables)
        );
        $this->assertSame(
            ['A4:L5', 'A11:H12', 'A17:H18', 'A27:P28'],
            array_map(
                fn ($table) => $table->getAutoFilter()->getRange(),
                $tables
            )
        );
        $this->assertSame('MONTH', $sheet->getCell('A11')->getValue());
        $this->assertSame(
            'Rekening/Kode',
            $sheet->getCell('A17')->getValue()
        );
        $this->assertSame('BULAN', $sheet->getCell('A4')->getValue());
        $this->assertSame(
            'Rekanan/Nama',
            $sheet->getCell('C27')->getValue()
        );
        $this->assertSame('Grand Total', $sheet->getCell('A6')->getValue());
        $this->assertSame('Grand Total', $sheet->getCell('A29')->getValue());
        $this->assertSame('=SUBTOTAL(9,B5:B5)', $sheet->getCell('B6')->getValue());
        $this->assertSame('=SUBTOTAL(9,D12:D12)', $sheet->getCell('D13')->getValue());
        $this->assertSame('=SUBTOTAL(9,E18:E18)', $sheet->getCell('E19')->getValue());
        $this->assertSame('=SUBTOTAL(9,D28:D28)', $sheet->getCell('D29')->getValue());
    }

    private function openWorkbook(SheetPakanOvdExport $export): Spreadsheet
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'pakan-ovd-export-');
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
