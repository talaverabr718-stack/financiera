<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'system_role_id' => ['nullable', Rule::exists('system_roles', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'pin' => ['nullable', 'digits:4', 'confirmed'],
            'collaborator_id' => ['nullable', Rule::exists('seller_profiles', 'id')->whereNull('user_id')],
        ];
    }
}
