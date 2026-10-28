<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerImportError extends Model
{
    protected $fillable = [
        'import_id', 'source_sheet', 'source_row', 'column_name',
        'raw_value', 'message',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(LedgerImport::class, 'import_id');
    }
}
