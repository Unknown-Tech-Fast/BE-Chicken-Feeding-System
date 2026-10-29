<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exports\SheetAyamExport;
use App\Http\Controllers\Controller;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SheetAyamPivotController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'import_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $query = GeneralLedgerEntry::query()
                ->where('source_sheet', 'AYAM')
                ->whereNotNull('month_number');

            if (!empty($validated['import_id'])) {
                $query->where(
                    'import_id',
                    (int) $validated['import_id']
                );
            }

            $entries = $query
                ->orderBy('month_number')
                ->orderBy('source_row')
                ->get([
                    'month_number',
                    'tipe_transaksi',
                    'location',
                    'partner_name',
                    'mutasi',
                ]);

            if ($entries->isEmpty()) {
                return response()->json([
                    'message' => 'Data sheet AYAM tidak ditemukan.',
                ], Response::HTTP_NOT_FOUND);
            }

            $locations = $entries
                ->filter(fn ($entry) =>
                    $this->isPembelian($entry->tipe_transaksi)
                )
                ->pluck('location')
                ->filter(fn ($value) =>
                    trim((string) $value) !== ''
                )
                ->map(fn ($value) =>
                    trim((string) $value)
                )
                ->unique(fn ($value) =>
                    mb_strtolower($value)
                )
                ->sort()
                ->values();

            $monthlyEntries = $entries->groupBy(
                fn ($entry) => (int) $entry->month_number
            );

            $exportRows = [];
            $grandMatiJual = 0.0;
            $grandPembelian = 0.0;
            $grandLocationTotals = [];

            foreach ($locations as $location) {
                $grandLocationTotals[$location] = 0.0;
            }

            $rekapData = [];
            $rekapGrandTotal = array_fill(1, 12, 0.0);

            foreach ($entries as $entry) {
                if (!$this->isPembelian($entry->tipe_transaksi)) {
                    continue;
                }

                $month = (int) $entry->month_number;

                if ($month < 1 || $month > 12) {
                    continue;
                }

                $partner = trim(
                    (string) ($entry->partner_name ?? '')
                );

                if ($partner === '') {
                    $partner = '(blank)';
                }

                $namaNpwp = $this->getNamaNpwp($partner);

                $partnerKey = mb_strtolower($partner);

                if (!isset($rekapData[$partnerKey])) {
                    $rekapData[$partnerKey] = [
                        'nama_npwp' => $namaNpwp,
                        'partner_name' => $partner,
                        'months' => array_fill(1, 12, 0.0),
                    ];
                }

                $value = (float) ($entry->mutasi ?? 0);

                $rekapData[$partnerKey]['months'][$month] += $value;
                $rekapGrandTotal[$month] += $value;
            }

            foreach ($monthlyEntries->sortKeys() as $month => $rows) {
                $matiJual = $rows
                    ->filter(fn ($entry) =>
                        $this->isMatiJual($entry->tipe_transaksi)
                    )
                    ->sum(fn ($entry) =>
                        (float) ($entry->mutasi ?? 0)
                    );

                $locationTotals = [];

                foreach ($locations as $location) {
                    $locationTotals[$location] = $rows
                        ->filter(function ($entry) use ($location) {
                            return $this->isPembelian(
                                $entry->tipe_transaksi
                            ) && mb_strtolower(
                                trim((string) $entry->location)
                            ) === mb_strtolower(
                                trim((string) $location)
                            );
                        })
                        ->sum(fn ($entry) =>
                            (float) ($entry->mutasi ?? 0)
                        );
                }

                $pembelianTotal = array_sum($locationTotals);

                $row = [
                    (int) $month,
                    $matiJual,
                    $matiJual,
                ];

                foreach ($locations as $location) {
                    $row[] = $locationTotals[$location];
                }

                $row[] = $pembelianTotal;
                $row[] = 0;
                $row[] = 0;
                $row[] = $matiJual + $pembelianTotal;

                $exportRows[] = $row;

                $grandMatiJual += $matiJual;
                $grandPembelian += $pembelianTotal;

                foreach ($locations as $location) {
                    $grandLocationTotals[$location] +=
                        $locationTotals[$location];
                }
            }

            $headerTop = [
                'Row Labels',
                'MATI/JUAL',
                'MATI/JUAL Total',
            ];

            foreach ($locations as $location) {
                $headerTop[] = 'PEMBELIAN';
            }

            $headerTop[] = 'PEMBELIAN Total';
            $headerTop[] = '(blank)';
            $headerTop[] = '(blank) Total';
            $headerTop[] = 'Grand Total';

            $headerBottom = ['', '', ''];

            foreach ($locations as $location) {
                $headerBottom[] = $location;
            }

            $headerBottom[] = '';
            $headerBottom[] = '';
            $headerBottom[] = '';
            $headerBottom[] = '';

            $grandRow = [
                'Grand Total',
                $grandMatiJual,
                $grandMatiJual,
            ];

            foreach ($locations as $location) {
                $grandRow[] = $grandLocationTotals[$location];
            }

            $grandRow[] = $grandPembelian;
            $grandRow[] = 0;
            $grandRow[] = 0;
            $grandRow[] = $grandMatiJual + $grandPembelian;

            $exportRows[] = $grandRow;

            $rekapRows = array_values($rekapData);

            usort($rekapRows, function ($a, $b) {
                $nameCompare = strcasecmp(
                    $a['nama_npwp'],
                    $b['nama_npwp']
                );

                if ($nameCompare !== 0) {
                    return $nameCompare;
                }

                return strcasecmp(
                    $a['partner_name'],
                    $b['partner_name']
                );
            });

            $formattedRekapRows = [];

            foreach ($rekapRows as $item) {
                $row = [
                    $item['nama_npwp'],
                    $item['partner_name'],
                ];

                for ($month = 1; $month <= 12; $month++) {
                    $row[] = $item['months'][$month] ?? 0.0;
                }

                $formattedRekapRows[] = $row;
            }

            $grandRekapRow = [
                'Grand Total',
                '',
            ];

            for ($month = 1; $month <= 12; $month++) {
                $grandRekapRow[] = $rekapGrandTotal[$month] ?? 0.0;
            }

            $formattedRekapRows[] = $grandRekapRow;

            Log::info('Export AYAM rekap bulanan', [
                'jumlah_baris' => count($formattedRekapRows),
                'contoh_baris' => $formattedRekapRows[0] ?? null,
            ]);

            $payload = [
                'header_top' => $headerTop,
                'header_bottom' => $headerBottom,
                'export_rows' => $exportRows,
                'location_count' => $locations->count(),
                'rekap_rows' => $formattedRekapRows,
            ];

            $filename = isset($validated['import_id'])
                ? 'pivot-ayam-import-' . $validated['import_id'] . '.xlsx'
                : 'pivot-ayam.xlsx';

            return Excel::download(
                new SheetAyamExport($payload),
                $filename
            );
        } catch (Throwable $e) {
            Log::error('Export Pivot AYAM gagal.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat file Excel.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getNamaNpwp(string $partner): string
    {
        $partner = trim($partner);

        $partner = preg_replace(
            '/^PT\.?\s*/i',
            '',
            $partner
        );

        $parts = explode(' - ', $partner, 2);

        return trim($parts[0]);
    }

    private function isMatiJual(mixed $type): bool
    {
        return in_array(
            mb_strtoupper(trim((string) $type)),
            [
                'MATI/JUAL',
                'MATI',
                'JUAL',
                'MATI JUAL',
            ],
            true
        );
    }

    private function isPembelian(mixed $type): bool
    {
        return mb_strtoupper(
            trim((string) $type)
        ) === 'PEMBELIAN';
    }
}