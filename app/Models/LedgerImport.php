<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerImport extends Model
{
    protected $fillable = [
        'original_filename', 'stored_path', 'file_sha256', 'status',
        'total_rows', 'processed_rows', 'successful_rows', 'failed_rows',
        'total_chunks', 'processed_chunks', 'started_at', 'finished_at',
        'error_message', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'total_chunks' => 'integer',
            'processed_chunks' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GeneralLedgerEntry::class, 'import_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(LedgerImportError::class, 'import_id');
    }
}
