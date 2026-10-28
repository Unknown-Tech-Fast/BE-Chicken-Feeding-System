<?php

namespace App\Http\Requests\MasterLokasi;

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
        return [
            'kode_lokasi' => [
                'required', 'string', 'max:50',
                Rule::unique('master_lokasi', 'kode_lokasi')
                    ->ignore($this->route('masterLokasi')),
            ],
            'nama_lokasi' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
