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
                    'mutasi',
                ]);

            if ($entries->isEmpty()) {
                return response()->json([
                    'message' => 'Data sheet AYAM tidak ditemukan.',
                ], Response::HTTP_NOT_FOUND);
            }
            $locations = $entries
                ->filter(function ($entry) {
                    return $this->isPembelian(
                        $entry->tipe_transaksi
                    );
                })
                ->pluck('location')
                ->filter(function ($location) {
                    return trim((string) $location) !== '';
                })
                ->map(function ($location) {
                    return trim((string) $location);
                })
                ->unique(function ($location) {
                    return mb_strtolower($location);
                })
                ->sort()
                ->values();

            $monthlyEntries = $entries->groupBy(
                fn ($entry) => (int) $entry->month_number
            );

            $exportRows = [];

            $grandMatiJual = 0;
            $grandPembelian = 0;

            $grandLocationTotals = [];

            foreach ($locations as $location) {
                $grandLocationTotals[$location] = 0;
            }

            foreach ($monthlyEntries->sortKeys() as $month => $rows) {
                $matiJual = $rows
                    ->filter(function ($entry) {
                        return $this->isMatiJual(
                            $entry->tipe_transaksi
                        );
                    })
                    ->sum(function ($entry) {
                        return (float) ($entry->mutasi ?? 0);
                    });

                $locationTotals = [];

                foreach ($locations as $location) {
                    $locationTotals[$location] = $rows
                        ->filter(function ($entry) use ($location) {
                            return $this->isPembelian(
                                $entry->tipe_transaksi
                            )
                                && mb_strtolower(
                                    trim((string) $entry->location)
                                ) === mb_strtolower(
                                    trim((string) $location)
                                );
                        })
                        ->sum(function ($entry) {
                            return (float) ($entry->mutasi ?? 0);
                        });
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

            $headerBottom = [
                '',
                '',
                '',
            ];

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

            $payload = [
                'header_top' => $headerTop,
                'header_bottom' => $headerBottom,
                'export_rows' => $exportRows,
                'location_count' => $locations->count(),
            ];

            $filename = isset($validated['import_id'])
                ? 'pivot-ayam-import-'
                    . $validated['import_id']
                    . '.xlsx'
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

    private function isMatiJual(mixed $type): bool
    {
        $type = mb_strtoupper(trim((string) $type));

        return in_array(
            $type,
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