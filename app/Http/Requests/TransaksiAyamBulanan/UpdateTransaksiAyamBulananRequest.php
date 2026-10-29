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
        if ($this->exists('flock_no')) {
            $this->merge([
                'flock_no' => (string) $this->input('flock_no'),
            ]);
        }
    }

    public function rules(): array
    {
        $transaksi = $this->route('transaksiAyamBulanan');

        $periodeId = $this->input('periode_id', $transaksi?->periode_id);
        $lokasiId = $this->input('lokasi_id', $transaksi?->lokasi_id);
        $kandangId = $this->input('kandang_id', $transaksi?->kandang_id);
        $masterAyamId = $this->input('master_ayam_id', $transaksi?->master_ayam_id);
        $flockNo = $this->input('flock_no', $transaksi?->flock_no ?? '');

        return [
            'periode_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('periode_closing', 'id'),
            ],

            'lokasi_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('master_lokasi', 'id'),
            ],

            'kandang_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('master_kandang', 'id')
                    ->where('lokasi_id', $lokasiId),

                Rule::unique('transaksi_ayam_bulanan', 'kandang_id')
                    ->where('periode_id', $periodeId)
                    ->where('master_ayam_id', $masterAyamId)
                    ->where('flock_no', $flockNo)
                    ->ignore($transaksi?->id),
            ],

            'master_ayam_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('master_ayam', 'id'),
            ],

            'flock_no' => ['sometimes', 'required', 'string', 'max:100'],

            'umur_minggu' => ['sometimes', 'integer', 'min:0', 'max:65535'],

            'in_ekor' => ['sometimes', 'integer', 'min:0'],
            'out_ekor' => ['sometimes', 'integer', 'min:0'],
            'mati_ekor' => ['sometimes', 'integer', 'min:0'],
            'jual_ekor' => ['sometimes', 'integer', 'min:0'],
            'koreksi_ekor' => ['sometimes', 'integer'],

            // Saldo awal dan saldo akhir dihitung/dikelola backend.
            'so_ekor' => ['prohibited'],
            'sa_ekor' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'lokasi_id.required' => 'Lokasi wajib dipilih.',
            'lokasi_id.exists' => 'Lokasi yang dipilih tidak ditemukan.',

            'kandang_id.exists' => 'Kandang tidak ditemukan atau tidak sesuai dengan lokasi yang dipilih.',
            'kandang_id.unique' => 'Data untuk kombinasi periode, kandang, jenis ayam, dan flock ini sudah ada.',

            'so_ekor.prohibited' => 'Saldo awal tidak dapat diubah melalui update transaksi.',
            'sa_ekor.prohibited' => 'Saldo akhir dihitung otomatis oleh sistem.',
        ];
    }
}
