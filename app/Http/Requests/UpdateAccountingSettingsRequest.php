<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_postable', true));

        return [
            'cash_account_id' => ['nullable', $account],
            'bank_account_id' => ['nullable', $account],
            'loan_receivable_account_id' => ['nullable', $account],
            'interest_income_account_id' => ['nullable', $account],
            'fee_income_account_id' => ['nullable', $account],
        ];
    }
}
