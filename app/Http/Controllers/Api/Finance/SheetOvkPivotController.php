<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exports\SheetOvkExport;
use App\Http\Controllers\Controller;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class SheetOvkPivotController extends Controller
{
    private array $preferredLocations = [
        'Farm Cariu',
        'Farm Cimangkok',
        'Farm Cimayasari',
        'Farm Gekbrong',
        'Farm Talun',
        'Farm Tenjo Kalijati',
        'Gd. Lab Bogor',
        'Gunung Maju',
        'Hatch Cimangkok',
    ];

    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
            'month_from' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
            'month_to' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
        ]);

        $validated['year'] = (int) (
            $validated['year'] ?? now()->year
        );

        $monthFrom = (int) (
            $validated['month_from'] ?? 1
        );

        $monthTo = (int) (
            $validated['month_to'] ?? 12
        );

        if ($monthFrom > $monthTo) {
            abort(
                422,
                'month_from tidak boleh lebih besar dari month_to.'
            );
        }

        return [
            $validated,
            $monthFrom,
            $monthTo,
        ];
    }

    public function export(Request $request)
    {
        [$validated, $monthFrom, $monthTo] =
            $this->validateFilters($request);

        try {
            $payload = $this->buildPivot(
                $validated,
                $monthFrom,
                $monthTo
            );

            $filename = sprintf(
                'SheetOvkPivot_%d_%02d-%02d.xlsx',
                $validated['year'],
                $monthFrom,
                $monthTo
            );

            return Excel::download(
                new SheetOvkExport($payload),
                $filename
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor laporan OVK.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        [$validated, $monthFrom, $monthTo] =
            $this->validateFilters($request);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildPivot(
                    $validated,
                    $monthFrom,
                    $monthTo
                ),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menampilkan preview OVK.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    private function buildPivot(
        array $validated,
        int $monthFrom,
        int $monthTo
    ): array {
        $year = (int) $validated['year'];

        $locationExpression = "
            COALESCE(
                NULLIF(TRIM(location), ''),
                '(blank)'
            )
        ";

        $baseQuery = GeneralLedgerEntry::query()
            ->where('source_sheet', 'OVK')
            ->whereBetween(
                'month_number',
                [$monthFrom, $monthTo]
            )
            ->whereYear(
                'transaction_date',
                $year
            );

        $summaryQuery = clone $baseQuery;

        $summaryRecords = $summaryQuery
            ->select('month_number')
            ->selectRaw(
                "{$locationExpression} AS location_name"
            )
            ->selectRaw(
                'SUM(COALESCE(mutasi, 0)) AS total_mutasi'
            )
            ->groupBy('month_number')
            ->groupByRaw($locationExpression)
            ->orderBy('month_number')
            ->get();

        $actualLocations = $summaryRecords
            ->pluck('location_name')
            ->map(
                fn ($location) => trim((string) $location)
            )
            ->unique()
            ->values()
            ->all();

        $locations = array_values(
            array_filter(
                $this->preferredLocations,
                fn ($location) => in_array(
                    $location,
                    $actualLocations,
                    true
                )
            )
        );

        $otherLocations = array_values(
            array_diff(
                $actualLocations,
                $locations
            )
        );

        sort(
            $otherLocations,
            SORT_NATURAL | SORT_FLAG_CASE
        );

        $locations = array_merge(
            $locations,
            $otherLocations
        );

        $summaryPivot = [];

        foreach ($summaryRecords as $record) {
            $month = (int) $record->month_number;

            $location = (string) $record->location_name;

            $summaryPivot[$month][$location] =
                (float) $record->total_mutasi;
        }

        $summaryRows = [
            array_merge(
                ['MONTH'],
                $locations,
                ['Grand Total']
            ),
        ];

        $grandTotalByLocation = array_fill_keys(
            $locations,
            0.0
        );

        $grandTotalAll = 0.0;

        for (
            $month = $monthFrom;
            $month <= $monthTo;
            $month++
        ) {
            $row = [$month];

            $monthTotal = 0.0;

            foreach ($locations as $location) {
                $value = (float) (
                    $summaryPivot[$month][$location] ?? 0
                );

                $row[] = $value;

                $monthTotal += $value;

                $grandTotalByLocation[$location] += $value;
            }

            $row[] = $monthTotal;

            $summaryRows[] = $row;

            $grandTotalAll += $monthTotal;
        }

        $summaryRows[] = array_merge(
            ['Grand Total'],
            array_values($grandTotalByLocation),
            [$grandTotalAll]
        );

        $detailQuery = clone $baseQuery;

        $detailRecords = $detailQuery
            ->selectRaw(
                "{$locationExpression} AS location_name"
            )
            ->addSelect([
                'month_number',
                'coa_code',
                'coa_name',
            ])
            ->selectRaw(
                'SUM(COALESCE(mutasi, 0)) AS total_mutasi'
            )
            ->groupByRaw($locationExpression)
            ->groupBy(
                'month_number',
                'coa_code',
                'coa_name'
            )
            ->orderBy('location_name')
            ->orderBy('month_number')
            ->orderBy('coa_code')
            ->get();

        $detailRows = [
            [
                'Akun Analitik/Akun Analitik',
                'MONTH',
                'Rekening/Kode',
                'Rekening/Nama',
                'Sum of MUTASI',
            ],
        ];

        $detailGrandTotal = 0.0;

        foreach ($detailRecords as $record) {
            $mutasi = (float) $record->total_mutasi;

            $detailRows[] = [
                (string) $record->location_name,
                (int) $record->month_number,
                $record->coa_code,
                $record->coa_name,
                $mutasi,
            ];

            $detailGrandTotal += $mutasi;
        }

        $detailRows[] = [
            'Grand Total',
            null,
            null,
            null,
            $detailGrandTotal,
        ];

        return [
            'source_sheet' => 'OVK',
            'year' => $year,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,

            'table_spacing_rows' => 3,

            'summary_title' => 'RINGKASAN OVK PER BULAN DAN LOKASI',
            'location_count' => count($locations),
            'locations' => $locations,

            'summary_rows' => $summaryRows,
            'summary_grand_total' => $grandTotalAll,

            'detail_title' => 'DETAIL OVK PER LOKASI, BULAN, DAN REKENING',
            'detail_rows' => $detailRows,
            'detail_count' => $detailRecords->count(),
            'detail_grand_total' => $detailGrandTotal,
        ];
    }
}
