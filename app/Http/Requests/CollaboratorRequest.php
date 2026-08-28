<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $collaborator = $this->route('collaborator');

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'identity_number' => ['nullable', 'string', 'max:30', Rule::unique('seller_profiles')->ignore($collaborator)],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
        ];
    }
}
