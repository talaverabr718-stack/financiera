<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('system_roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*.view' => ['required_with:permissions', 'boolean'],
            'permissions.*.manage' => ['required_with:permissions', 'boolean'],
            'permissions.*.full' => ['required_with:permissions', 'boolean'],
        ];
    }
}
