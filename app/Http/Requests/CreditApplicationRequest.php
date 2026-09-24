<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Services\SimpleInterestProjectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('applied_on')) {
            $this->merge(['applied_on' => today()->toDateString()]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')->where('status', 'active')],
            'seller_id' => ['required', Rule::exists('seller_profiles', 'id')->where('status', 'active')->where(fn ($query) => $query->whereJsonContains('capabilities', 'credit_origination'))],
            'credit_product_id' => ['required', Rule::exists('credit_products', 'id')->where('is_active', true)],
            'requested_amount' => ['required', 'decimal:0,2', 'gt:0'], 'approved_amount' => ['nullable', 'decimal:0,2', 'gt:0'],
            'currency' => ['required', Rule::in(['NIO', 'USD'])], 'purpose' => ['required', 'string', 'max:1000'],
            'applied_on' => ['required', 'date', 'before_or_equal:today'],
            'installment_amount' => ['nullable', 'decimal:0,2', 'gt:0'],
            'term' => ['nullable', 'integer', 'min:1', 'max:'.SimpleInterestProjectionService::MAX_PAYMENTS],
            'term_value' => ['required', 'integer', 'min:1', 'max:365'],
            'term_unit' => ['required', Rule::in(array_keys(SimpleInterestProjectionService::TERM_UNITS))],
            'payment_frequency' => ['required', Rule::in(SimpleInterestProjectionService::PAYMENT_FREQUENCIES)],
            'interest_rate' => ['required', 'decimal:0,6', 'min:0'], 'interest_method' => ['nullable', Rule::in(['flat', 'declining_balance', 'french'])],
            'proposed_first_payment_date' => ['nullable', 'date'], 'administrative_fee' => ['nullable', 'decimal:0,2', 'min:0'],
            'seller_notes' => ['nullable', 'string', 'max:2000'], 'analyst_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'review'])], 'requires_guarantor' => ['sometimes', 'boolean'],
            'guarantors' => ['nullable', 'array', 'max:10'],
            'guarantors.*.guarantor_id' => ['nullable', 'required_without:guarantors.*.full_name', 'exists:guarantors,id'],
            'guarantors.*.full_name' => ['nullable', 'required_without:guarantors.*.guarantor_id', 'string', 'max:180'],
            'guarantors.*.identity_number' => ['nullable', 'string', 'max:30'], 'guarantors.*.phone' => ['nullable', 'string', 'max:30'],
            'guarantors.*.email' => ['nullable', 'email', 'max:180'], 'guarantors.*.address' => ['nullable', 'string', 'max:1000'],
            'guarantors.*.guaranteed_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'guarantors.*.guarantee_type' => ['required', Rule::in(['personal', 'solidary', 'limited'])],
            'guarantors.*.relationship' => ['required', 'string', 'max:100'],
            'guarantors.*.occupation' => ['nullable', 'string', 'max:150'], 'guarantors.*.workplace' => ['nullable', 'string', 'max:180'],
            'guarantors.*.workplace_address' => ['nullable', 'string', 'max:1000'],
            'guarantors.*.monthly_income' => ['required', 'decimal:0,2', 'min:0'], 'guarantors.*.other_income' => ['nullable', 'decimal:0,2', 'min:0'],
            'guarantors.*.monthly_expenses' => ['required', 'decimal:0,2', 'min:0'], 'guarantors.*.assets' => ['nullable', 'string', 'max:3000'],
            'guarantors.*.has_overdue_obligations' => ['nullable', 'boolean'], 'guarantors.*.evaluation_notes' => ['nullable', 'string', 'max:2000'],
            'guarantors.*.accepted_at' => ['nullable', 'date'],
            'guarantors.*.identity_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'guarantors.*.signed_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'term_value.required' => 'Indica la duración del plazo.',
            'term_unit.required' => 'Selecciona si el plazo está expresado en días, semanas, meses o años.',
            'interest_rate.required' => 'Indica la tasa de interés por período.',
            'term.max' => 'El plazo no puede superar 365 pagos.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $clientId = $this->integer('client_id');
            $currentClientId = $this->route('application')?->client_id;
            if ($clientId && $clientId !== $currentClientId) {
                $client = Client::query()->find($clientId);
                if ($client && ! $client->canOriginateNewCredit()) {
                    $validator->errors()->add('client_id', 'Este cliente tiene un crédito activo o en mora. Debe finalizarlo antes de registrar una nueva solicitud.');
                }
            }
            if ($this->boolean('requires_guarantor') && empty(array_filter($this->input('guarantors', []), fn ($row) => ! empty($row['guarantor_id']) || ! empty($row['full_name'])))) {
                $validator->errors()->add('guarantors', 'Agrega al menos un fiador o indica que el crédito no lo requiere.');
            }
        });
    }
}
