<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SheetOvkExport implements FromArray, WithEvents
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
                $sheet->setTitle('OVK');

                $summaryRows = $this->payload['summary_rows'] ?? [];
                $detailRows = $this->payload['detail_rows'] ?? [];

                if (empty($summaryRows) && empty($detailRows)) {
                    return;
                }

                $summaryColCount = !empty($summaryRows[0]) ? count($summaryRows[0]) : 2;
                $summaryLastColumn = Coordinate::stringFromColumnIndex($summaryColCount);
                $detailLastColumn = 'E';

                $summaryTitle = $this->payload['summary_title'] ?? 'RINGKASAN OVK PER BULAN DAN LOKASI';
                $detailTitle = $this->payload['detail_title'] ?? 'DETAIL OVK PER LOKASI, BULAN, DAN REKENING';
                $spacingRowsCount = (int) ($this->payload['table_spacing_rows'] ?? 3);
                if ($spacingRowsCount < 2) {
                    $spacingRowsCount = 3;
                }

                // ==========================================
                // 1. TABLE 1: RINGKASAN OVK PER BULAN & LOKASI
                // ==========================================
                $currentRow = 1;

                // Section Title: Ringkasan
                $sheet->setCellValue("A{$currentRow}", $summaryTitle);
                $sheet->mergeCells("A{$currentRow}:{$summaryLastColumn}{$currentRow}");
                $this->styleSectionTitle($sheet, "A{$currentRow}:{$summaryLastColumn}{$currentRow}", '2F5597');
                $sheet->getRowDimension($currentRow)->setRowHeight(26);

                $currentRow++; // Row 2: Summary Header
                $summaryHeaderRow = $currentRow;
                $sheet->fromArray($summaryRows, null, "A{$summaryHeaderRow}");

                // Summary Row Count & Last Row
                $summaryRowCount = count($summaryRows);
                $summaryLastRow = $summaryHeaderRow + $summaryRowCount - 1;

                // Style Summary Header
                $this->styleHeader($sheet, "A{$summaryHeaderRow}:{$summaryLastColumn}{$summaryHeaderRow}");
                $sheet->getRowDimension($summaryHeaderRow)->setRowHeight(25);

                // Style Summary Data Rows
                $summaryDataStart = $summaryHeaderRow + 1;
                $summaryDataEnd = $summaryLastRow - 1;

                if ($summaryDataEnd >= $summaryDataStart) {
                    for ($r = $summaryDataStart; $r <= $summaryDataEnd; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(20);
                    }

                    // Format numbers (Cols B..Last)
                    $sheet->getStyle("B{$summaryDataStart}:{$summaryLastColumn}{$summaryLastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0;[Red]\(#,##0\);"-"');

                    // Center column A (MONTH)
                    $sheet->getStyle("A{$summaryDataStart}:A{$summaryDataEnd}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Right-align numbers
                    $sheet->getStyle("B{$summaryDataStart}:{$summaryLastColumn}{$summaryLastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Hair borders on summary table data
                    $sheet->getStyle("A{$summaryHeaderRow}:{$summaryLastColumn}{$summaryLastRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_HAIR);
                }

                // Style Summary Grand Total
                $this->styleGrandTotal($sheet, "A{$summaryLastRow}:{$summaryLastColumn}{$summaryLastRow}");
                $sheet->getRowDimension($summaryLastRow)->setRowHeight(22);

                // ==========================================
                // 2. SPACING ROWS (BREATHING ROOM ANTAR TABEL)
                // ==========================================
                $spacingStartRow = $summaryLastRow + 1;
                $spacingEndRow = $summaryLastRow + $spacingRowsCount;

                for ($r = $spacingStartRow; $r <= $spacingEndRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(18);
                    $sheet->getStyle("A{$r}:{$summaryLastColumn}{$r}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_NONE);
                }

                // ==========================================
                // 3. TABLE 2: DETAIL OVK PER LOKASI, BULAN, DAN REKENING
                // ==========================================
                $detailTitleRow = $spacingEndRow + 1;
                $sheet->setCellValue("A{$detailTitleRow}", $detailTitle);
                $sheet->mergeCells("A{$detailTitleRow}:{$detailLastColumn}{$detailTitleRow}");
                $this->styleSectionTitle($sheet, "A{$detailTitleRow}:{$detailLastColumn}{$detailTitleRow}", '4472C4');
                $sheet->getRowDimension($detailTitleRow)->setRowHeight(26);

                $detailHeaderRow = $detailTitleRow + 1;
                $sheet->fromArray($detailRows, null, "A{$detailHeaderRow}");

                $detailRowCount = count($detailRows);
                $detailLastRow = $detailHeaderRow + $detailRowCount - 1;

                // Style Detail Header
                $this->styleHeader($sheet, "A{$detailHeaderRow}:{$detailLastColumn}{$detailHeaderRow}");
                $sheet->getRowDimension($detailHeaderRow)->setRowHeight(25);

                $detailDataStart = $detailHeaderRow + 1;
                $detailDataEnd = $detailLastRow - 1;

                if ($detailDataEnd >= $detailDataStart) {
                    for ($r = $detailDataStart; $r <= $detailDataEnd; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(20);
                    }

                    // Format numbers
                    $sheet->getStyle("B{$detailDataStart}:B{$detailDataEnd}")
                        ->getNumberFormat()
                        ->setFormatCode('0');

                    $sheet->getStyle("E{$detailDataStart}:E{$detailLastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0;[Red]\(#,##0\);"-"');

                    // Alignments
                    $sheet->getStyle("A{$detailDataStart}:A{$detailDataEnd}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("B{$detailDataStart}:B{$detailDataEnd}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("C{$detailDataStart}:C{$detailDataEnd}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("D{$detailDataStart}:D{$detailDataEnd}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("E{$detailDataStart}:E{$detailLastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Hair borders on detail table data
                    $sheet->getStyle("A{$detailHeaderRow}:{$detailLastColumn}{$detailLastRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_HAIR);

                    // AutoFilter on detail data (excluding Grand Total)
                    $sheet->setAutoFilter("A{$detailHeaderRow}:{$detailLastColumn}{$detailDataEnd}");
                }

                // Style Detail Grand Total
                $this->styleGrandTotal($sheet, "A{$detailLastRow}:{$detailLastColumn}{$detailLastRow}");
                $sheet->getRowDimension($detailLastRow)->setRowHeight(22);

                // ==========================================
                // 4. PROFESSIONAL COLUMN DIMENSIONS
                // ==========================================
                $sheet->getColumnDimension('A')->setWidth(28);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(44);
                $sheet->getColumnDimension('E')->setWidth(24);

                for ($i = 6; $i <= $summaryColCount; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setWidth(22);
                }
            },
        ];
    }

    private function styleSectionTitle($sheet, string $range, string $bgColor = '2F5597'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $bgColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E2F3'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '8EA9DB'],
                ],
            ],
        ]);
    }

    private function styleGrandTotal($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9EEF4'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '8EA9DB'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '1F4E79'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
}
