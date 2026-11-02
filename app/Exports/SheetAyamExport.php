<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetAyamExport implements FromArray, WithEvents
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function array(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->setTitle('AYAM Transaksi');
                $headerTop = $this->payload['header_top'] ?? [];
                $headerBottom = $this->payload['header_bottom'] ?? [];
                $exportRows = $this->payload['export_rows'] ?? [];

                $pivotRows = array_merge(
                    [$headerTop, $headerBottom],
                    $exportRows
                );

                foreach ($pivotRows as $rowIndex => $rowData) {
                    foreach ($rowData as $columnIndex => $value) {
                        $column = Coordinate::stringFromColumnIndex(
                            $columnIndex + 1
                        );

                        $sheet->setCellValue(
                            $column.($rowIndex + 1),
                            $value
                        );
                    }
                }

                $locationCount = (int) (
                    $this->payload['location_count'] ?? 0
                );

                $firstLocationColumn = 4;

                $lastLocationColumn = $locationCount > 0
                    ? $firstLocationColumn + $locationCount - 1
                    : 3;

                $pembelianTotalColumn =
                    $firstLocationColumn + $locationCount;

                $blankColumn = $pembelianTotalColumn + 1;
                $blankTotalColumn = $pembelianTotalColumn + 2;
                $grandTotalColumn = $pembelianTotalColumn + 3;

                $pivotLastColumn = Coordinate::stringFromColumnIndex(
                    $grandTotalColumn
                );

                $pivotLastRow = count($pivotRows);

                if ($locationCount > 1) {
                    $start = Coordinate::stringFromColumnIndex(
                        $firstLocationColumn
                    );

                    $end = Coordinate::stringFromColumnIndex(
                        $lastLocationColumn
                    );

                    $sheet->mergeCells(
                        "{$start}1:{$end}1"
                    );
                }

                $sheet->setCellValue('A1', null);
                $sheet->setCellValue('B1', null);
                $sheet->setCellValue('C1', null);

                $filterHeaders = [
                    'MONTH',
                    'MATI/JUAL',
                    'MATI/JUAL Total',
                ];

                for ($i = $firstLocationColumn; $i <= $lastLocationColumn; $i++) {
                    $location = $headerBottom[$i - 1] ?? '';
                    $filterHeaders[] = 'PEMBELIAN - '.$location;
                }

                $filterHeaders[] = 'PEMBELIAN Total';
                $filterHeaders[] = '(blank)';
                $filterHeaders[] = '(blank) Total';
                $filterHeaders[] = 'Grand Total';

                $sheet->fromArray($filterHeaders, null, 'A2');

                foreach ([
                    $pembelianTotalColumn,
                    $blankColumn,
                    $blankTotalColumn,
                    $grandTotalColumn,
                ] as $columnIndex) {
                    $column = Coordinate::stringFromColumnIndex(
                        $columnIndex
                    );

                    $sheet->setCellValue("{$column}1", null);
                }

                $sheet->getStyle(
                    "A1:{$pivotLastColumn}2"
                )->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'D9E2F3',
                        ],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => 'B7B7B7',
                            ],
                        ],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(27);
                $sheet->getRowDimension(2)->setRowHeight(32);

                if ($pivotLastRow >= 3) {
                    $sheet->getStyle(
                        "B3:{$pivotLastColumn}{$pivotLastRow}"
                    )->getNumberFormat()->setFormatCode(
                        '#,##0;[Red](#,##0);-'
                    );

                    $sheet->getStyle(
                        "A1:{$pivotLastColumn}{$pivotLastRow}"
                    )->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_HAIR);

                    $sheet->getStyle(
                        "A{$pivotLastRow}:{$pivotLastColumn}{$pivotLastRow}"
                    )->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => [
                                'rgb' => 'D9E2F3',
                            ],
                        ],
                    ]);
                }

                $pivotDataLastRow = $pivotLastRow - 1;
                if ($pivotDataLastRow >= 3) {
                    ExcelFilterTable::add(
                        $sheet,
                        "A2:{$pivotLastColumn}{$pivotDataLastRow}",
                        'AyamMonthlyTransactions'
                    );
                    ExcelFilterTable::setSubtotalValues(
                        $sheet,
                        $pivotLastRow,
                        3,
                        $pivotDataLastRow,
                        'B',
                        $pivotLastColumn
                    );
                }

                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(20);

                for (
                    $i = $firstLocationColumn;
                    $i <= $lastLocationColumn;
                    $i++
                ) {
                    $column = Coordinate::stringFromColumnIndex($i);

                    $sheet->getColumnDimension($column)->setWidth(22);
                }

                foreach ([
                    $pembelianTotalColumn,
                    $blankColumn,
                    $blankTotalColumn,
                    $grandTotalColumn,
                ] as $columnIndex) {
                    $column = Coordinate::stringFromColumnIndex(
                        $columnIndex
                    );

                    $sheet->getColumnDimension($column)->setWidth(20);
                }

                $rekapSheet = $sheet->getParent()->createSheet();
                $rekapSheet->setTitle('AYAM Rekap Bulanan');
                $this->writeRekapBulanan($rekapSheet);

                $sheet->freezePane('B3');
                $sheet->getParent()->setActiveSheetIndex(0);
            },
        ];
    }

    private function writeRekapBulanan(Worksheet $sheet): void
    {
        $rekapRows = $this->payload['rekap_rows'] ?? [];

        $colNpwp = 'A';
        $colPartner = 'B';
        $startMonthColumn = 3;

        for ($month = 1; $month <= 12; $month++) {
            $column = Coordinate::stringFromColumnIndex(
                $startMonthColumn + $month - 1
            );

            $sheet->setCellValue($column.'1', (string) $month);
        }

        $sheet->setCellValue('A1', 'NAMA NPWP');
        $sheet->setCellValue('B1', 'Rekanan/Nama');
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'FFFF00',
                ],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => [
                        'rgb' => 'B7B7B7',
                    ],
                ],
            ],
        ]);

        $startRow = 2;
        $rowIndex = $startRow;

        foreach ($rekapRows as $rekapRow) {
            $namaNpwp = trim(
                (string) ($rekapRow[0] ?? '')
            );

            $rekananNama = trim(
                (string) ($rekapRow[1] ?? '')
            );

            $sheet->setCellValue(
                "{$colNpwp}{$rowIndex}",
                $namaNpwp
            );

            $sheet->setCellValue(
                "{$colPartner}{$rowIndex}",
                $rekananNama
            );

            for ($month = 1; $month <= 12; $month++) {
                $column = Coordinate::stringFromColumnIndex(
                    $startMonthColumn + $month - 1
                );

                $value = $rekapRow[$month + 1] ?? 0;

                $sheet->setCellValue(
                    "{$column}{$rowIndex}",
                    (float) $value
                );
            }

            $rowIndex++;
        }

        $lastRow = max($startRow, $rowIndex - 1);
        $dataLastRow = $lastRow - 1;

        $sheet->getStyle(
            "C{$startRow}:N{$lastRow}"
        )->getNumberFormat()->setFormatCode(
            '#,##0;[Red](#,##0);-'
        );

        $sheet->getStyle(
            "A1:N{$lastRow}"
        )->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR);

        $sheet->getStyle(
            "A{$startRow}:B{$lastRow}"
        )->getAlignment()->setHorizontal(
            Alignment::HORIZONTAL_LEFT
        );

        $sheet->getStyle(
            "A{$startRow}:N{$lastRow}"
        )->getAlignment()->setVertical(
            Alignment::VERTICAL_CENTER
        );

        $sheet->getStyle(
            "C{$startRow}:N{$lastRow}"
        )->getAlignment()->setHorizontal(
            Alignment::HORIZONTAL_RIGHT
        );

        $sheet->getStyle(
            "A{$lastRow}:N{$lastRow}"
        )->getFont()->setBold(true);

        $sheet->getStyle(
            "A{$lastRow}:N{$lastRow}"
        )->getBorders()->getTop()->setBorderStyle(
            Border::BORDER_THIN
        );

        if ($dataLastRow >= $startRow) {
            ExcelFilterTable::add(
                $sheet,
                "A1:N{$dataLastRow}",
                'AyamPartnerMonthlySummary'
            );
            ExcelFilterTable::setSubtotalValues(
                $sheet,
                $lastRow,
                $startRow,
                $dataLastRow,
                'C',
                'N'
            );
        }

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(48);

        for ($month = 1; $month <= 12; $month++) {
            $column = Coordinate::stringFromColumnIndex(
                $startMonthColumn + $month - 1
            );

            $sheet->getColumnDimension($column)->setWidth(16);
        }
    }
}
