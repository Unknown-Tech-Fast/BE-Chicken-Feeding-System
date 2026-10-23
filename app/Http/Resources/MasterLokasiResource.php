<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterLokasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_lokasi' => $this->kode_lokasi,
            'nama_lokasi' => $this->nama_lokasi,
            'is_active' => $this->is_active,
            'kandang_count' => $this->whenCounted('kandang'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}