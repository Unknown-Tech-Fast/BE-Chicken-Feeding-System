<?php

namespace App\Http\Requests\PeriodeClosing;

use App\Models\PeriodeClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodeClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'min:2000', 'max:9999'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'status' => [
                'required',
                'string',
                Rule::in(['OPEN', 'CLOSED']),
            ],
            'total_biaya_operasional' => ['sometimes', 'numeric', 'min:0'],
            'locked_at' => ['sometimes', 'nullable', 'date'],
            'locked_by' => ['sometimes', 'nullable', 'integer'],
            'tahun' => [
                'required',
                'integer',
                'min:2000',
                'max:9999',
                Rule::unique('periode_closing', 'tahun')
                    ->where('bulan', $this->input('bulan')),
            ],
        ];
    }
}