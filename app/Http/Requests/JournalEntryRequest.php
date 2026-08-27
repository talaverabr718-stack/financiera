<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date', 'before_or_equal:today'], 'concept' => ['required', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where('is_active', true)->where('is_postable', true)], 'lines.*.detail' => ['nullable', 'string', 'max:255'], 'lines.*.debit' => ['nullable', 'decimal:0,2', 'gte:0'], 'lines.*.credit' => ['nullable', 'decimal:0,2', 'gte:0']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $debit = '0.00';
            $credit = '0.00';
            foreach ($this->input('lines', []) as $i => $line) {
                $d = (string) ($line['debit'] ?? 0);
                $c = (string) ($line['credit'] ?? 0);
                if (bccomp($d, '0', 2) > 0 && bccomp($c, '0', 2) > 0) {
                    $validator->errors()->add("lines.$i.debit", 'Una línea no puede tener Debe y Haber simultáneamente.');
                }if (bccomp($d, '0', 2) <= 0 && bccomp($c, '0', 2) <= 0) {
                    $validator->errors()->add("lines.$i.debit", 'Indica un monto en Debe o Haber.');
                }$debit = bcadd($debit, $d, 2);
                $credit = bcadd($credit, $c, 2);
            }if (bccomp($debit, $credit, 2) !== 0) {
                $validator->errors()->add('lines',"El asiento está desbalanceado: Debe $debit y Haber $credit.");
            }
        });
    }
}
