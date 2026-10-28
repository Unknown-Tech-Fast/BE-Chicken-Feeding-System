<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerImportRequest;
use App\Jobs\PrepareLedgerImport;
use App\Models\GeneralLedgerEntry;
use App\Models\LedgerImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Throwable;

class LedgerImportController extends Controller
{
    /**
     * Path file queue worker.
     */
    private const QUEUE_WORKER_BAT =
        'D:\\UniServer-Reload-1.3.15\\www\\chicken-feeding-system\\Backend\\start-queue-worker.bat';

    public function store(StoreLedgerImportRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $sha256 = hash_file('sha256', $file->getRealPath());

        // Cegah upload file dengan konten identik.
        $existing = LedgerImport::where('file_sha256', $sha256)->first();

        if ($existing) {
            return response()->json([
                'message' => 'File dengan konten identik sudah pernah diunggah.',
                'data' => $existing,
            ], Response::HTTP_CONFLICT);
        }

        // Simpan file Excel.
        $path = $file->store('ledger-imports', 'local');

        try {
            $import = LedgerImport::create([
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'file_sha256' => $sha256,
                'status' => 'queued',
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        // Minta Windows menjalankan file BAT.
        $workerTriggered = $this->triggerQueueWorker();

        // Masukkan job import ke antrean Laravel.
        PrepareLedgerImport::dispatch($import->id)
            ->onQueue('default');

        return response()->json([
            'message' => 'File diterima dan job import dimasukkan ke antrean.',
            'data' => [
                'id' => $import->id,
                'status' => $import->status,
                'status_url' => url("/api/ledger-imports/{$import->id}"),
                'worker_triggered' => $workerTriggered,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Jalankan file BAT sebagai proses background di Windows.
     *
     * Catatan: method ini tidak memeriksa apakah worker lain
     * sudah berjalan. Karena itu, hindari memanggilnya berulang
     * kali jika BAT berisi loop worker tanpa akhir.
     */
    private function triggerQueueWorker(): bool
    {
        $batPath = self::QUEUE_WORKER_BAT;

        if (!is_file($batPath)) {
            Log::error('File Queue Worker tidak ditemukan.', [
                'path' => $batPath,
            ]);

            return false;
        }

        try {
            // Start menjalankan CMD tanpa menunggu worker selesai.
            $command = 'start "" /B cmd.exe /c '
                . escapeshellarg($batPath);

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(5);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Gagal memicu file Queue Worker.', [
                    'path' => $batPath,
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput(),
                ]);

                return false;
            }

            Log::info('Permintaan menjalankan Queue Worker dikirim.', [
                'path' => $batPath,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Exception saat memicu Queue Worker.', [
                'path' => $batPath,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function show(LedgerImport $ledgerImport): JsonResponse
    {
        $ledgerImport->refresh();

        $durationSeconds = null;

        if ($ledgerImport->started_at) {
            $start = \Illuminate\Support\Carbon::parse(
                $ledgerImport->started_at
            );

            $end = $ledgerImport->finished_at
                ? \Illuminate\Support\Carbon::parse($ledgerImport->finished_at)
                : now();

            $durationSeconds = max(
                0,
                (int) $start->diffInSeconds($end)
            );
        }

        return response()->json([
            'data' => [
                'id' => $ledgerImport->id,
                'original_filename' => $ledgerImport->original_filename,
                'status' => $ledgerImport->status,
                'total_rows' => $ledgerImport->total_rows,
                'processed_rows' => $ledgerImport->processed_rows,
                'successful_rows' => $ledgerImport->successful_rows,
                'failed_rows' => $ledgerImport->failed_rows,
                'total_chunks' => $ledgerImport->total_chunks,
                'processed_chunks' => $ledgerImport->processed_chunks,
                'error_message' => $ledgerImport->error_message,
                'started_at' => $ledgerImport->started_at,
                'finished_at' => $ledgerImport->finished_at,
            ],
            'entries_count' => $ledgerImport->entries()->count(),
            'errors_count' => $ledgerImport->errors()->count(),
            'progress_percent' => $ledgerImport->total_chunks > 0
                ? (int) floor(
                    ($ledgerImport->processed_chunks
                        / $ledgerImport->total_chunks) * 100
                )
                : ($ledgerImport->status === 'completed' ? 100 : 0),
            'duration_seconds' => $durationSeconds,
            'duration_minutes' => $durationSeconds !== null
                ? round($durationSeconds / 60, 2)
                : null,
        ]);
    }

    public function errors(
        Request $request,
        LedgerImport $ledgerImport
    ): JsonResponse {
        $perPage = min(
            max((int) $request->integer('per_page', 50), 1),
            100
        );

        $errors = $ledgerImport->errors()
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($errors);
    }

    public function entries(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->integer('per_page', 50), 1),
            100
        );

        $query = GeneralLedgerEntry::query()
            ->with('import:id,original_filename')
            ->when(
                $request->filled('source_sheet'),
                fn ($q) => $q->where(
                    'source_sheet',
                    $request->string('source_sheet')->toString()
                )
            )
            ->when(
                $request->filled('jenis_transaksi'),
                fn ($q) => $q->where(
                    'jenis_transaksi',
                    $request->string('jenis_transaksi')->toString()
                )
            )
            ->when(
                $request->filled('coa_code'),
                fn ($q) => $q->where(
                    'coa_code',
                    $request->string('coa_code')->toString()
                )
            )
            ->when(
                $request->filled('from'),
                fn ($q) => $q->whereDate(
                    'transaction_date',
                    '>=',
                    $request->date('from')
                )
            )
            ->when(
                $request->filled('to'),
                fn ($q) => $q->whereDate(
                    'transaction_date',
                    '<=',
                    $request->date('to')
                )
            )
            ->when(
                $request->filled('import_id'),
                fn ($q) => $q->where(
                    'import_id',
                    $request->integer('import_id')
                )
            )
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        return response()->json($query->paginate($perPage));
    }
}
