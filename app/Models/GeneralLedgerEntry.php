<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralLedgerEntry extends Model
{
    protected $fillable = [
        'import_id', 'source_sheet', 'source_row', 'month_number',
        'tipe_transaksi', 'division', 'location', 'status', 'internal_type',
        'account_type', 'coa_code', 'coa_name', 'partner_name',
        'deskripsi_transaksi', 'reference', 'document_number', 'transaction_date',
        'debit', 'credit', 'jenis_transaksi', 'mutasi', 'source_payload',
        'row_hash',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'mutasi' => 'decimal:2',
            'source_payload' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LedgerImport::class, 'import_id');
    }
}
