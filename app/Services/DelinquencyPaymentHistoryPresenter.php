<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Support\Collection;

class DelinquencyPaymentHistoryPresenter
{
    public function present(Loan $loan): Collection
    {
        $loan->loadMissing(['payments.allocations.installment', 'payments.creator', 'payments.reversal', 'installments.paymentAllocations']);
        $receipts = $loan->payments->sortByDesc('received_at')->values()->map(function ($payment) {
            return ['source' => 'payment', 'date' => $payment->received_at, 'title' => $payment->receipt_number, 'amount' => $payment->amount, 'status' => $payment->reversal ? 'reversed' : $payment->status, 'method' => $payment->payment_method, 'actor' => $payment->creator?->name, 'allocations' => $payment->allocations->map(fn ($allocation) => ['installment' => $allocation->installment?->number, 'component' => $allocation->component, 'component_label' => $this->componentLabel($allocation->component), 'amount' => $allocation->amount])];
        });
        $withoutReceipt = $loan->installments->filter(fn (LoanInstallment $installment) => bccomp($installment->amountPaid(), '0.00', 2) === 1 && $installment->paymentAllocations->isEmpty())->map(fn (LoanInstallment $installment) => ['source' => 'installment', 'date' => $installment->updated_at, 'title' => 'Cuota '.$installment->number, 'amount' => $installment->amountPaid(), 'status' => $installment->status, 'method' => 'Registrado en la cuota', 'actor' => null, 'allocations' => collect([['installment' => $installment->number, 'component' => 'principal', 'amount' => $installment->principal_paid], ['installment' => $installment->number, 'component' => 'interest', 'amount' => $installment->interest_paid], ['installment' => $installment->number, 'component' => 'fees', 'amount' => $installment->fees_paid], ['installment' => $installment->number, 'component' => 'delinquency', 'amount' => $installment->delinquency_paid]])->filter(fn (array $row) => bccomp((string) $row['amount'], '0.00', 2) === 1)->map(fn (array $row) => $row + ['component_label' => $this->componentLabel($row['component'])])->values()]);

        return $receipts->concat($withoutReceipt)->sortByDesc(fn (array $row) => optional($row['date'])->timestamp ?? 0)->values();
    }

    private function componentLabel(string $component): string
    {
        return ['principal' => 'Principal', 'interest' => 'Interés', 'fees' => 'Cargos', 'delinquency' => 'Cargo por mora'][$component] ?? $component;
    }
}
