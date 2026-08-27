<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\DelinquencyTrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DelinquencyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->firstOrFail();

        Loan::query()->with('installments')->each(function (Loan $loan): void {
            if ($loan->installments->isNotEmpty()) {
                return;
            }

            $this->createSchedule($loan);
        });

        $demoLoan = Loan::query()->with(['installments', 'payments'])->orderBy('id')->first();
        if ($demoLoan) {
            $this->seedPaidHistory($demoLoan, $actor);
        }

        app(DelinquencyTrackingService::class)->recalculateDueLoans(now(), ['trigger' => 'schedule']);
    }

    private function createSchedule(Loan $loan): void
    {
        $amount = (string) max(500, (int) ((float) $loan->principal / 20));
        foreach ([21, 14, 7] as $number => $daysAgo) {
            $this->createInstallment($loan, $number + 1, today()->subDays($daysAgo), $amount);
        }

        $this->createInstallment($loan, 4, today()->addWeek(), $amount);
    }

    private function createInstallment(Loan $loan, int $number, $dueDate, string $amount): LoanInstallment
    {
        return LoanInstallment::create([
            'loan_id' => $loan->id,
            'number' => $number,
            'due_date' => $dueDate,
            'principal_due' => $amount,
            'interest_due' => '0.00',
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ]);
    }

    private function seedPaidHistory(Loan $loan, User $actor): void
    {
        $loan->load('installments', 'payments');
        if ($loan->payments->isNotEmpty()) {
            return;
        }

        $first = $loan->installments->firstWhere('number', 1);
        $second = $loan->installments->firstWhere('number', 2);
        if (! $first || ! $second) {
            return;
        }

        $this->recordPayment($loan, $first, (string) $first->principal_due, $actor, now()->subDays(10), 'Pago completo de la cuota 1');
        $partial = bcdiv((string) $second->principal_due, '2', 2);
        $this->recordPayment($loan, $second, $partial, $actor, now()->subDays(3), 'Abono parcial a la cuota 2');
    }

    private function recordPayment(Loan $loan, LoanInstallment $installment, string $amount, User $actor, $receivedAt, string $notes): void
    {
        $paid = bcadd($installment->fresh()->amountPaid(), $amount, 2);
        $installment->update([
            'principal_paid' => $paid,
            'paid_amount' => $paid,
            'status' => bccomp($paid, $installment->amountDue(), 2) >= 0 ? 'paid' : $installment->status,
        ]);

        $payment = Payment::create([
            'idempotency_key' => (string) Str::uuid(),
            'receipt_number' => 'REC-DEMO-'.$installment->number.'-'.$loan->id,
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'collector_id' => $actor->id,
            'received_at' => $receivedAt,
            'amount' => $amount,
            'currency' => $loan->currency,
            'payment_method' => 'cash',
            'previous_balance' => $loan->outstanding_balance,
            'new_balance' => bcsub((string) $loan->outstanding_balance, $amount, 2),
            'status' => 'applied',
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'installment_id' => $installment->id,
            'component' => 'principal',
            'amount' => $amount,
            'application_order' => 1,
            'policy_snapshot' => ['source' => 'demo', 'note' => 'Abono de demostración; no aplica motor de asignación.'],
        ]);
    }
}
