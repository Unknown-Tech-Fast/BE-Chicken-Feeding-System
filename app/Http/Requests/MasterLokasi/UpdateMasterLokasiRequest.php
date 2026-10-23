<?php

namespace App\Http\Requests\MasterKandang;

use App\Models\MasterLokasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterLokasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $masterLokasi = $this->route('masterLokasi');

        $lokasiId = is_object($masterLokasi)
            ? $masterLokasi->id
            : $masterLokasi;

        return [
            'kode_lokasi' => [
                'required',
                'string',
                'max:50',
                Rule::unique('master_lokasi', 'kode_lokasi')
                    ->ignore($lokasiId),
            ],
            'nama_lokasi' => [
                'required',
                'string',
                'max:255',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}