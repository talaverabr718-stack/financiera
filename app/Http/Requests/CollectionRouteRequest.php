<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Validation\Validator;
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
            'scheduled_until' => ['nullable', 'date', 'after_or_equal:scheduled_date'],
            'collector_id' => ['required', Rule::exists('seller_profiles', 'id')->where('status', 'active')->where(fn ($query) => $query->whereJsonContains('capabilities', 'collections'))],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['distinct', Rule::exists('clients', 'id')->where('status', 'active')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('scheduled_until') || ! $this->filled('scheduled_date')) {
                return;
            }

            try {
                $days = Carbon::parse($this->input('scheduled_date'))->diffInDays(Carbon::parse($this->input('scheduled_until')));
                if ($days > 366) {
                    $validator->errors()->add('scheduled_until', 'El rango no puede superar 366 días.');
                }
            } catch (\Throwable) {
                // Las reglas de fecha muestran el error correspondiente.
            }
        }];
    }
}
