<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'sidebar_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'font_family' => ['required', Rule::in(['instrument', 'inter', 'system', 'humanist', 'nunito', 'poppins', 'roboto', 'lato', 'serif', 'merriweather', 'georgia', 'mono'])], 'density' => ['required', Rule::in(['comfortable', 'compact'])], 'border_radius' => ['required', Rule::in(['soft', 'rounded', 'square'])]];
    }
}
