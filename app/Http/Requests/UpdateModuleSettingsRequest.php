<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['modules' => ['required', 'array'], 'modules.*.enabled' => ['nullable', 'boolean'], 'modules.*.visible' => ['nullable', 'boolean'], 'modules.*.sort_order' => ['required', 'integer', 'between:0,999']];
    }
}
