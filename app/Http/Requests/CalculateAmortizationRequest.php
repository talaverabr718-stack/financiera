<?php

namespace App\Http\Requests;

use App\Services\AmortizationCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateAmortizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal' => ['required', 'decimal:0,2', 'gt:0', 'max:1000000000'],
            'annual_rate' => ['required', 'decimal:0,6', 'min:0', 'max:1000'],
            'periods' => ['exclude_if:method,monthly_flat', 'required_unless:method,monthly_flat', 'integer', 'min:1', 'max:365'],
            'term_months' => ['exclude_unless:method,monthly_flat', 'required_if:method,monthly_flat', 'integer', 'min:1', 'max:60'],
            'frequency' => [
                'required',
                Rule::in(array_keys(AmortizationCalculator::FREQUENCIES)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('method') === 'monthly_flat' && ! isset(AmortizationCalculator::MONTHLY_PAYMENT_FACTORS[$value])) {
                        $fail('Para interés simple mensual selecciona frecuencia semanal, quincenal o mensual.');
                    }
                },
            ],
            'method' => ['required', Rule::in(array_keys(AmortizationCalculator::METHODS))],
            'first_payment_date' => ['required', 'date'],
        ];
    }
}
