<?php

namespace App\Jobs;

use App\Models\LedgerImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class PrepareLedgerImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = LedgerImport::findOrFail($this->importId);
        $path = Storage::disk('local')->path($import->stored_path);

        if (!is_file($path)) {
            $import->update([
                'status' => 'failed',
                'error_message' => 'File import tidak ditemukan.',
                'finished_at' => now(),
            ]);

            return;
        }

        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $worksheets = $reader->listWorksheetInfo($path);

        Log::info('Ledger import: worksheets terdeteksi.', [
            'import_id' => $this->importId,
            'filename' => $import->original_filename,
            'worksheets' => array_map(
                fn($sheet) => [
                    'name' => $sheet['worksheetName'],
                    'total_rows' => $sheet['totalRows'],
                ],
                $worksheets
            ),
        ]);

        $configs = [
            'OVK' => ['start_row' => 4],
            'PAKAN.OVD' => ['start_row' => 5],
            'AYAM' => ['start_row' => 4],
        ];

        $jobs = [];
        $totalRows = 0;
        $chunkSize = 1000;
        $chunksBySheet = [];
        $rowsBySheet = [];

        foreach ($worksheets as $sheetInfo) {
            $sheetName = $sheetInfo['worksheetName'];
            $lastRow = (int) $sheetInfo['totalRows'];

            Log::info('Ledger import: memeriksa worksheet.', [
                'import_id' => $this->importId,
                'sheet_name' => $sheetName,
                'configured' => isset($configs[$sheetName]),
                'total_rows' => $lastRow,
            ]);

            if (!isset($configs[$sheetName])) {
                Log::warning(
                    'Ledger import: worksheet dilewati karena tidak ada konfigurasi.',
                    [
                        'import_id' => $this->importId,
                        'sheet_name' => $sheetName,
                    ]
                );

                continue;
            }

            $startRow = $configs[$sheetName]['start_row'];

            if ($lastRow < $startRow) {
                Log::warning('Ledger import: worksheet tidak memiliki baris data.', [
                    'import_id' => $this->importId,
                    'sheet_name' => $sheetName,
                    'start_row' => $startRow,
                    'last_row' => $lastRow,
                ]);

                continue;
            }

            $rowsBySheet[$sheetName] = $lastRow - $startRow + 1;
            $chunksBySheet[$sheetName] = 0;

            $totalRows += $lastRow - $startRow + 1;

            for (
                $chunkStart = $startRow;
                $chunkStart <= $lastRow;
                $chunkStart += $chunkSize
            ) {
                $chunkEnd = min(
                    $chunkStart + $chunkSize - 1,
                    $lastRow
                );

                $jobs[] = new ProcessLedgerChunk(
                    $this->importId,
                    $sheetName,
                    $chunkStart,
                    $chunkEnd,
                    $startRow
                );

                $chunksBySheet[$sheetName]++;
            }
        }

        Log::info('Ledger import: ringkasan chunk dibuat.', [
            'import_id' => $this->importId,
            'rows_by_sheet' => $rowsBySheet,
            'chunks_by_sheet' => $chunksBySheet,
            'total_rows' => $totalRows,
            'total_chunks' => count($jobs),
        ]);

        if (count($jobs) === 0) {
            $import->update([
                'status' => 'failed',
                'error_message' =>
                'Sheet OVK, PAKAN.OVD, atau AYAM tidak ditemukan atau tidak memiliki data.',
                'finished_at' => now(),
            ]);

            return;
        }

        $import->update([
            'total_rows' => $totalRows,
            'total_chunks' => count($jobs),
            'processed_chunks' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'completed_chunk_keys' => [],
        ]);

        foreach ($jobs as $job) {
            dispatch($job)->onQueue('default');
        }

        Log::info('Ledger import: semua chunk dikirim ke queue.', [
            'import_id' => $this->importId,
            'chunks_by_sheet' => $chunksBySheet,
            'total_chunks' => count($jobs),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        LedgerImport::whereKey($this->importId)->update([
            'status' => 'failed',
            'error_message' => mb_substr(
                $exception->getMessage(),
                0,
                5000
            ),
            'finished_at' => now(),
        ]);
    }
}
