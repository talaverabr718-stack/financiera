<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class CorrectCollectionAmountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && app(PermissionService::class)->allows($this->user(), 'collections', 'manage');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999999.99'],
            'reason' => ['required', 'string', 'max:500'],
            'correction_authorization_id' => ['required', 'integer', 'exists:collection_payment_correction_authorizations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Ingresa el monto total correcto del pago.',
            'amount.gt' => 'El monto correcto debe ser mayor que cero.',
            'reason.required' => 'Explica por qué se corrige el monto.',
        ];
    }
}
