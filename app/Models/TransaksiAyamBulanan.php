<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransaksiAyamBulanan extends Model
{
    protected $table = 'transaksi_ayam_bulanan';

    protected $fillable = [
        'periode_id', 'kandang_id', 'master_ayam_id', 'flock_no', 'umur_minggu',
        'so_ekor', 'in_ekor', 'out_ekor', 'sa_ekor', 'mati_ekor',
        'jual_ekor', 'koreksi_ekor',
    ];

    protected function casts(): array
    {
        return [
            'umur_minggu' => 'integer',
            'so_ekor' => 'integer',
            'in_ekor' => 'integer',
            'out_ekor' => 'integer',
            'sa_ekor' => 'integer',
            'mati_ekor' => 'integer',
            'jual_ekor' => 'integer',
            'koreksi_ekor' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeClosing::class, 'periode_id');
    }

    public function kandang(): BelongsTo
    {
        return $this->belongsTo(MasterKandang::class, 'kandang_id');
    }

    public function masterAyam(): BelongsTo
    {
        return $this->belongsTo(MasterAyam::class, 'master_ayam_id');
    }

    public function alokasiBiaya(): HasOne
    {
        return $this->hasOne(AlokasiBiayaKandang::class, 'transaksi_ayam_id');
    }
}
