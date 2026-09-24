<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class AuthorizeCollectionPaymentCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && app(PermissionService::class)->allows($this->user(), 'collections', 'full');
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Explica por qué se habilita la corrección del monto.'];
    }
}
