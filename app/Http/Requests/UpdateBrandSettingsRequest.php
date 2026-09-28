<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'system_name' => ['required', 'string', 'max:80'],
            'system_tagline' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'system_name.required' => 'El nombre del sistema es obligatorio.',
            'logo.image' => 'El logotipo debe ser una imagen válida.',
            'logo.mimes' => 'El logotipo debe estar en formato PNG, JPG, JPEG o WEBP.',
            'logo.max' => 'El logotipo no puede superar 2 MB.',
        ];
    }
}
