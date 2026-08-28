<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class CollectionReceiptPresenter
{
    public function fromPayment(Payment $payment): array
    {
        $payment->loadMissing(['client', 'loan', 'collector', 'creator', 'allocations.installment']);

        $receivedAt = $payment->received_at?->timezone(config('app.timezone'));

        return [
            'receipt_number' => $payment->receipt_number,
            'received_at' => $receivedAt?->format('d/m/Y H:i'),
            'client' => $payment->client?->full_name,
            'phone' => $payment->client?->phone,
            'loan_number' => $payment->loan?->number,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency ?: 'NIO',
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'previous_balance' => (string) $payment->previous_balance,
            'new_balance' => (string) $payment->new_balance,
            'collector' => $payment->collector?->name ?? $payment->creator?->name,
            'installments' => $this->installmentLines($payment->allocations),
        ];
    }

    /**
     * @param  Collection<int, PaymentAllocation>  $allocations
     * @return list<array{label: string, number: int|null, due_date: string|null, amount: string, parts: list<array{component: string, amount: string}>}>
     */
    private function installmentLines(Collection $allocations): array
    {
        return $allocations
            ->groupBy(fn (PaymentAllocation $allocation) => $allocation->installment_id ?? 'loan')
            ->map(function (Collection $group) {
                $installment = $group->first()?->installment;
                $dueDate = $installment?->due_date;

                return [
                    'label' => $installment
                        ? 'Cuota '.$installment->number
                        : 'Saldo del crédito',
                    'number' => $installment?->number,
                    'due_date' => $dueDate?->format('d/m/Y'),
                    'amount' => $group->reduce(
                        fn (string $total, PaymentAllocation $allocation) => bcadd($total, (string) $allocation->amount, 2),
                        '0.00',
                    ),
                    'parts' => $group->map(fn (PaymentAllocation $allocation) => [
                        'component' => $allocation->component,
                        'amount' => (string) $allocation->amount,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
