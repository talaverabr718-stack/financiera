<?php

namespace App\Http\Controllers;

use App\Models\CreditGuarantor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditGuarantorController extends Controller
{
    public function decision(Request $request, CreditGuarantor $guarantee)
    {
        $this->authorize('decide', $guarantee);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_reason' => ['required', 'string', 'max:1000'],
            'analyst_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['status'] === 'approved' && (! $guarantee->accepted_at || ! $guarantee->signed_document_path)) {
            throw ValidationException::withMessages(['status' => 'Para aprobar se requiere aceptación y documento de garantía firmado.']);
        }
        DB::transaction(function () use ($guarantee, $data): void {
            $locked = CreditGuarantor::lockForUpdate()->findOrFail($guarantee->id);
            $locked->update($data + ['approved_by' => auth()->id(), 'approved_at' => now(), 'evaluated_at' => $locked->latestEvaluation()->value('evaluated_at')]);
        });

        return back()->with('success', 'Decisión del fiador registrada.');
    }

    public function release(Request $request, CreditGuarantor $guarantee)
    {
        $this->authorize('release', $guarantee);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'authorized_release' => ['nullable', 'accepted']]);
        $loanIsSettled = $guarantee->loan && in_array($guarantee->loan->status, ['paid', 'cancelled'], true);
        if (! $loanIsSettled && ! $request->boolean('authorized_release')) {
            throw ValidationException::withMessages(['authorized_release' => 'El crédito no está cancelado; confirma que existe una liberación autorizada.']);
        }
        DB::transaction(fn () => CreditGuarantor::lockForUpdate()->findOrFail($guarantee->id)->update([
            'status' => 'released', 'released_by' => auth()->id(), 'released_at' => now(), 'decision_reason' => $data['reason'],
        ]));

        return back()->with('success', 'Garantía liberada con trazabilidad.');
    }
}
