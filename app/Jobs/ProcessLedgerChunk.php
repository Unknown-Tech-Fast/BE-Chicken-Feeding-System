<?php

namespace App\Jobs;

use App\Models\GeneralLedgerEntry;
use App\Models\LedgerImport;
use App\Models\LedgerImportError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class LedgerChunkReadFilter implements IReadFilter
{
    public function __construct(
        private int $startRow,
        private int $endRow
    ) {}

    public function readCell(
        string $columnAddress,
        int $row,
        string $worksheetName = ''
    ): bool {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}

class ProcessLedgerChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public bool $failOnTimeout = true;

    /**
     * PostgreSQL advisory transaction lock.
     * Semua job import yang menerapkan aturan deduplikasi ini harus memakai lock yang sama.
     */
    private const DEDUPLICATION_LOCK_ID = 782341901;

    public function __construct(
        public int $importId,
        public string $sheetName,
        public int $chunkStart,
        public int $chunkEnd,
        public int $dataStartRow
    ) {}

    public function handle(): void
    {
        $import = LedgerImport::findOrFail($this->importId);

        if (in_array($import->status, [
            'failed',
            'completed',
            'completed_with_errors',
        ], true)) {
            return;
        }

        $path = Storage::disk('local')->path($import->stored_path);

        if (!is_file($path)) {
            throw new \RuntimeException("File import tidak ditemukan: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$this->sheetName]);
        $reader->setReadFilter(
            new LedgerChunkReadFilter($this->chunkStart, $this->chunkEnd)
        );

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName($this->sheetName);

            if (!$sheet) {
                throw new \RuntimeException(
                    "Sheet {$this->sheetName} tidak ditemukan."
                );
            }

            $processedCount = 0;
            $validationErrorCount = 0;
            $insertRows = [];
            $errorRows = [];
            $now = now();

            for (
                $rowNumber = $this->chunkStart;
                $rowNumber <= $this->chunkEnd;
                $rowNumber++
            ) {
                $values = $sheet->rangeToArray(
                    "A{$rowNumber}:W{$rowNumber}",
                    null,
                    true,
                    false
                )[0] ?? [];

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $processedCount++;

                try {
                    $insertRows[] = $this->mapRow($values, $rowNumber, $now);
                } catch (Throwable $e) {
                    $validationErrorCount++;
                    $errorRows[] = $this->makeError(
                        $rowNumber,
                        $values,
                        $e->getMessage(),
                        $now
                    );
                }
            }

            $chunkKey = $this->sheetName
                . ':'
                . $this->chunkStart
                . ':'
                . $this->chunkEnd;

            DB::transaction(function () use (
                $chunkKey,
                $processedCount,
                $validationErrorCount,
                $insertRows,
                $errorRows,
                $now
            ): void {
                /*
                 * Lock row import. Retry untuk chunk yang sama akan menunggu
                 * transaksi sebelumnya, lalu membaca status terbaru.
                 */
                $import = LedgerImport::query()
                    ->whereKey($this->importId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $completedKeys = $import->completed_chunk_keys ?? [];

                if (is_string($completedKeys)) {
                    $completedKeys = json_decode($completedKeys, true) ?: [];
                }

                if (!is_array($completedKeys)) {
                    $completedKeys = [];
                }

                if (in_array($chunkKey, $completedKeys, true)) {
                    return;
                }

                if (in_array($import->status, [
                    'failed',
                    'completed',
                    'completed_with_errors',
                ], true)) {
                    return;
                }

                DB::select(
                    'SELECT pg_advisory_xact_lock(?)',
                    [self::DEDUPLICATION_LOCK_ID]
                );

                $rowsToInsert = $insertRows;

                /*
                 * Simpan seluruh baris ke general_ledger_entries.
                 * Gunakan insertOrIgnore untuk menjaga idempotensi jika chunk di-retry.
                 */
                foreach (array_chunk($rowsToInsert, 300) as $batch) {
                    GeneralLedgerEntry::query()->insertOrIgnore($batch);
                }
                $successCount = 0;

                foreach (array_chunk($rowsToInsert, 300) as $batch) {
                    $sourceRows = array_column($batch, 'source_row');

                    $successCount += GeneralLedgerEntry::query()
                        ->where('import_id', $this->importId)
                        ->where('source_sheet', $this->sheetName)
                        ->whereIn('source_row', $sourceRows)
                        ->count();
                }

                /*
                 * Baris valid yang gagal masuk karena konflik constraint
                 * dihitung sebagai failed, bukan successful.
                 */
                $insertFailureCount = max(
                    0,
                    count($rowsToInsert) - $successCount
                );

                $allErrors = $errorRows;

                if ($insertFailureCount > 0) {
                    $validationErrorCount += $insertFailureCount;

                    foreach ($rowsToInsert as $record) {
                        $existsForSource = GeneralLedgerEntry::query()
                            ->where('import_id', $this->importId)
                            ->where('source_sheet', $this->sheetName)
                            ->where('source_row', $record['source_row'])
                            ->exists();

                        if (!$existsForSource) {
                            $allErrors[] = $this->makeError(
                                (int) $record['source_row'],
                                [],
                                'Baris tidak dapat disimpan karena konflik constraint database.',
                                $now
                            );
                        }
                    }
                }

                foreach (array_chunk($allErrors, 300) as $batch) {
                    if ($batch !== []) {
                        LedgerImportError::query()->insert($batch);
                    }
                }

                $completedKeys[] = $chunkKey;
                $completedKeys = array_values(array_unique($completedKeys));

                $import->completed_chunk_keys = json_encode(
                    $completedKeys,
                    JSON_THROW_ON_ERROR
                );

                $import->processed_rows =
                    (int) $import->processed_rows + $processedCount;

                $import->successful_rows =
                    (int) $import->successful_rows + $successCount;

                $import->failed_rows =
                    (int) $import->failed_rows + $validationErrorCount;

                $import->skipped_rows = 0;

                $import->processed_chunks = count($completedKeys);

                if (
                    $import->total_chunks > 0
                    && $import->processed_chunks >= $import->total_chunks
                ) {
                    $import->status = $import->failed_rows > 0
                        ? 'completed_with_errors'
                        : 'completed';
                    $import->finished_at = now();
                }

                $import->save();
            }, 3);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function mapRow(array $row, int $sourceRow, $now): array
    {
        /*
         * Mapping ini mengikuti implementasi yang kamu kirim:
         * document_number = kolom M (index 12)
         * transaction_date = kolom N (index 13)
         */
        $partner = $this->stringOrNull($row[9] ?? null);
        $debit = $this->money($row[14] ?? 0);
        $credit = $this->money($row[15] ?? 0);
        $date = $this->dateValue($row[13] ?? null);

        $payload = [];

        foreach ($row as $index => $value) {
            $payload['column_' . ($index + 1)] =
                $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        return [
            'import_id' => $this->importId,
            'source_sheet' => $this->sheetName,
            'source_row' => $sourceRow,
            'month_number' => $this->integerOrNull($row[0] ?? null),
            'tipe_transaksi' => $this->stringOrNull($row[1] ?? null),
            'division' => $this->stringOrNull($row[2] ?? null),
            'location' => $this->stringOrNull($row[3] ?? null),
            'status' => $this->stringOrNull($row[4] ?? null),
            'internal_type' => $this->stringOrNull($row[5] ?? null),
            'account_type' => $this->stringOrNull($row[6] ?? null),
            'coa_code' => $this->stringOrNull($row[7] ?? null),
            'coa_name' => $this->stringOrNull($row[8] ?? null),
            'partner_name' => $partner,
            'deskripsi_transaksi' => $this->stringOrNull($row[10] ?? null),
            'reference' => $this->stringOrNull($row[11] ?? null),
            'document_number' => $this->stringOrNull($row[12] ?? null),
            'transaction_date' => $date,
            'debit' => number_format($debit, 2, '.', ''),
            'credit' => number_format($credit, 2, '.', ''),
            'jenis_transaksi' => $partner === null ? 'PEMAKAIAN' : 'PEMBELIAN',
            'mutasi' => number_format(round($debit - $credit, 2), 2, '.', ''),
            'source_payload' => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            'row_hash' => hash(
                'sha256',
                json_encode(
                    [$this->sheetName, $sourceRow, $payload],
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                )
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function makeError(
        int $rowNumber,
        array $values,
        string $message,
        $now,
        ?string $columnName = null
    ): array {
        return [
            'import_id' => $this->importId,
            'source_sheet' => $this->sheetName,
            'source_row' => $rowNumber,
            'column_name' => $columnName,
            'raw_value' => mb_substr(
                json_encode(
                    $values,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: '',
                0,
                60000
            ),
            'message' => mb_substr($message, 0, 5000),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function normalizeDocumentNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(["\xc2\xa0", ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            throw new \RuntimeException("Nilai nominal tidak valid: {$value}");
        }

        return (float) $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)
                ->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            throw new \RuntimeException('Format tanggal tidak valid.');
        }
    }

    public function failed(Throwable $exception): void
    {
        LedgerImport::whereKey($this->importId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
            'finished_at' => now(),
        ]);
    }
}
