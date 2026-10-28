<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterAyamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_fase' => $this->kode_fase,
            'nama_fase' => $this->nama_fase,
            'default_umur_deplesi' => $this->default_umur_deplesi,
            'coa_persediaan' => $this->coa_persediaan,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}