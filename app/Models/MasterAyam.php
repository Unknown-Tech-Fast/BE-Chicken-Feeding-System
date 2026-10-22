<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterAyam extends Model
{
    protected $table = 'master_ayam';

    protected $fillable = [
        'kode_fase', 'nama_fase', 'default_umur_deplesi', 'coa_persediaan', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_umur_deplesi' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function transaksiAyamBulanan(): HasMany
    {
        return $this->hasMany(TransaksiAyamBulanan::class, 'master_ayam_id');
    }
}
