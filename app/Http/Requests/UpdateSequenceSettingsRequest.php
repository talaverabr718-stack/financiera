<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSequenceSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['sequences' => ['required', 'array'], 'sequences.*.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'], 'sequences.*.padding' => ['required', 'integer', 'between:3,12']];
    }
}
