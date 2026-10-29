<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SheetOvkExport implements
    FromArray,
    ShouldAutoSize,
    WithEvents
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function array(): array
    {
        $summaryRows = $this->payload['summary_rows'] ?? [];
        $detailRows = $this->payload['detail_rows'] ?? [];

        $rows = $summaryRows;

        $rows[] = [];
        $rows[] = [];

        $rows[] = [
            'DETAIL OVK PER LOKASI, BULAN, DAN REKENING',
        ];

        $rows[] = [
            'Akun Analitik/Akun Analitik',
            'MONTH',
            'Rekening/Kode',
            'Rekening/Nama',
            'Sum of MUTASI',
        ];

        foreach ($detailRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $firstColumn = trim((string) ($row[0] ?? ''));

            if (
                in_array(
                    $firstColumn,
                    [
                        'Akun Analitik/Akun Analitik',
                        'Akun Analitik/A',
                    ],
                    true
                )
            ) {
                continue;
            }

            $rows[] = array_pad(
                array_slice(array_values($row), 0, 5),
                5,
                null
            );
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->setTitle('OVK');

                $summaryRows =
                    $this->payload['summary_rows'] ?? [];

                $detailRows =
                    $this->payload['detail_rows'] ?? [];

                if (
                    empty($summaryRows) ||
                    empty($detailRows)
                ) {
                    return;
                }

                $summaryHeaderRow = 1;

                $summaryLastRow = count($summaryRows);

                $summaryLastColumn =
                    Coordinate::stringFromColumnIndex(
                        count($summaryRows[0])
                    );

                $detailTitleRow = $summaryLastRow + 2;

                $detailHeaderRow = $detailTitleRow + 1;

                $detailFirstRow = $detailHeaderRow + 1;

                $detailLastRow =
                    $detailHeaderRow + count($detailRows);

                $detailLastColumn = 'E';

                $this->styleHeader(
                    $sheet,
                    "A{$summaryHeaderRow}:{$summaryLastColumn}{$summaryHeaderRow}"
                );


                if ($summaryLastRow > 1) {
                    $sheet->getStyle(
                        "B2:{$summaryLastColumn}{$summaryLastRow}"
                    )->getNumberFormat()
                        ->setFormatCode(
                            '#,##0;[Red]\(#,##0\);-'
                        );
                }

                $this->styleGrandTotal(
                    $sheet,
                    "A{$summaryLastRow}:{$summaryLastColumn}{$summaryLastRow}"
                );


                $sheet->setCellValue(
                    "A{$detailTitleRow}",
                    'DETAIL OVK PER LOKASI, BULAN, DAN REKENING'
                );

                $sheet->getStyle(
                    "A{$detailTitleRow}:{$detailLastColumn}{$detailTitleRow}"
                )->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => [
                            'rgb' => 'FFFFFF',
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '4472C4',
                        ],
                    ],
                    'alignment' => [
                        'vertical' =>
                        Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->mergeCells(
                    "A{$detailTitleRow}:{$detailLastColumn}{$detailTitleRow}"
                );

                $sheet->getRowDimension($detailTitleRow)
                    ->setRowHeight(24);

                $this->styleHeader(
                    $sheet,
                    "A{$detailHeaderRow}:{$detailLastColumn}{$detailHeaderRow}"
                );

                if ($detailLastRow >= $detailFirstRow) {
                    $sheet->getStyle(
                        "B{$detailFirstRow}:B{$detailLastRow}"
                    )->getNumberFormat()
                        ->setFormatCode('0');

                    $sheet->getStyle(
                        "E{$detailFirstRow}:E{$detailLastRow}"
                    )->getNumberFormat()
                        ->setFormatCode(
                            '#,##0;[Red]\(#,##0\);-'
                        );
                }

                $this->styleGrandTotal(
                    $sheet,
                    "A{$detailLastRow}:{$detailLastColumn}{$detailLastRow}"
                );


                $sheet->getStyle(
                    "A1:{$summaryLastColumn}{$summaryLastRow}"
                )->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        Border::BORDER_HAIR
                    );

                $sheet->getStyle(
                    "A{$detailHeaderRow}:{$detailLastColumn}{$detailLastRow}"
                )->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        Border::BORDER_HAIR
                    );


                $sheet->getColumnDimension('A')
                    ->setWidth(30);

                for (
                    $i = 2;
                    $i <= count($summaryRows[0]);
                    $i++
                ) {
                    $column =
                        Coordinate::stringFromColumnIndex($i);

                    $sheet->getColumnDimension($column)
                        ->setWidth(22);
                }

                $sheet->getColumnDimension('A')
                    ->setWidth(32);

                $sheet->getColumnDimension('B')
                    ->setWidth(12);

                $sheet->getColumnDimension('C')
                    ->setWidth(20);

                $sheet->getColumnDimension('D')
                    ->setWidth(42);

                $sheet->getColumnDimension('E')
                    ->setWidth(22);

                $sheet->setAutoFilter(
                    "A{$detailHeaderRow}:{$detailLastColumn}" .
                        ($detailLastRow - 1)
                );

                $sheet->freezePane('B2');


                $sheet->getStyle(
                    "A{$summaryHeaderRow}:{$summaryLastColumn}{$summaryHeaderRow}"
                )->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                $sheet->getStyle(
                    "A{$detailHeaderRow}:{$detailLastColumn}{$detailHeaderRow}"
                )->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                $sheet->getStyle(
                    "B{$detailFirstRow}:B{$detailLastRow}"
                )->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                $sheet->getStyle(
                    "B2:{$summaryLastColumn}{$summaryLastRow}"
                )->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_RIGHT
                    );

                $sheet->getStyle(
                    "E{$detailFirstRow}:E{$detailLastRow}"
                )->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_RIGHT
                    );
            },
        ];
    }

    private function styleHeader(
        $sheet,
        string $range
    ): void {
        $sheet->getStyle($range)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'color' => [
                        'rgb' => '000000',
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                    Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                    Alignment::VERTICAL_CENTER,
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
                        'borderStyle' =>
                        Border::BORDER_THIN,
                        'color' => [
                            'rgb' => 'B7B7B7',
                        ],
                    ],
                ],
            ]);
    }

    private function styleGrandTotal(
        $sheet,
        string $range
    ): void {
        $sheet->getStyle($range)
            ->applyFromArray([
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
                        'borderStyle' =>
                        Border::BORDER_DOUBLE,
                        'color' => [
                            'rgb' => '000000',
                        ],
                    ],
                ],
            ]);
    }
}
