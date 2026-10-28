<?php

namespace App\Http\Requests\TransaksiAyamBulanan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransaksiAyamBulananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'flock_no' => (string) ($this->input('flock_no') ?? ''),
        ]);
    }

    public function rules(): array
    {
        return [
            'periode_id' => [
                'required',
                'integer',
                Rule::exists('periode_closing', 'id'),
            ],

            'lokasi_id' => [
                'required',
                'integer',
                Rule::exists('master_lokasi', 'id'),
            ],

            'kandang_id' => [
                'required',
                'integer',
                Rule::exists('master_kandang', 'id')
                    ->where('lokasi_id', $this->input('lokasi_id')),

                Rule::unique('transaksi_ayam_bulanan', 'kandang_id')
                    ->where('periode_id', $this->input('periode_id'))
                    ->where('master_ayam_id', $this->input('master_ayam_id'))
                    ->where('flock_no', $this->input('flock_no'))
                    ->ignore($this->route('transaksiAyamBulanan')),
            ],

            'master_ayam_id' => [
                'required',
                'integer',
                Rule::exists('master_ayam', 'id'),
            ],

            'flock_no' => ['present', 'max:100'],
            'umur_minggu' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'so_ekor' => ['sometimes', 'integer', 'min:0'],
            'in_ekor' => ['sometimes', 'integer', 'min:0'],
            'out_ekor' => ['sometimes', 'integer', 'min:0'],
            'sa_ekor' => ['sometimes', 'integer', 'min:0'],
            'mati_ekor' => ['sometimes', 'integer', 'min:0'],
            'jual_ekor' => ['sometimes', 'integer', 'min:0'],
            'koreksi_ekor' => ['sometimes', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'lokasi_id.required' => 'Lokasi wajib dipilih.',
            'lokasi_id.exists' => 'Lokasi yang dipilih tidak ditemukan.',
            'kandang_id.exists' => 'Kandang tidak ditemukan atau tidak sesuai dengan lokasi yang dipilih.',
            'kandang_id.unique' => 'Data untuk kombinasi periode, kandang, fase ayam, dan flock ini sudah ada.',
        ];
    }
}
