<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permissions' => ['nullable', 'array'], 'permissions.*.*.view' => ['nullable', 'boolean'], 'permissions.*.*.manage' => ['nullable', 'boolean']];
    }
}
