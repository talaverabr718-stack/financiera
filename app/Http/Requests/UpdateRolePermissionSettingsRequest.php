<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('system_roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*.view' => ['required', 'boolean'],
            'permissions.*.manage' => ['required', 'boolean'],
            'permissions.*.full' => ['required', 'boolean'],
        ];
    }
}
