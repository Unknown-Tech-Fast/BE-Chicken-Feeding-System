<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterCoaMapping extends Model
{
    protected $table = 'master_coa_mapping';

    protected $fillable = ['kode_coa', 'nama_coa', 'kategori', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'kode_coa', 'kode_coa');
    }
}
