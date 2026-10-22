<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiBiayaKandang extends Model
{
    protected $table = 'alokasi_biaya_kandang';

    protected $fillable = [
        'transaksi_ayam_id', 'nbo_ayam', 'nbo_ayam_dp', 'alokasi_biaya',
        'koreksi_alokasi', 'mati_rupiah', 'jual_rupiah', 'mutasi_koreksi_rupiah',
        'nba_ayam', 'umur_deplesi', 'deplesi_rupiah', 'nba_ayam_dp',
        'biaya_growing', 'mutasi_rupiah', 'status_perhitungan',
    ];

    protected function casts(): array
    {
        return [
            'nbo_ayam' => 'decimal:2',
            'nbo_ayam_dp' => 'decimal:2',
            'alokasi_biaya' => 'decimal:2',
            'koreksi_alokasi' => 'decimal:2',
            'mati_rupiah' => 'decimal:2',
            'jual_rupiah' => 'decimal:2',
            'mutasi_koreksi_rupiah' => 'decimal:2',
            'nba_ayam' => 'decimal:2',
            'umur_deplesi' => 'integer',
            'deplesi_rupiah' => 'decimal:2',
            'nba_ayam_dp' => 'decimal:2',
            'biaya_growing' => 'decimal:2',
            'mutasi_rupiah' => 'decimal:2',
        ];
    }

    public function transaksiAyam(): BelongsTo
    {
        return $this->belongsTo(TransaksiAyamBulanan::class, 'transaksi_ayam_id');
    }
}
