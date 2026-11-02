<?php

namespace Tests\Feature\Exports;

use App\Exports\SheetPakanOvdExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SheetPakanOvdExportTest extends TestCase
{
    public function test_exported_pakan_ovd_tables_are_filterable_on_separate_sheets(): void
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
        $sheetNames = [
            'PAKAN Akun Bulanan',
            'PAKAN Akun Lokasi',
            'PAKAN Analitik',
            'PAKAN Rekap Rekanan',
        ];
        $expectedRanges = [
            'A4:L5',
            'A3:H4',
            'A2:H3',
            'A6:P7',
        ];

        $this->assertSame(4, $workbook->getSheetCount());

        foreach ($sheetNames as $index => $sheetName) {
            $sheet = $workbook->getSheetByName($sheetName);
            $tables = array_values(
                iterator_to_array($sheet->getTableCollection())
            );

            $this->assertSame(
                [$expectedRanges[$index]],
                array_map(fn ($table) => $table->getRange(), $tables)
            );
            $this->assertSame(
                [$expectedRanges[$index]],
                array_map(
                    fn ($table) => $table->getAutoFilter()->getRange(),
                    $tables
                )
            );
        }

        $accountLocationSheet = $workbook->getSheetByName('PAKAN Akun Lokasi');
        $monthlySheet = $workbook->getSheetByName('PAKAN Akun Bulanan');
        $analyticalSheet = $workbook->getSheetByName('PAKAN Analitik');
        $partnerSheet = $workbook->getSheetByName('PAKAN Rekap Rekanan');

        $this->assertSame('MONTH', $accountLocationSheet->getCell('A3')->getValue());
        $this->assertSame(
            'Rekening/Kode',
            $accountLocationSheet->getCell('B3')->getValue()
        );
        $this->assertSame('BULAN', $monthlySheet->getCell('A4')->getValue());
        $this->assertSame(
            'Rekanan/Nama',
            $partnerSheet->getCell('C6')->getValue()
        );
        $this->assertSame('Grand Total', $monthlySheet->getCell('A6')->getValue());
        $this->assertSame('Grand Total', $partnerSheet->getCell('A8')->getValue());
        $this->assertSame('=SUBTOTAL(9,B5:B5)', $monthlySheet->getCell('B6')->getValue());
        $this->assertSame('=SUBTOTAL(9,D4:D4)', $accountLocationSheet->getCell('D5')->getValue());
        $this->assertSame('=SUBTOTAL(9,E3:E3)', $analyticalSheet->getCell('E4')->getValue());
        $this->assertSame('=SUBTOTAL(9,D7:D7)', $partnerSheet->getCell('D8')->getValue());
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
