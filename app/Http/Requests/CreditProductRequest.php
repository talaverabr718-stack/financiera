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
        $quick = $this->boolean('quick');

        return [
            'code' => [$quick ? 'nullable' : 'required', 'string', 'max:30', Rule::unique('credit_products')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'currency' => ['required', Rule::in(['NIO', 'USD'])],
            'allowed_frequencies' => [$quick ? 'nullable' : 'required', 'array', 'min:1'],
            'allowed_frequencies.*' => [Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'allowed_interest_methods' => [$quick ? 'nullable' : 'required', 'array', 'min:1'],
            'allowed_interest_methods.*' => [Rule::in(['flat', 'declining_balance', 'french'])],
            'default_interest_rate' => [$quick ? 'required' : 'nullable', 'decimal:0,6', 'min:0'],
            'default_interest_method' => ['nullable', Rule::in(['flat', 'declining_balance', 'french'])],
            'default_administrative_fee' => [$quick ? 'nullable' : 'required', 'decimal:0,2', 'min:0'],
            'delinquency_method' => ['nullable', Rule::in(['none', 'daily_percentage', 'fixed'])],
            'delinquency_rate' => ['nullable', 'decimal:0,6', 'min:0'],
            'payment_allocation_order' => [$quick ? 'nullable' : 'required', 'array', 'size:4'],
            'payment_allocation_order.*' => ['distinct', Rule::in(['delinquency', 'fees', 'interest', 'principal'])],
            'minimum_term' => [$quick ? 'nullable' : 'required', 'integer', 'min:1'],
            'maximum_term' => [$quick ? 'nullable' : 'required', 'integer', 'gte:minimum_term'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function isQuickCreate(): bool
    {
        return $this->boolean('quick');
    }
}
