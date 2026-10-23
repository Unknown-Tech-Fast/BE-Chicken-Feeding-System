<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodeClosingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tahun' => $this->tahun,
            'bulan' => $this->bulan,
            'status' => $this->status,
            'total_biaya_operasional' => $this->total_biaya_operasional,
            'locked_at' => $this->locked_at,
            'locked_by' => $this->locked_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}