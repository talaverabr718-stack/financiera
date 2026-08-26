<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'scheduled_date' => ['required', 'date'],
            'collector_id' => ['required', Rule::exists('seller_profiles', 'id')->where('status', 'active')->where(fn ($query) => $query->whereJsonContains('capabilities', 'collections'))],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['distinct', Rule::exists('clients', 'id')->where('status', 'active')],
        ];
    }
}
