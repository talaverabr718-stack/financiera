<?php

namespace App\Http\Requests;

use App\Services\DelinquencyTrackingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecalculateLoanDelinquencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('method') && $this->filled('daily_rate')) {
            $this->merge(['method' => DelinquencyTrackingService::METHOD_DAILY_PERCENTAGE]);
        }
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_keys(DelinquencyTrackingService::MONETARY_METHODS))],
            'daily_rate' => [
                'exclude_unless:method,'.DelinquencyTrackingService::METHOD_DAILY_PERCENTAGE,
                'required_if:method,'.DelinquencyTrackingService::METHOD_DAILY_PERCENTAGE,
                'numeric', 'min:0', 'max:100', 'decimal:0,6',
            ],
            'fixed_amount' => [
                'exclude_unless:method,'.DelinquencyTrackingService::METHOD_FIXED,
                'required_if:method,'.DelinquencyTrackingService::METHOD_FIXED,
                'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999.99',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Selecciona si la mora será porcentual o fija.',
            'daily_rate.required' => 'Indica el porcentaje de mora por cada día de retraso.',
            'daily_rate.min' => 'El porcentaje de mora no puede ser negativo.',
            'fixed_amount.required' => 'Indica el cargo fijo por cada cuota vencida.',
            'fixed_amount.min' => 'El cargo fijo no puede ser negativo.',
        ];
    }
}
