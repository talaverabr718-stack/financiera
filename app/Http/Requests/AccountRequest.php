<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $account = $this->route('account');
        return ['code'=>['required','string','max:30',Rule::unique('accounts')->ignore($account)],'name'=>['required','string','max:150'],'description'=>['nullable','string','max:500'],'type'=>['required',Rule::in(array_keys(Account::TYPES))],'parent_id'=>['nullable',Rule::exists('accounts','id')->where('is_active',true),Rule::notIn([$account?->id])],'is_postable'=>['nullable','boolean'],'is_active'=>['nullable','boolean']];
    }
}
