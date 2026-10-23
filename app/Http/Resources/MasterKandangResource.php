<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterKandangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lokasi_id' => $this->lokasi_id,
            'lokasi' => $this->whenLoaded('lokasi'),
            'kode_kandang' => $this->kode_kandang,
            'kapasitas' => $this->kapasitas,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}