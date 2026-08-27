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
            'periods' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['required', Rule::in(array_keys(AmortizationCalculator::FREQUENCIES))],
            'method' => ['required', Rule::in(array_keys(AmortizationCalculator::METHODS))],
            'first_payment_date' => ['required', 'date'],
        ];
    }
}
