<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'pin' => ['nullable', 'digits:4', 'confirmed'],
            'remove_pin' => ['nullable', 'boolean'],
            'collaborator_id' => ['nullable', Rule::exists('seller_profiles', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user?->id))],
        ];
    }
}
