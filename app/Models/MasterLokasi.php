<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterLokasi extends Model
{
    protected $table = 'master_lokasi';

    protected $fillable = ['kode_lokasi', 'nama_lokasi', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function kandang(): HasMany
    {
        return $this->hasMany(MasterKandang::class, 'lokasi_id');
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'lokasi_id');
    }
}
