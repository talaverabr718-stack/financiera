<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('credit_products')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'currency' => ['required', Rule::in(['NIO', 'USD'])],
            'allowed_frequencies' => ['required', 'array', 'min:1'],
            'allowed_frequencies.*' => [Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'allowed_interest_methods' => ['required', 'array', 'min:1'],
            'allowed_interest_methods.*' => [Rule::in(['flat', 'declining_balance', 'french'])],
            'default_interest_rate' => ['nullable', 'decimal:0,6', 'min:0'],
            'default_interest_method' => ['nullable', Rule::in(['flat', 'declining_balance', 'french'])],
            'default_administrative_fee' => ['required', 'decimal:0,2', 'min:0'],
            'delinquency_method' => ['nullable', Rule::in(['none', 'daily_percentage', 'fixed'])],
            'delinquency_rate' => ['nullable', 'decimal:0,6', 'min:0'],
            'payment_allocation_order' => ['required', 'array', 'size:4'],
            'payment_allocation_order.*' => ['distinct', Rule::in(['delinquency', 'fees', 'interest', 'principal'])],
            'minimum_term' => ['required', 'integer', 'min:1'],
            'maximum_term' => ['required', 'integer', 'gte:minimum_term'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
