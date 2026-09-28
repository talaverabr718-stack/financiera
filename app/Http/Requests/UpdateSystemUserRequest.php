<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateSystemUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'system_role_id' => ['nullable', Rule::exists('system_roles', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'collaborator_id' => ['nullable', Rule::exists('seller_profiles', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user?->id))],
        ];
    }
}
