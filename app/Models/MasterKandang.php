<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterKandang extends Model
{
    protected $table = 'master_kandang';

    protected $fillable = ['lokasi_id', 'kode_kandang', 'kapasitas', 'is_active'];

    protected function casts(): array
    {
        return ['kapasitas' => 'integer', 'is_active' => 'boolean'];
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(MasterLokasi::class, 'lokasi_id');
    }

    public function transaksiAyamBulanan(): HasMany
    {
        return $this->hasMany(TransaksiAyamBulanan::class, 'kandang_id');
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'kandang_id');
    }
}
