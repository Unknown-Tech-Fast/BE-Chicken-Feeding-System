<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaksiAyamBulananResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'periode_id' => $this->periode_id,
            'kandang_id' => $this->kandang_id,
            'master_ayam_id' => $this->master_ayam_id,
            'flock_no' => $this->flock_no,
            'umur_minggu' => $this->umur_minggu,
            'so_ekor' => $this->so_ekor,
            'in_ekor' => $this->in_ekor,
            'out_ekor' => $this->out_ekor,
            'sa_ekor' => $this->sa_ekor,
            'mati_ekor' => $this->mati_ekor,
            'jual_ekor' => $this->jual_ekor,
            'koreksi_ekor' => $this->koreksi_ekor,
            'periode' => $this->whenLoaded('periode'),
            'kandang' => new MasterKandangResource($this->whenLoaded('kandang')),
            'master_ayam' => new MasterAyamResource($this->whenLoaded('masterAyam')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}