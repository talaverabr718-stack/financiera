<?php

namespace App\Http\Requests;

use App\Models\SellerProfile;
use App\Models\Zone;
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
            'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($collaborator?->user_id)],
            'password' => [$collaborator ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'confirmed'],
            'code' => ['required', 'string', 'max:40', Rule::unique('seller_profiles')->ignore($collaborator)],
            'identity_number' => ['nullable', 'string', 'max:30', Rule::unique('seller_profiles')->ignore($collaborator)],
            'phone' => ['nullable', 'string', 'max:30'], 'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where('is_active', true)],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['distinct', Rule::in(array_keys(SellerProfile::CAPABILITIES))],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->zone_id && ! Zone::whereKey($this->zone_id)->where('branch_id', $this->branch_id)->exists()) {
                $validator->errors()->add('zone_id', 'La zona seleccionada no pertenece a la sucursal.');
            }
        });
    }
}
