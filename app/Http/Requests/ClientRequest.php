<?php

namespace App\Http\Requests;

use App\Rules\NicaraguanIdentity;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $locations = config('nicaragua.locations', []);

        return [
            'full_name' => ['required', 'string', 'max:180'],
            'identity_number' => ['required', 'string', new NicaraguanIdentity, Rule::unique('clients')->ignore($client)],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:180'],
            'address' => ['required', 'string', 'max:1000'], 'department' => ['required', Rule::in(array_keys($locations))],
            'municipality' => ['required', 'string', 'max:100'], 'neighborhood' => ['required', 'string', 'max:150'],
            'economic_activity' => ['nullable', 'string', 'max:180'], 'workplace' => ['nullable', 'string', 'max:180'],
            'job_position' => ['nullable', 'string', 'max:150'], 'workplace_address' => ['nullable', 'string', 'max:1000'],
            'employment_duration_months' => ['nullable', 'integer', 'min:0'], 'estimated_income' => ['required', 'decimal:0,2', 'min:0'],
            'other_income' => ['nullable', 'decimal:0,2', 'min:0'], 'estimated_expenses' => ['required', 'decimal:0,2', 'min:0'],
            'housing_status' => ['nullable', Rule::in(['owned', 'rented', 'family', 'financed', 'other'])], 'dependents' => ['nullable', 'integer', 'min:0'],
            'assets' => ['nullable', 'array'], 'assets.*.type' => ['required_with:assets.*.description', Rule::in(['jewelry', 'vehicle', 'property', 'appliance', 'machinery', 'livestock', 'inventory', 'other'])],
            'assets.*.description' => ['required_with:assets.*.type', 'string', 'max:300'], 'assets.*.estimated_value' => ['nullable', 'decimal:0,2', 'min:0'],
            'assets.*.ownership_status' => ['nullable', Rule::in(['owned', 'financed', 'shared'])], 'assets.*.document_reference' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])], 'notes' => ['nullable', 'string', 'max:2000'],
            'seller_id' => [$client ? 'nullable' : 'required', 'nullable', Rule::exists('seller_profiles', 'id')->where('status', 'active')->where(fn ($query) => $query->whereJsonContains('capabilities', 'prospecting'))],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identity_number' => strtoupper(trim((string) $this->identity_number)),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $locations = config('nicaragua.locations', []);
            $municipalities = $locations[$this->department] ?? [];

            if ($this->municipality && ! array_key_exists($this->municipality, $municipalities)) {
                $validator->errors()->add('municipality', 'El municipio no pertenece al departamento seleccionado.');
            }

            $neighborhoods = $municipalities[$this->municipality] ?? [];
            if ($this->neighborhood && ! in_array($this->neighborhood, $neighborhoods, true)) {
                $validator->errors()->add('neighborhood', 'El barrio o comunidad no pertenece al municipio seleccionado.');
            }

            if (! $validator->errors()->has('identity_number') && ! $validator->errors()->has('birth_date')) {
                $identityDate = substr(str_replace('-', '', $this->identity_number), 3, 6);
                $birthDate = Carbon::createFromFormat('Y-m-d', $this->birth_date);

                if ($identityDate !== $birthDate->format('dmy')) {
                    $validator->errors()->add('birth_date', 'La fecha de nacimiento no coincide con la fecha incluida en la cédula.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['full_name.required' => 'El nombre completo es obligatorio.', 'identity_number.required' => 'La cédula es obligatoria.', 'identity_number.unique' => 'Ya existe un cliente con esta cédula.', 'birth_date.required' => 'La fecha de nacimiento es obligatoria.', 'address.required' => 'La dirección es obligatoria.', 'department.required' => 'Selecciona el departamento.', 'municipality.required' => 'Selecciona el municipio.', 'neighborhood.required' => 'Selecciona el barrio o comunidad.', 'seller_id.required' => 'Selecciona el vendedor responsable.'];
    }
}
