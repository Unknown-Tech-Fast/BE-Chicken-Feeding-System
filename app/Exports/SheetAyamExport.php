<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetAyamExport implements FromArray, WithEvents, WithStyles
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function array(): array
    {
        return array_merge(
            [
                $this->payload['header_top'] ?? [],
                $this->payload['header_bottom'] ?? [],
            ],
            $this->payload['export_rows'] ?? []
        );
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        return [
            1 => [
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
            ],

            2 => [
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
            ],

            $lastRow => [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'D9E2F3',
                    ],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => [
                            'rgb' => '4472C4',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

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

                $lastColumn = Coordinate::stringFromColumnIndex(
                    $grandTotalColumn
                );

                $lastRow = $sheet->getHighestRow();

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
                } elseif ($locationCount === 1) {
                    $column = Coordinate::stringFromColumnIndex(
                        $firstLocationColumn
                    );

                    $sheet->getStyle("{$column}1")
                        ->getAlignment()
                        ->setHorizontal(
                            Alignment::HORIZONTAL_CENTER
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

                $sheet->freezePane('B3');
                $sheet->getRowDimension(1)->setRowHeight(27);
                $sheet->getRowDimension(2)->setRowHeight(32);

                if ($lastRow >= 3) {
                    $sheet->getStyle(
                        "B3:{$lastColumn}{$lastRow}"
                    )
                        ->getNumberFormat()
                        ->setFormatCode(
                            '#,##0;[Red](#,##0);-'
                        );
                }

                $sheet->getStyle(
                    "A1:{$lastColumn}{$lastRow}"
                )
                    ->getAlignment()
                    ->setVertical(
                        Alignment::VERTICAL_CENTER
                    );

                $sheet->getStyle(
                    "A1:{$lastColumn}2"
                )
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                if ($lastRow >= 3) {
                    $sheet->getStyle(
                        "B3:{$lastColumn}{$lastRow}"
                    )
                        ->getAlignment()
                        ->setHorizontal(
                            Alignment::HORIZONTAL_RIGHT
                        );

                    $sheet->getStyle(
                        "A3:A{$lastRow}"
                    )
                        ->getAlignment()
                        ->setHorizontal(
                            Alignment::HORIZONTAL_LEFT
                        );
                }

                $sheet->getStyle(
                    "A1:{$lastColumn}{$lastRow}"
                )
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        Border::BORDER_HAIR
                    );

                $sheet->getColumnDimension('A')
                    ->setWidth(16);

                $sheet->getColumnDimension('B')
                    ->setWidth(16);

                $sheet->getColumnDimension('C')
                    ->setWidth(20);

                for (
                    $i = $firstLocationColumn;
                    $i <= $lastLocationColumn;
                    $i++
                ) {
                    $column = Coordinate::stringFromColumnIndex(
                        $i
                    );

                    $sheet->getColumnDimension($column)
                        ->setWidth(22);
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

                    $sheet->getColumnDimension($column)
                        ->setWidth(20);
                }

                $sheet->getStyle(
                    "A1:{$lastColumn}2"
                )
                    ->getAlignment()
                    ->setWrapText(true);

                $sheet->getStyle(
                    "A1:{$lastColumn}2"
                )
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID);

                $sheet->getStyle(
                    "A1:{$lastColumn}2"
                )
                    ->getFill()
                    ->getStartColor()
                    ->setRGB('D9E2F3');

                $sheet->getStyle(
                    "A{$lastRow}:{$lastColumn}{$lastRow}"
                )
                    ->getFont()
                    ->setBold(true);
            },
        ];
    }
}