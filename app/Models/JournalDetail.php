<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalDetail extends Model
{
    protected $table = 'journal_detail';

    protected $fillable = [
        'import_batch_id', 'sumber', 'sheet_sumber', 'tahun', 'bulan', 'tipe_transaksi',
        'divisi', 'lokasi_id', 'lokasi_raw', 'kandang_id', 'status',
        'tipe_internal', 'rekening_jenis', 'kode_coa', 'nama_coa', 'rekanan',
        'deskripsi_transaksi', 'referensi', 'nomor', 'tanggal', 'debit', 'kredit', 'mutasi',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'tanggal' => 'date',
            'debit' => 'decimal:2',
            'kredit' => 'decimal:2',
            'mutasi' => 'decimal:2',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(MasterLokasi::class, 'lokasi_id');
    }

    public function kandang(): BelongsTo
    {
        return $this->belongsTo(MasterKandang::class, 'kandang_id');
    }

    public function coaMapping(): BelongsTo
    {
        return $this->belongsTo(MasterCoaMapping::class, 'kode_coa', 'kode_coa');
    }
}
