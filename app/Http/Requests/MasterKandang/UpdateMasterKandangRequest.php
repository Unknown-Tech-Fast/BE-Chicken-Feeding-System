<?php

namespace App\Http\Requests\MasterKandang;

use App\Models\MasterLokasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterKandangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lokasi_id' => ['required', 'integer', Rule::exists((new MasterLokasi)->getTable(), 'id')],
            'kode_kandang' => [
                'required', 'string', 'max:50',
                Rule::unique('master_kandang', 'kode_kandang')
                    ->where('lokasi_id', $this->input('lokasi_id'))
                    ->ignore($this->route('masterKandang')),
            ],
            'kapasitas' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}