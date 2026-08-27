<?php

namespace App\Services;

use App\Models\CollectionRecord;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentApplicationService
{
    private const COMPONENTS = [
        'principal' => ['paid' => 'principal_paid', 'due' => 'principal_due', 'balance' => 'principal_balance'],
        'interest' => ['paid' => 'interest_paid', 'due' => 'interest_due', 'balance' => 'interest_balance'],
        'fees' => ['paid' => 'fees_paid', 'due' => 'fees_due', 'balance' => 'fee_balance'],
        'delinquency' => ['paid' => 'delinquency_paid', 'due' => 'delinquency_due', 'balance' => 'delinquency_balance'],
    ];

    public function __construct(private DocumentSequenceService $sequences) {}

    public function applyCollection(CollectionRecord $record): Payment
    {
        if ($record->outcome !== 'collected') {
            throw ValidationException::withMessages(['outcome' => 'Solo un cobro recibido se aplica a cartera.']);
        }

        if ($record->payment_id) {
            return $record->payment;
        }

        return DB::transaction(function () use ($record): Payment {
            $record = CollectionRecord::query()->lockForUpdate()->findOrFail($record->id);
            $record->loadMissing('collector');
            if ($record->payment_id) {
                return $record->payment;
            }

            $loan = Loan::query()->lockForUpdate()->findOrFail($record->loan_id);
            $loan->load(['application.product', 'installments']);

            if (! $loan->isCollectible()) {
                throw ValidationException::withMessages(['loan_id' => 'El préstamo ya no admite abonos.']);
            }

            $amount = (string) $record->amount;
            if (bccomp($amount, $loan->outstanding_balance, 2) === 1) {
                throw ValidationException::withMessages([
                    'amount' => 'El abono no puede ser mayor al saldo pendiente del crédito (C$ '.number_format((float) $loan->outstanding_balance, 2).').',
                ]);
            }

            $order = $this->allocationOrder($loan);
            $previous = $loan->outstanding_balance;
            $payment = Payment::create([
                'idempotency_key' => (string) $record->idempotency_key,
                'receipt_number' => $this->sequences->next('payment', 'REC-'),
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'collector_id' => $record->collector?->user_id ?? $record->recorded_by,
                'received_at' => $record->recorded_at ?? now(),
                'amount' => $amount,
                'currency' => $loan->currency,
                'payment_method' => $record->payment_method,
                'reference' => $record->reference,
                'previous_balance' => $previous,
                'new_balance' => $previous,
                'status' => 'applied',
                'notes' => $record->notes,
                'created_by' => $record->recorded_by,
            ]);

            $remaining = $this->allocateToInstallments($payment, $loan, $order, $amount);
            $remaining = $this->allocateToLoanBalances($payment, $loan, $order, $remaining);
            if (bccomp($remaining, '0.00', 2) === 1) {
                throw ValidationException::withMessages(['amount' => 'No fue posible aplicar el abono completo al crédito.']);
            }

            $loan->refresh();
            $payment->update(['new_balance' => $loan->outstanding_balance]);

            if (bccomp($loan->outstanding_balance, '0.00', 2) === 0) {
                $loan->update(['status' => 'paid', 'closed_at' => now()]);
            }

            $record->update([
                'payment_id' => $payment->id,
                'application_status' => 'applied',
            ]);

            return $payment->fresh();
        });
    }

    /**
     * @return list<string>
     */
    private function allocationOrder(Loan $loan): array
    {
        $order = array_values(array_filter(
            $loan->application?->product?->payment_allocation_order
                ?? config('financial.payment_allocation_order')
                ?? []
        ));

        $allowed = array_keys(self::COMPONENTS);
        $order = array_values(array_unique(array_intersect($order, $allowed)));

        if (count($order) !== 4) {
            throw ValidationException::withMessages([
                'loan_id' => 'Configura el orden de aplicación de pagos en el producto crediticio antes de registrar el abono.',
            ]);
        }

        return $order;
    }

    /**
     * @param  list<string>  $order
     */
    private function allocateToInstallments(Payment $payment, Loan $loan, array $order, string $remaining): string
    {
        $installments = $loan->installments
            ->filter(fn (LoanInstallment $item) => ! $item->isExcludedFromCollection() && bccomp($item->outstandingAmount(), '0.00', 2) === 1)
            ->sortBy(fn (LoanInstallment $item) => [$item->due_date->toDateString(), $item->number])
            ->values();

        $sequence = $payment->allocations()->count();

        foreach ($installments as $installment) {
            if (bccomp($remaining, '0.00', 2) !== 1) {
                break;
            }

            $installment = LoanInstallment::query()->lockForUpdate()->findOrFail($installment->id);
            $updates = [];

            foreach ($order as $component) {
                if (bccomp($remaining, '0.00', 2) !== 1) {
                    break;
                }

                $map = self::COMPONENTS[$component];
                $outstanding = bcsub((string) $installment->{$map['due']}, (string) $installment->{$map['paid']}, 2);
                if (bccomp($outstanding, '0.00', 2) !== 1) {
                    continue;
                }

                $applied = bccomp($remaining, $outstanding, 2) === 1 ? $outstanding : $remaining;
                $sequence++;
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'installment_id' => $installment->id,
                    'component' => $component,
                    'amount' => $applied,
                    'application_order' => $sequence,
                    'policy_snapshot' => [
                        'source' => 'collection',
                        'allocation_order' => $order,
                    ],
                ]);

                $updates[$map['paid']] = bcadd((string) $installment->{$map['paid']}, $applied, 2);
                $installment->{$map['paid']} = $updates[$map['paid']];
                $this->reduceLoanBalance($loan, $map['balance'], $applied);
                $remaining = bcsub($remaining, $applied, 2);
            }

            if ($updates !== []) {
                $updates['paid_amount'] = $installment->amountPaid();
                $updates['status'] = bccomp($installment->outstandingAmount(), '0.00', 2) === 0 ? 'paid' : $installment->status;
                $installment->update($updates);
            }
        }

        return $remaining;
    }

    /**
     * @param  list<string>  $order
     */
    private function allocateToLoanBalances(Payment $payment, Loan $loan, array $order, string $remaining): string
    {
        $sequence = $payment->allocations()->count();

        foreach ($order as $component) {
            if (bccomp($remaining, '0.00', 2) !== 1) {
                break;
            }

            $map = self::COMPONENTS[$component];
            $balance = (string) $loan->{$map['balance']};
            if (bccomp($balance, '0.00', 2) !== 1) {
                continue;
            }

            $applied = bccomp($remaining, $balance, 2) === 1 ? $balance : $remaining;
            $sequence++;
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'installment_id' => null,
                'component' => $component,
                'amount' => $applied,
                'application_order' => $sequence,
                'policy_snapshot' => [
                    'source' => 'collection',
                    'allocation_order' => $order,
                    'target' => 'loan_balance',
                ],
            ]);
            $this->reduceLoanBalance($loan, $map['balance'], $applied);
            $remaining = bcsub($remaining, $applied, 2);
        }

        return $remaining;
    }

    private function reduceLoanBalance(Loan $loan, string $column, string $amount): void
    {
        $next = bcsub((string) $loan->{$column}, $amount, 2);
        if (bccomp($next, '0.00', 2) === -1) {
            $next = '0.00';
        }

        $loan->update([$column => $next]);
        $loan->{$column} = $next;
    }
}
