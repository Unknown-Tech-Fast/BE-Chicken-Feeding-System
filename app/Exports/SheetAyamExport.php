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

                $sheet->setTitle('AYAM');
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
                            $column . ($rowIndex + 1),
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
                foreach (['A', 'B', 'C'] as $column) {
                    $sheet->mergeCells(
                        "{$column}1:{$column}2"
                    );
                }

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

                foreach ([
                    $pembelianTotalColumn,
                    $blankColumn,
                    $blankTotalColumn,
                    $grandTotalColumn,
                ] as $columnIndex) {
                    $column = Coordinate::stringFromColumnIndex(
                        $columnIndex
                    );

                    $sheet->mergeCells(
                        "{$column}1:{$column}2"
                    );
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

                $this->writeRekapBulanan($sheet);

                $sheet->freezePane('B3');
            },
        ];
    }

    private function writeRekapBulanan(Worksheet $sheet): void
    {
        $rekapRows = $this->payload['rekap_rows'] ?? [];

        $colNpwp = 'AF';
        $colPartner = 'AG';
        $startMonthColumn = 34; // AH

        $sheet->mergeCells('AH1:AS1');
        $sheet->setCellValue('AH1', 'BULAN');

        $sheet->setCellValue('AF2', 'NAMA NPWP');
        $sheet->setCellValue('AG2', 'Rekanan/Nama');

        for ($month = 1; $month <= 12; $month++) {
            $column = Coordinate::stringFromColumnIndex(
                $startMonthColumn + $month - 1
            );

            $sheet->setCellValue($column . '2', $month);
        }

        $sheet->getStyle('AF1:AS2')->applyFromArray([
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

        $startRow = 3;
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

        $sheet->getStyle(
            "AH{$startRow}:AS{$lastRow}"
        )->getNumberFormat()->setFormatCode(
            '#,##0;[Red](#,##0);-'
        );

        $sheet->getStyle(
            "AF2:AS{$lastRow}"
        )->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR);

        $sheet->getStyle(
            "AF3:AG{$lastRow}"
        )->getAlignment()->setHorizontal(
            Alignment::HORIZONTAL_LEFT
        );

        $sheet->getStyle(
            "AF3:AS{$lastRow}"
        )->getAlignment()->setVertical(
            Alignment::VERTICAL_CENTER
        );

        $sheet->getStyle(
            "AH3:AS{$lastRow}"
        )->getAlignment()->setHorizontal(
            Alignment::HORIZONTAL_RIGHT
        );

        $sheet->getStyle(
            "AF{$lastRow}:AS{$lastRow}"
        )->getFont()->setBold(true);

        $sheet->getStyle(
            "AF{$lastRow}:AS{$lastRow}"
        )->getBorders()->getTop()->setBorderStyle(
            Border::BORDER_THIN
        );

        $sheet->getColumnDimension('AF')->setWidth(32);
        $sheet->getColumnDimension('AG')->setWidth(48);

        for ($month = 1; $month <= 12; $month++) {
            $column = Coordinate::stringFromColumnIndex(
                $startMonthColumn + $month - 1
            );

            $sheet->getColumnDimension($column)->setWidth(16);
        }
    }
}