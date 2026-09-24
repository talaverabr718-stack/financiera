<?php

namespace App\Services;

use App\Models\CollectionRecord;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CollectionPaymentCorrectionService
{
    private const COMPONENTS = [
        'principal' => ['paid' => 'principal_paid', 'balance' => 'principal_balance'],
        'interest' => ['paid' => 'interest_paid', 'balance' => 'interest_balance'],
        'fees' => ['paid' => 'fees_paid', 'balance' => 'fee_balance'],
        'delinquency' => ['paid' => 'delinquency_paid', 'balance' => 'delinquency_balance'],
    ];

    public function __construct(
        private DocumentSequenceService $sequences,
        private PaymentApplicationService $payments,
        private CollectionPaymentCorrectionAuthorizationService $authorizations,
        private AuditService $audit,
    ) {}

    public function correct(CollectionRecord $record, string $amount, string $reason, User $actor, int $authorizationId): Payment
    {
        return DB::transaction(function () use ($record, $amount, $reason, $actor, $authorizationId): Payment {
            $record = CollectionRecord::query()->lockForUpdate()->findOrFail($record->id);
            $authorization = $this->authorizations->lockForUse($record, $authorizationId);

            if ($record->outcome !== 'collected' || ! $record->payment_id) {
                throw ValidationException::withMessages([
                    'amount' => 'Solo se puede corregir una gestión con un pago aplicado.',
                ]);
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($record->payment_id);
            if ($payment->reversal()->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'Este pago ya fue revertido o corregido.',
                ]);
            }

            if ($record->correction()->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'Esta gestión ya tiene una corrección registrada.',
                ]);
            }

            if (bccomp($amount, (string) $payment->amount, 2) === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto correcto debe ser diferente al pago original.',
                ]);
            }

            $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);
            $availableAfterReversal = bcadd($loan->outstanding_balance, (string) $payment->amount, 2);
            if (bccomp($amount, $availableAfterReversal, 2) === 1) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto correcto no puede ser mayor al saldo restaurado del crédito (C$ '.number_format((float) $availableAfterReversal, 2).').',
                ]);
            }

            $this->restorePaymentBalances($payment, $loan);

            $reversal = PaymentReversal::create([
                'payment_id' => $payment->id,
                'number' => $this->sequences->next('payment_reversal', 'REV-'),
                'reason' => $reason,
                'authorized_by' => $actor->id,
                'created_by' => $actor->id,
                'reversed_at' => now(),
            ]);

            $replacement = CollectionRecord::create([
                'idempotency_key' => (string) Str::uuid(),
                'collection_route_stop_id' => $record->collection_route_stop_id,
                'client_id' => $record->client_id,
                'loan_id' => $record->loan_id,
                'correction_of_id' => $record->id,
                'correction_authorization_id' => $authorization->id,
                'collector_id' => $record->collector_id,
                'outcome' => 'collected',
                'amount' => $amount,
                'currency' => $record->currency,
                'payment_method' => $record->payment_method,
                'reference' => $record->reference,
                'notes' => $record->notes,
                'correction_reason' => $reason,
                'application_status' => 'pending',
                'recorded_at' => now(),
                'recorded_by' => $actor->id,
                'corrected_by' => $actor->id,
            ]);

            $replacementPayment = $this->payments->applyCollection($replacement->load('collector'));

            $this->authorizations->markUsed($authorization, $actor, $replacement->id, $replacementPayment->id);

            $this->audit->record(
                $record,
                'collection.payment.amount_corrected',
                $actor->id,
                ['amount' => (string) $payment->amount, 'receipt_number' => $payment->receipt_number],
                ['amount' => (string) $replacementPayment->amount, 'receipt_number' => $replacementPayment->receipt_number],
                $reason,
                ['reversal_id' => $reversal->id, 'replacement_record_id' => $replacement->id],
            );

            return $replacementPayment->fresh();
        });
    }

    private function restorePaymentBalances(Payment $payment, Loan $loan): void
    {
        $allocations = $payment->allocations()->orderByDesc('application_order')->lockForUpdate()->get();

        foreach ($allocations as $allocation) {
            $component = self::COMPONENTS[$allocation->component] ?? null;
            if (! $component) {
                throw ValidationException::withMessages([
                    'amount' => 'El pago contiene una aplicación que no puede revertirse automáticamente.',
                ]);
            }

            $loanColumn = $component['balance'];
            $restoredBalance = bcadd((string) $loan->{$loanColumn}, (string) $allocation->amount, 2);
            $loan->update([$loanColumn => $restoredBalance]);
            $loan->{$loanColumn} = $restoredBalance;

            if (! $allocation->installment_id) {
                continue;
            }

            $installment = LoanInstallment::query()->lockForUpdate()->findOrFail($allocation->installment_id);
            $paidColumn = $component['paid'];
            $restoredPaid = bcsub((string) $installment->{$paidColumn}, (string) $allocation->amount, 2);
            if (bccomp($restoredPaid, '0.00', 2) === -1) {
                throw ValidationException::withMessages([
                    'amount' => 'No fue posible restaurar los saldos del pago original.',
                ]);
            }

            $installment->{$paidColumn} = $restoredPaid;
            $paidAmount = bcadd((string) $installment->principal_paid, (string) $installment->interest_paid, 2);
            $paidAmount = bcadd($paidAmount, (string) $installment->fees_paid, 2);
            $paidAmount = bcadd($paidAmount, (string) $installment->delinquency_paid, 2);
            $installment->update([
                $paidColumn => $restoredPaid,
                'paid_amount' => $paidAmount,
                'status' => $installment->due_date->isBefore(today()) ? 'overdue' : 'pending',
            ]);
        }

        if ($loan->status === 'paid') {
            $loan->update(['status' => 'active', 'closed_at' => null]);
        }
    }
}
