<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeClosing extends Model
{
    protected $table = 'periode_closing';

    protected $fillable = [
        'tahun', 'bulan', 'status', 'total_biaya_operasional', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'total_biaya_operasional' => 'decimal:2',
            'locked_at' => 'datetime',
        ];
    }

    public function transaksiAyamBulanan(): HasMany
    {
        return $this->hasMany(TransaksiAyamBulanan::class, 'periode_id');
    }
}
