<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecalculateLoanDelinquencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'daily_rate.required' => 'Indica el porcentaje de mora por cada día de retraso.',
            'daily_rate.min' => 'El porcentaje de mora no puede ser negativo.',
        ];
    }
}
