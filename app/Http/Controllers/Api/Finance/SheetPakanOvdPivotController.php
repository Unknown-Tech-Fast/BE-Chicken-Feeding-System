<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exports\SheetPakanOvdExport;
use App\Http\Controllers\Controller;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SheetPakanOvdPivotController extends Controller
{
    public const SOURCE_SHEET = 'PAKAN.OVD';

    public const COA_ACCOUNTS = [
        '1-130-3' => 'Persediaan Pakan',
        '1-130-4' => 'Persediaan Disinfectant',
        '1-130-5' => 'Persediaan Vaccine',
        '1-130-6' => 'Persediaan Medicines & Vitamin',
    ];

    private array $preferredLocations = [
        'Farm Cariu',
        'Farm Cimangkok',
        'Farm Cimayasari',
        'Farm Gekbrong',
        'Farm Talun',
        'Farm Tenjo Kalijati',
        'Gunung Maju',
    ];

    /**
     * Validate filter inputs (year, month_from, month_to, import_id).
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month_from' => ['nullable', 'integer', 'between:1,12'],
            'month_to' => ['nullable', 'integer', 'between:1,12'],
            'import_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $monthFrom = (int) ($validated['month_from'] ?? 1);
        $monthTo = (int) ($validated['month_to'] ?? 12);
        $importId = isset($validated['import_id']) ? (int) $validated['import_id'] : null;

        if ($monthFrom > $monthTo) {
            abort(422, 'month_from tidak boleh lebih besar dari month_to.');
        }

        return [$year, $monthFrom, $monthTo, $importId];
    }

    /**
     * Normalizes location names to handle casing or whitespace inconsistencies (e.g. "Farm CImangkok").
     */
    private function normalizeLocation(?string $location): string
    {
        $loc = trim((string) $location);
        if ($loc === '') {
            return '(blank)';
        }

        foreach ($this->preferredLocations as $preferred) {
            if (strcasecmp($loc, $preferred) === 0) {
                return $preferred;
            }
        }

        return $loc;
    }

    /**
     * Build base query for GeneralLedgerEntry with filters.
     */
    private function baseQuery(?int $year, int $monthFrom, int $monthTo, ?int $importId)
    {
        $query = GeneralLedgerEntry::query()
            ->where('source_sheet', self::SOURCE_SHEET)
            ->whereBetween('month_number', [$monthFrom, $monthTo]);

        if ($year !== null) {
            $query->whereYear('transaction_date', $year);
        }

        if ($importId !== null) {
            $query->where('import_id', $importId);
        }

        return $query;
    }

    /**
     * Preview pivot data in JSON.
     */
    public function preview(Request $request): JsonResponse
    {
        [$year, $monthFrom, $monthTo, $importId] = $this->validateFilters($request);

        try {
            $payload = $this->buildPivot($year, $monthFrom, $monthTo, $importId);

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menampilkan preview pivot PAKAN.OVD.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Export pivot data to Excel.
     */
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        [$year, $monthFrom, $monthTo, $importId] = $this->validateFilters($request);

        try {
            $payload = $this->buildPivot($year, $monthFrom, $monthTo, $importId);
            $yearLabel = $year ?? 'all-years';
            $filename = sprintf(
                'SheetPakanOvdPivot_%s_%02d-%02d.xlsx',
                $yearLabel,
                $monthFrom,
                $monthTo
            );

            return Excel::download(new SheetPakanOvdExport($payload), $filename);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor pivot PAKAN.OVD.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Build all 4 pivot tables mirroring sheet PAKAN.OVD in Excel.
     */
    private function buildPivot(?int $year, int $monthFrom, int $monthTo, ?int $importId): array
    {
        $base = $this->baseQuery($year, $monthFrom, $monthTo, $importId);

        // =========================================================================
        // PIVOT 1: MUTASI BULANAN PER AKUN (Month x Internal Type x COA)
        // Corresponds to range S1:AD17 in Excel sheet PAKAN.OVD
        // =========================================================================
        $pivot1Records = (clone $base)
            ->select('month_number', 'internal_type', 'coa_code')
            ->selectRaw('SUM(COALESCE(mutasi, 0)) AS total_mutasi')
            ->whereIn('coa_code', array_keys(self::COA_ACCOUNTS))
            ->groupBy('month_number', 'internal_type', 'coa_code')
            ->orderBy('month_number')
            ->get();

        $pivot1Map = [];
        foreach ($pivot1Records as $record) {
            $month = (int) $record->month_number;
            $type = strtoupper(trim((string) $record->internal_type));
            $coa = (string) $record->coa_code;
            $pivot1Map[$month][$type][$coa] = (float) $record->total_mutasi;
        }

        $coaKeys = array_keys(self::COA_ACCOUNTS);
        $coaCount = count($coaKeys);

        // Header level 1, 2, 3 mirroring Excel
        $pivot1Header1 = array_merge(
            [''],
            array_fill(0, $coaCount, 'PEMAKAIAN'),
            ['PEMAKAIAN Total'],
            array_fill(0, $coaCount, 'PEMBELIAN'),
            ['PEMBELIAN Total', 'Grand Total']
        );

        $pivot1Header2 = array_merge(
            [''],
            $coaKeys,
            [''],
            $coaKeys,
            ['', '']
        );

        $pivot1Header3 = array_merge(
            ['BULAN'],
            array_values(self::COA_ACCOUNTS),
            ['PEMAKAIAN Total'],
            array_values(self::COA_ACCOUNTS),
            ['PEMBELIAN Total', 'Grand Total']
        );

        $pivot1RowsFlat = [[
            'Row Labels',
            'PEMAKAIAN - Persediaan Pakan',
            'PEMAKAIAN - Persediaan Disinfectant',
            'PEMAKAIAN - Persediaan Vaccine',
            'PEMAKAIAN - Persediaan Medicines & Vitamin',
            'PEMAKAIAN Total',
            'PEMBELIAN - Persediaan Pakan',
            'PEMBELIAN - Persediaan Disinfectant',
            'PEMBELIAN - Persediaan Vaccine',
            'PEMBELIAN - Persediaan Medicines & Vitamin',
            'PEMBELIAN Total',
            'Grand Total',
        ]];

        $pivot1DataRows = [];
        $pivot1Totals = array_fill(0, ($coaCount * 2) + 3, 0.0);
        $pivot1MonthlyPembelian = []; // For cross-check with Pivot 4

        for ($month = $monthFrom; $month <= $monthTo; $month++) {
            $usage = [];
            $purchase = [];

            foreach ($coaKeys as $coa) {
                $usage[] = (float) ($pivot1Map[$month]['PEMAKAIAN'][$coa] ?? 0.0);
                $purchase[] = (float) ($pivot1Map[$month]['PEMBELIAN'][$coa] ?? 0.0);
            }

            $usageTotal = round(array_sum($usage), 2);
            $purchaseTotal = round(array_sum($purchase), 2);
            $netTotal = round($usageTotal + $purchaseTotal, 2);

            $pivot1MonthlyPembelian[$month] = $purchaseTotal;

            $row = array_merge([$month], $usage, [$usageTotal], $purchase, [$purchaseTotal, $netTotal]);
            $pivot1DataRows[] = $row;
            $pivot1RowsFlat[] = $row;

            for ($i = 1; $i < count($row); $i++) {
                $pivot1Totals[$i - 1] += (float) $row[$i];
            }
        }

        $pivot1Totals = array_map(fn ($v) => round((float) $v, 2), $pivot1Totals);

        $pivot1GrandRow = array_merge(['Grand Total'], $pivot1Totals);
        $pivot1RowsFlat[] = $pivot1GrandRow;

        // =========================================================================
        // RESOLVE LOCATIONS (Preferred order first, then alphabetical)
        // =========================================================================
        $allLocationRaw = (clone $base)
            ->selectRaw("COALESCE(NULLIF(TRIM(location), ''), '(blank)') AS location_name")
            ->distinct()
            ->pluck('location_name')
            ->map(fn ($v) => $this->normalizeLocation((string) $v))
            ->unique()
            ->values()
            ->all();

        $locations = array_values(array_filter(
            $this->preferredLocations,
            fn ($location) => in_array($location, $allLocationRaw, true)
        ));

        $otherLocations = array_values(array_diff($allLocationRaw, $locations));
        sort($otherLocations, SORT_NATURAL | SORT_FLAG_CASE);
        $locations = array_merge($locations, $otherLocations);

        // =========================================================================
        // PIVOT 2: MUTASI PER BULAN, AKUN, DAN LOKASI FARM
        // Corresponds to range S21:AL59 in Excel sheet PAKAN.OVD
        // =========================================================================
        $pivot2Records = (clone $base)
            ->select('month_number', 'coa_code', 'coa_name', 'internal_type', 'location')
            ->selectRaw('SUM(COALESCE(mutasi, 0)) AS total_mutasi')
            ->whereIn('internal_type', ['PEMAKAIAN', 'PEMBELIAN'])
            ->groupBy('month_number', 'coa_code', 'coa_name', 'internal_type', 'location')
            ->orderBy('month_number')
            ->orderBy('coa_code')
            ->get();

        $pivot2Map = [];
        $accountKeys = [];

        foreach ($pivot2Records as $record) {
            $month = (int) $record->month_number;
            $coa = (string) $record->coa_code;
            $name = (string) $record->coa_name;
            $loc = $this->normalizeLocation($record->location);
            $type = strtoupper(trim((string) $record->internal_type));

            $key = $month . '|' . $coa . '|' . $name;
            $accountKeys[$key] = [$month, $coa, $name];

            if (!isset($pivot2Map[$key][$type][$loc])) {
                $pivot2Map[$key][$type][$loc] = 0.0;
            }
            $pivot2Map[$key][$type][$loc] += (float) $record->total_mutasi;
        }

        $locCount = count($locations);

        $pivot2Header1 = array_merge(
            ['MONTH', 'Rekening/Kode'],
            array_fill(0, $locCount, 'PEMAKAIAN'),
            ['PEMAKAIAN Total'],
            array_fill(0, $locCount, 'PEMBELIAN'),
            ['PEMBELIAN Total', 'Grand Total']
        );

        $pivot2Header2 = array_merge(
            ['', '', ''],
            $locations,
            [''],
            $locations,
            ['', '']
        );

        $pivot2RowsFlat = [[
            'MONTH', 'Rekening/Kode',
            ...array_map(fn ($l) => 'PEMAKAIAN - ' . $l, $locations),
            'PEMAKAIAN Total',
            ...array_map(fn ($l) => 'PEMBELIAN - ' . $l, $locations),
            'PEMBELIAN Total',
            'Grand Total',
        ]];

        $pivot2DataRows = [];
        $pivot2Grand = array_fill(0, ($locCount * 2) + 3, 0.0);

        foreach ($accountKeys as $key => [$month, $coa, $name]) {
            $row = [$month, $coa, $name];
            $usageTotal = 0.0;

            foreach ($locations as $loc) {
                $v = (float) ($pivot2Map[$key]['PEMAKAIAN'][$loc] ?? 0.0);
                $row[] = $v;
                $usageTotal += $v;
            }
            $row[] = $usageTotal;

            $purchaseTotal = 0.0;
            foreach ($locations as $loc) {
                $v = (float) ($pivot2Map[$key]['PEMBELIAN'][$loc] ?? 0.0);
                $row[] = $v;
                $purchaseTotal += $v;
            }
            $row[] = $purchaseTotal;
            $row[] = $usageTotal + $purchaseTotal;

            $pivot2DataRows[] = $row;
            $pivot2RowsFlat[] = $row;

            for ($i = 3; $i < count($row); $i++) {
                $pivot2Grand[$i - 3] += (float) $row[$i];
            }
        }

        $pivot2GrandRow = array_merge(['Grand Total', '', ''], $pivot2Grand);
        $pivot2RowsFlat[] = $pivot2GrandRow;

        // =========================================================================
        // PIVOT 3: AKUN ANALITIK PER BULAN DAN TIPE INTERNAL
        // Corresponds to range AS21:AZ253 in Excel sheet PAKAN.OVD
        // =========================================================================
        $pivot3Records = (clone $base)
            ->select('coa_code', 'coa_name', 'month_number', 'internal_type', 'location')
            ->selectRaw('SUM(COALESCE(mutasi, 0)) AS total_mutasi')
            ->groupBy('coa_code', 'coa_name', 'month_number', 'internal_type', 'location')
            ->orderBy('coa_code')
            ->orderBy('location')
            ->orderBy('month_number')
            ->get();

        $pivot3Map = [];
        foreach ($pivot3Records as $record) {
            $loc = $this->normalizeLocation($record->location);
            $key = implode('|', [
                (string) $record->coa_code,
                (string) $record->coa_name,
                $loc,
                (int) $record->month_number,
            ]);

            $pivot3Map[$key]['coa_code'] = (string) $record->coa_code;
            $pivot3Map[$key]['coa_name'] = (string) $record->coa_name;
            $pivot3Map[$key]['location'] = $loc;
            $pivot3Map[$key]['month'] = (int) $record->month_number;

            $type = strtoupper(trim((string) $record->internal_type));
            if (!isset($pivot3Map[$key][$type])) {
                $pivot3Map[$key][$type] = 0.0;
            }
            $pivot3Map[$key][$type] += (float) $record->total_mutasi;
        }

        $pivot3Header = [
            'Rekening/Kode',
            'Rekening/Nama',
            'Akun Analitik/Akun Analitik',
            'C',
            '(blank)',
            'PEMAKAIAN',
            'PEMBELIAN',
            'Grand Total',
        ];

        $pivot3RowsFlat = [$pivot3Header];
        $pivot3DataRows = [];
        $pivot3Totals = array_fill(0, 4, 0.0);

        foreach ($pivot3Map as $record) {
            $blank = (float) ($record['(BLANK)'] ?? ($record[''] ?? 0.0));
            $usage = (float) ($record['PEMAKAIAN'] ?? 0.0);
            $purchase = (float) ($record['PEMBELIAN'] ?? 0.0);
            $total = $blank + $usage + $purchase;

            $row = [
                $record['coa_code'],
                $record['coa_name'],
                $record['location'],
                $record['month'],
                $blank,
                $usage,
                $purchase,
                $total,
            ];

            $pivot3DataRows[] = $row;
            $pivot3RowsFlat[] = $row;

            $pivot3Totals[0] += $blank;
            $pivot3Totals[1] += $usage;
            $pivot3Totals[2] += $purchase;
            $pivot3Totals[3] += $total;
        }

        $pivot3GrandRow = ['Grand Total', '', '', '', ...$pivot3Totals];
        $pivot3RowsFlat[] = $pivot3GrandRow;

        // =========================================================================
        // PIVOT 4: REKAP PEMBELIAN PER REKANAN / PARTNER BULANAN
        // Corresponds to range BB1:BP57 in Excel sheet PAKAN.OVD
        // =========================================================================
        $pivot4Records = (clone $base)
            ->select('partner_name', 'coa_code', 'month_number')
            ->selectRaw('SUM(COALESCE(mutasi, 0)) AS total_mutasi')
            ->whereNotNull('partner_name')
            ->where('partner_name', '!=', '')
            ->groupBy('partner_name', 'coa_code', 'month_number')
            ->orderBy('partner_name')
            ->get();

        $pivot4Map = [];
        foreach ($pivot4Records as $record) {
            $partner = trim((string) $record->partner_name);
            if ($partner === '') {
                continue;
            }

            // Determine category: "PAKAN" for 1-130-3, "OVD" for 1-130-4/5/6
            $category = ((string) $record->coa_code === '1-130-3') ? 'PAKAN' : 'OVD';
            $key = $partner . '|' . $category;

            $pivot4Map[$key]['partner_name'] = $partner;
            $pivot4Map[$key]['category'] = $category;

            $month = (int) $record->month_number;
            if (!isset($pivot4Map[$key]['months'][$month])) {
                $pivot4Map[$key]['months'][$month] = 0.0;
            }
            $pivot4Map[$key]['months'][$month] += (float) $record->total_mutasi;
        }

        // Sort partners alphabetically
        ksort($pivot4Map, SORT_NATURAL | SORT_FLAG_CASE);

        $monthsList = range(1, 12);
        $rekapPembelianPerBulan = array_fill_keys($monthsList, 0.0);

        $pivot4DataRows = [];
        $pivot4Grand = array_fill_keys($monthsList, 0.0);
        $pivot4GrandTotalAll = 0.0;

        foreach ($pivot4Map as $record) {
            $rowTotal = 0.0;
            $monthValues = [];

            foreach ($monthsList as $m) {
                $val = (float) ($record['months'][$m] ?? 0.0);
                $monthValues[] = $val;
                $rowTotal += $val;
                $pivot4Grand[$m] += $val;
                $rekapPembelianPerBulan[$m] += $val;
            }

            $pivot4GrandTotalAll += $rowTotal;

            $row = [
                $record['partner_name'],
                $record['category'],
                $record['partner_name'],
                ...$monthValues,
                $rowTotal,
            ];

            $pivot4DataRows[] = $row;
        }

        // Control Check Rows (Row 1-3 from Excel):
        // Row 1: Rekap Pembelian (Total) per Bulan
        // Row 2: PEMBELIAN Total dari Pivot 1
        // Row 3: Selisih (Difference)
        $pembelianPivot1PerBulan = [];
        $selisihPerBulan = [];
        foreach ($monthsList as $m) {
            $p1Val = (float) ($pivot1MonthlyPembelian[$m] ?? 0.0);
            $pembelianPivot1PerBulan[$m] = $p1Val;
            $selisihPerBulan[$m] = $p1Val - $rekapPembelianPerBulan[$m];
        }

        $pivot4RowsFlat = [
            array_merge(['Rekap Pembelian (Total)', '', ''], array_values($rekapPembelianPerBulan), [array_sum($rekapPembelianPerBulan)]),
            array_merge(['PEMBELIAN Total (Pivot 1)', '', ''], array_values($pembelianPivot1PerBulan), [array_sum($pembelianPivot1PerBulan)]),
            array_merge(['Selisih (Difference)', '', ''], array_values($selisihPerBulan), [array_sum($selisihPerBulan)]),
            ['Rekanan/Nama', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'Grand Total'],
            ...$pivot4DataRows,
            array_merge(['Grand Total', '', ''], array_values($pivot4Grand), [$pivot4GrandTotalAll]),
        ];

        return [
            'source_sheet' => self::SOURCE_SHEET,
            'year' => $year,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'import_id' => $importId,
            'table_spacing_rows' => 3,
            'locations' => $locations,

            // Pivot 1 Details
            'pivot1' => [
                'title' => 'PIVOT 1 - MUTASI BULANAN PER AKUN',
                'header_level1' => $pivot1Header1,
                'header_level2' => $pivot1Header2,
                'header_level3' => $pivot1Header3,
                'data_rows' => $pivot1DataRows,
                'grand_total' => $pivot1GrandRow,
                'totals' => [
                    'pemakaian_total' => (float) ($pivot1Totals[$coaCount] ?? 0.0),
                    'pembelian_total' => (float) ($pivot1Totals[$coaCount * 2 + 1] ?? 0.0),
                    'net_total' => (float) ($pivot1Totals[$coaCount * 2 + 2] ?? 0.0),
                ],
            ],

            // Pivot 2 Details
            'pivot2' => [
                'title' => 'PIVOT 2 - MUTASI PER BULAN, AKUN, DAN LOKASI FARM',
                'locations' => $locations,
                'header_level1' => $pivot2Header1,
                'header_level2' => $pivot2Header2,
                'data_rows' => $pivot2DataRows,
                'grand_total' => $pivot2GrandRow,
            ],

            // Pivot 3 Details
            'pivot3' => [
                'title' => 'PIVOT 3 - AKUN ANALITIK PER BULAN DAN TIPE INTERNAL',
                'headers' => $pivot3Header,
                'data_rows' => $pivot3DataRows,
                'grand_total' => $pivot3GrandRow,
            ],

            // Pivot 4 Details
            'pivot4' => [
                'title' => 'PIVOT 4 - REKAP PEMBELIAN PER REKANAN / PARTNER BULANAN',
                'check_rows' => [
                    'rekap_pembelian' => $rekapPembelianPerBulan,
                    'pembelian_pivot1' => $pembelianPivot1PerBulan,
                    'selisih' => $selisihPerBulan,
                ],
                'headers' => ['Rekanan/Nama', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'Grand Total'],
                'data_rows' => $pivot4DataRows,
                'grand_total' => array_merge(['Grand Total', '', ''], array_values($pivot4Grand), [$pivot4GrandTotalAll]),
            ],

            // Backward compatibility matrix arrays
            'pivot1_rows' => $pivot1RowsFlat,
            'pivot2_rows' => $pivot2RowsFlat,
            'pivot3_rows' => $pivot3RowsFlat,
            'pivot4_rows' => $pivot4RowsFlat,
        ];
    }
}
