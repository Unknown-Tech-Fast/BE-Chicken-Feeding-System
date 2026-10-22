<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    protected $table = 'import_batches';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'file_name', 'file_hash', 'source_type', 'status', 'total_rows',
        'successful_rows', 'failed_rows', 'error_summary', 'started_at',
        'finished_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'import_batch_id');
    }
}
