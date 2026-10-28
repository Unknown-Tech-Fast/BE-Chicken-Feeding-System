<?php

namespace App\Http\Requests\MasterAyam;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterAyamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_fase' => ['required', 'string', 'max:50', 'unique:master_ayam,kode_fase'],
            'nama_fase' => ['required', 'string', 'max:150'],
            'default_umur_deplesi' => ['required', 'integer', 'min:0'],
            'coa_persediaan' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}