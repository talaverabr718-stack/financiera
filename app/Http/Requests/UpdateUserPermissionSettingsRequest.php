<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'system_role_id' => ['required', Rule::exists('system_roles', 'id')->where('is_active', true)],
            'overrides' => ['required', 'array'],
            'overrides.*.view' => ['nullable', 'boolean'],
            'overrides.*.manage' => ['nullable', 'boolean'],
            'overrides.*.full' => ['nullable', 'boolean'],
        ];
    }
}
