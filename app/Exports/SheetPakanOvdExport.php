<?php

namespace App\Exports;

use App\Http\Controllers\Api\Finance\SheetPakanOvdPivotController;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetPakanOvdExport implements FromArray, WithEvents
{
    private const NUM_FORMAT_CURRENCY = '#,##0;[Red]\(#,##0\);"-"';
    private const COLOR_NAVY_TITLE = '2F5597';
    private const COLOR_HEADER_BG = 'D9E2F3';
    private const COLOR_YELLOW_BG = 'FFFF00';
    private const COLOR_GRAND_TOTAL_BG = 'E9EEF4';
    private const COLOR_BORDER_BLUE = '8EA9DB';

    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Data is constructed manually inside AfterSheet to enable multi-level merged headers and pixel-perfect formatting.
     */
    public function array(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setTitle('PAKAN.OVD');
                $sheet->setShowGridLines(true);

                $currentRow = 1;

                // =========================================================================
                // 1. PIVOT 1: MUTASI BULANAN PER AKUN
                // =========================================================================
                $currentRow = $this->renderPivot1($sheet, $currentRow);

                // Breathing room spacing rows
                $currentRow += 3;

                // =========================================================================
                // 2. PIVOT 2: MUTASI PER BULAN, AKUN, DAN LOKASI FARM
                // =========================================================================
                $currentRow = $this->renderPivot2($sheet, $currentRow);

                // Breathing room spacing rows
                $currentRow += 3;

                // =========================================================================
                // 3. PIVOT 3: AKUN ANALITIK PER BULAN DAN TIPE INTERNAL
                // =========================================================================
                $currentRow = $this->renderPivot3($sheet, $currentRow);

                // Breathing room spacing rows
                $currentRow += 3;

                // =========================================================================
                // 4. PIVOT 4: REKAP PEMBELIAN PER REKANAN / PARTNER BULANAN
                // =========================================================================
                $currentRow = $this->renderPivot4($sheet, $currentRow);

                // Default column dimension adjustments
                $this->adjustColumnDimensions($sheet);
            },
        ];
    }

    /**
     * Render Pivot 1: Mutasi Bulanan per Akun (S1:AD17 in Excel).
     */
    private function renderPivot1(Worksheet $sheet, int $startRow): int
    {
        $pivot = $this->payload['pivot1'] ?? null;
        if (!$pivot) {
            return $startRow;
        }

        $coaAccounts = SheetPakanOvdPivotController::COA_ACCOUNTS;
        $coaCount = count($coaAccounts);
        $totalCols = ($coaCount * 2) + 4; // 1 Month + 4 Pemakaian + 1 Total + 4 Pembelian + 1 Total + 1 Grand = 12
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        // --- Section Title ---
        $title = $pivot['title'] ?? 'PIVOT 1 - MUTASI BULANAN PER AKUN';
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $this->styleSectionTitle($sheet, "A{$startRow}:{$lastCol}{$startRow}", self::COLOR_NAVY_TITLE);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        // --- Header Rows (3 Levels) ---
        $h1Row = $startRow + 1;
        $h2Row = $startRow + 2;
        $h3Row = $startRow + 3;

        // Level 1: Merges
        $sheet->mergeCells("A{$h1Row}:A{$h3Row}");
        $sheet->setCellValue("A{$h1Row}", 'BULAN');

        // PEMAKAIAN (B..E)
        $p1Start = Coordinate::stringFromColumnIndex(2);
        $p1End = Coordinate::stringFromColumnIndex(1 + $coaCount);
        $sheet->mergeCells("{$p1Start}{$h1Row}:{$p1End}{$h1Row}");
        $sheet->setCellValue("{$p1Start}{$h1Row}", 'PEMAKAIAN');

        // PEMAKAIAN Total
        $pemakaianTotalCol = Coordinate::stringFromColumnIndex(2 + $coaCount);
        $sheet->mergeCells("{$pemakaianTotalCol}{$h1Row}:{$pemakaianTotalCol}{$h3Row}");
        $sheet->setCellValue("{$pemakaianTotalCol}{$h1Row}", 'PEMAKAIAN Total');

        // PEMBELIAN (G..J)
        $p2Start = Coordinate::stringFromColumnIndex(3 + $coaCount);
        $p2End = Coordinate::stringFromColumnIndex(2 + ($coaCount * 2));
        $sheet->mergeCells("{$p2Start}{$h1Row}:{$p2End}{$h1Row}");
        $sheet->setCellValue("{$p2Start}{$h1Row}", 'PEMBELIAN');

        // PEMBELIAN Total
        $pembelianTotalCol = Coordinate::stringFromColumnIndex(3 + ($coaCount * 2));
        $sheet->mergeCells("{$pembelianTotalCol}{$h1Row}:{$pembelianTotalCol}{$h3Row}");
        $sheet->setCellValue("{$pembelianTotalCol}{$h1Row}", 'PEMBELIAN Total');

        // Grand Total
        $grandTotalCol = Coordinate::stringFromColumnIndex(4 + ($coaCount * 2));
        $sheet->mergeCells("{$grandTotalCol}{$h1Row}:{$grandTotalCol}{$h3Row}");
        $sheet->setCellValue("{$grandTotalCol}{$h1Row}", 'Grand Total');

        // Level 2 & 3: COA Codes & COA Names
        $colIdx = 2;
        foreach ($coaAccounts as $code => $name) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetter}{$h2Row}", $code);
            $sheet->setCellValue("{$colLetter}{$h3Row}", $name);
            $colIdx++;
        }
        $colIdx++; // skip pemakaian total col
        foreach ($coaAccounts as $code => $name) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetter}{$h2Row}", $code);
            $sheet->setCellValue("{$colLetter}{$h3Row}", $name);
            $colIdx++;
        }

        // Style Headers
        $this->styleHeader($sheet, "A{$h1Row}:{$lastCol}{$h3Row}");
        $sheet->getRowDimension($h1Row)->setRowHeight(22);
        $sheet->getRowDimension($h2Row)->setRowHeight(20);
        $sheet->getRowDimension($h3Row)->setRowHeight(26);

        // --- Data Rows ---
        $dataRows = $pivot['data_rows'] ?? [];
        $currentRow = $h3Row + 1;

        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$currentRow}");
            $sheet->getRowDimension($currentRow)->setRowHeight(20);

            // Month center align
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number formatting for value columns
            $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")
                ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
            $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $currentRow++;
        }

        // --- Grand Total Row ---
        $grandTotal = $pivot['grand_total'] ?? [];
        $sheet->fromArray($grandTotal, null, "A{$currentRow}");
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $this->styleGrandTotal($sheet, "A{$currentRow}:{$lastCol}{$currentRow}");

        $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")
            ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
        $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Hair borders for table
        $sheet->getStyle("A{$h1Row}:{$lastCol}{$currentRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        return $currentRow;
    }

    /**
     * Render Pivot 2: Mutasi per Bulan, Akun, dan Lokasi Farm (S21:AL59 in Excel).
     */
    private function renderPivot2(Worksheet $sheet, int $startRow): int
    {
        $pivot = $this->payload['pivot2'] ?? null;
        if (!$pivot) {
            return $startRow;
        }

        $locations = $pivot['locations'] ?? [];
        $locCount = count($locations);
        $totalCols = 3 + ($locCount * 2) + 3; // Month, Code, Name + Pemakaian Locs + Pem Total + Pemb Locs + Pemb Total + Grand Total
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        // --- Section Title ---
        $title = $pivot['title'] ?? 'PIVOT 2 - MUTASI PER BULAN, AKUN, DAN LOKASI FARM';
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $this->styleSectionTitle($sheet, "A{$startRow}:{$lastCol}{$startRow}", self::COLOR_NAVY_TITLE);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        // --- Header Rows (2 Levels) ---
        $h1Row = $startRow + 1;
        $h2Row = $startRow + 2;

        // Frozen identifiers
        $sheet->mergeCells("A{$h1Row}:A{$h2Row}");
        $sheet->setCellValue("A{$h1Row}", 'MONTH');
        $sheet->mergeCells("B{$h1Row}:B{$h2Row}");
        $sheet->setCellValue("B{$h1Row}", 'Rekening/Kode');
        $sheet->mergeCells("C{$h1Row}:C{$h2Row}");
        $sheet->setCellValue("C{$h1Row}", 'Rekening/Nama');

        // PEMAKAIAN block
        $p1Start = Coordinate::stringFromColumnIndex(4);
        $p1End = Coordinate::stringFromColumnIndex(3 + $locCount);
        $sheet->mergeCells("{$p1Start}{$h1Row}:{$p1End}{$h1Row}");
        $sheet->setCellValue("{$p1Start}{$h1Row}", 'PEMAKAIAN');

        // PEMAKAIAN Total
        $pemakaianTotalCol = Coordinate::stringFromColumnIndex(4 + $locCount);
        $sheet->mergeCells("{$pemakaianTotalCol}{$h1Row}:{$pemakaianTotalCol}{$h2Row}");
        $sheet->setCellValue("{$pemakaianTotalCol}{$h1Row}", 'PEMAKAIAN Total');

        // PEMBELIAN block
        $p2Start = Coordinate::stringFromColumnIndex(5 + $locCount);
        $p2End = Coordinate::stringFromColumnIndex(4 + ($locCount * 2));
        $sheet->mergeCells("{$p2Start}{$h1Row}:{$p2End}{$h1Row}");
        $sheet->setCellValue("{$p2Start}{$h1Row}", 'PEMBELIAN');

        // PEMBELIAN Total
        $pembelianTotalCol = Coordinate::stringFromColumnIndex(5 + ($locCount * 2));
        $sheet->mergeCells("{$pembelianTotalCol}{$h1Row}:{$pembelianTotalCol}{$h2Row}");
        $sheet->setCellValue("{$pembelianTotalCol}{$h1Row}", 'PEMBELIAN Total');

        // Grand Total
        $grandTotalCol = Coordinate::stringFromColumnIndex(6 + ($locCount * 2));
        $sheet->mergeCells("{$grandTotalCol}{$h1Row}:{$grandTotalCol}{$h2Row}");
        $sheet->setCellValue("{$grandTotalCol}{$h1Row}", 'Grand Total');

        // Header Level 2: Locations
        $colIdx = 4;
        foreach ($locations as $loc) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx) . $h2Row, $loc);
            $colIdx++;
        }
        $colIdx++; // skip pemakaian total
        foreach ($locations as $loc) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx) . $h2Row, $loc);
            $colIdx++;
        }

        // Style Headers
        $this->styleHeader($sheet, "A{$h1Row}:{$lastCol}{$h2Row}");
        $sheet->getRowDimension($h1Row)->setRowHeight(22);
        $sheet->getRowDimension($h2Row)->setRowHeight(25);

        // --- Data Rows ---
        $dataRows = $pivot['data_rows'] ?? [];
        $currentRow = $h2Row + 1;

        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$currentRow}");
            $sheet->getRowDimension($currentRow)->setRowHeight(20);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
                ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
            $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $currentRow++;
        }

        // --- Grand Total Row ---
        $grandTotal = $pivot['grand_total'] ?? [];
        $sheet->fromArray($grandTotal, null, "A{$currentRow}");
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $this->styleGrandTotal($sheet, "A{$currentRow}:{$lastCol}{$currentRow}");

        $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
            ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
        $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A{$h1Row}:{$lastCol}{$currentRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        return $currentRow;
    }

    /**
     * Render Pivot 3: Akun Analitik per Bulan dan Tipe Internal (AS21:AZ253 in Excel).
     */
    private function renderPivot3(Worksheet $sheet, int $startRow): int
    {
        $pivot = $this->payload['pivot3'] ?? null;
        if (!$pivot) {
            return $startRow;
        }

        $headers = $pivot['headers'] ?? [];
        $totalCols = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        // --- Section Title ---
        $title = $pivot['title'] ?? 'PIVOT 3 - AKUN ANALITIK PER BULAN DAN TIPE INTERNAL';
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $this->styleSectionTitle($sheet, "A{$startRow}:{$lastCol}{$startRow}", self::COLOR_NAVY_TITLE);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        // --- Header Row ---
        $hRow = $startRow + 1;
        $sheet->fromArray($headers, null, "A{$hRow}");
        $this->styleHeader($sheet, "A{$hRow}:{$lastCol}{$hRow}");
        $sheet->getRowDimension($hRow)->setRowHeight(25);

        // --- Data Rows ---
        $dataRows = $pivot['data_rows'] ?? [];
        $currentRow = $hRow + 1;

        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$currentRow}");
            $sheet->getRowDimension($currentRow)->setRowHeight(20);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("E{$currentRow}:{$lastCol}{$currentRow}")
                ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
            $sheet->getStyle("E{$currentRow}:{$lastCol}{$currentRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $currentRow++;
        }

        // --- Grand Total Row ---
        $grandTotal = $pivot['grand_total'] ?? [];
        $sheet->fromArray($grandTotal, null, "A{$currentRow}");
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $this->styleGrandTotal($sheet, "A{$currentRow}:{$lastCol}{$currentRow}");

        $sheet->getStyle("E{$currentRow}:{$lastCol}{$currentRow}")
            ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
        $sheet->getStyle("E{$currentRow}:{$lastCol}{$currentRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A{$hRow}:{$lastCol}{$currentRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        return $currentRow;
    }

    /**
     * Render Pivot 4: Rekap Pembelian per Rekanan / Partner Bulanan (BB1:BP57 in Excel).
     */
    private function renderPivot4(Worksheet $sheet, int $startRow): int
    {
        $pivot = $this->payload['pivot4'] ?? null;
        if (!$pivot) {
            return $startRow;
        }

        $headers = $pivot['headers'] ?? [];
        $totalCols = count($headers); // 3 + 12 + 1 = 16
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        // --- Section Title ---
        $title = $pivot['title'] ?? 'PIVOT 4 - REKAP PEMBELIAN PER REKANAN / PARTNER BULANAN';
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $this->styleSectionTitle($sheet, "A{$startRow}:{$lastCol}{$startRow}", self::COLOR_NAVY_TITLE);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        // --- Control Check Rows (Rows 1-3 from Excel) ---
        $checkRows = $pivot['check_rows'] ?? [];
        $rekapValues = array_values($checkRows['rekap_pembelian'] ?? array_fill(1, 12, 0.0));
        $p1Values = array_values($checkRows['pembelian_pivot1'] ?? array_fill(1, 12, 0.0));
        $selisihValues = array_values($checkRows['selisih'] ?? array_fill(1, 12, 0.0));

        $checkRow1 = $startRow + 1;
        $checkRow2 = $startRow + 2;
        $checkRow3 = $startRow + 3;

        // Label Column (A:C merged)
        $sheet->mergeCells("A{$checkRow1}:C{$checkRow1}");
        $sheet->setCellValue("A{$checkRow1}", 'Rekap Pembelian (Total) per Bulan');
        $sheet->mergeCells("A{$checkRow2}:C{$checkRow2}");
        $sheet->setCellValue("A{$checkRow2}", 'PEMBELIAN Total (Pivot 1)');
        $sheet->mergeCells("A{$checkRow3}:C{$checkRow3}");
        $sheet->setCellValue("A{$checkRow3}", 'Selisih (Difference Check)');

        // Values
        $sheet->fromArray(array_merge($rekapValues, [array_sum($rekapValues)]), null, "D{$checkRow1}");
        $sheet->fromArray(array_merge($p1Values, [array_sum($p1Values)]), null, "D{$checkRow2}");
        $sheet->fromArray(array_merge($selisihValues, [array_sum($selisihValues)]), null, "D{$checkRow3}");

        for ($r = $checkRow1; $r <= $checkRow3; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(20);
            $sheet->getStyle("A{$r}:C{$r}")->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle("A{$r}:C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$r}:{$lastCol}{$r}")->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
            $sheet->getStyle("D{$r}:{$lastCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("A{$checkRow3}:{$lastCol}{$checkRow3}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_HAIR],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // --- Yellow Header Block (Exact Excel Style) ---
        $h1Row = $startRow + 4;
        $h2Row = $startRow + 5;

        // Frozen identifiers merged over h1Row:h2Row
        $sheet->mergeCells("C{$h1Row}:C{$h2Row}");
        $sheet->setCellValue("C{$h1Row}", 'Rekanan/Nama');

        // "BULAN" merged over columns D..O (months 1-12)
        $monthStartCol = 'D';
        $monthEndCol = Coordinate::stringFromColumnIndex(15);
        $sheet->mergeCells("{$monthStartCol}{$h1Row}:{$monthEndCol}{$h1Row}");
        $sheet->setCellValue("{$monthStartCol}{$h1Row}", 'BULAN');

        // Grand Total merged over last column
        $sheet->mergeCells("{$lastCol}{$h1Row}:{$lastCol}{$h2Row}");
        $sheet->setCellValue("{$lastCol}{$h1Row}", 'Grand Total');

        // Header Level 2: month numbers 1..12 in row h2
        for ($m = 1; $m <= 12; $m++) {
            $col = Coordinate::stringFromColumnIndex(3 + $m);
            $sheet->setCellValue("{$col}{$h2Row}", $m);
        }

        // Style Yellow Header
        $sheet->getStyle("A{$h1Row}:{$lastCol}{$h2Row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_YELLOW_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'B7B7B7'],
                ],
            ],
        ]);
        $sheet->getRowDimension($h1Row)->setRowHeight(22);
        $sheet->getRowDimension($h2Row)->setRowHeight(25);

        // --- Data Rows ---
        $dataRows = $pivot['data_rows'] ?? [];
        $currentRow = $h2Row + 1;

        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$currentRow}");
            $sheet->getRowDimension($currentRow)->setRowHeight(20);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
                ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
            $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $currentRow++;
        }

        // --- Grand Total Row ---
        $grandTotal = $pivot['grand_total'] ?? [];
        $sheet->fromArray($grandTotal, null, "A{$currentRow}");
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $this->styleGrandTotal($sheet, "A{$currentRow}:{$lastCol}{$currentRow}");

        $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
            ->getNumberFormat()->setFormatCode(self::NUM_FORMAT_CURRENCY);
        $sheet->getStyle("D{$currentRow}:{$lastCol}{$currentRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A{$h1Row}:{$lastCol}{$currentRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        return $currentRow;
    }

    /**
     * Apply section title banner styling.
     */
    private function styleSectionTitle(Worksheet $sheet, string $range, string $bgColor = '2F5597'): void
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

    /**
     * Apply header styling.
     */
    private function styleHeader(Worksheet $sheet, string $range): void
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
                'startColor' => ['rgb' => self::COLOR_HEADER_BG],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::COLOR_BORDER_BLUE],
                ],
            ],
        ]);
    }

    /**
     * Apply grand total row styling.
     */
    private function styleGrandTotal(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_GRAND_TOTAL_BG],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::COLOR_BORDER_BLUE],
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

    /**
     * Set explicit, elegant column dimensions to guarantee clean rendering without `###`.
     */
    private function adjustColumnDimensions(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(24);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->getColumnDimension('H')->setWidth(22);
        $sheet->getColumnDimension('I')->setWidth(22);
        $sheet->getColumnDimension('J')->setWidth(24);
        $sheet->getColumnDimension('K')->setWidth(24);
        $sheet->getColumnDimension('L')->setWidth(26);

        // Extended columns for wide tables (Pivot 2 & 4)
        for ($i = 13; $i <= 35; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(22);
        }
    }
}
