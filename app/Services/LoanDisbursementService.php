<?php

namespace App\Services;

use App\Models\CreditApplication;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanDisbursementService
{
    public function __construct(private DocumentSequenceService $sequences) {}

    public function disburse(CreditApplication $application, array $data, int $userId): LoanDisbursement
    {
        return DB::transaction(function () use ($application, $data, $userId) {
            if ($existing = LoanDisbursement::where('idempotency_key', $data['idempotency_key'])->first()) {
                if ($existing->credit_application_id !== $application->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave de operación ya fue utilizada en otro desembolso.']);
                }

                return $existing;
            }

            $application = CreditApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status !== 'approved' || ! $application->approved_amount) {
                throw ValidationException::withMessages(['disbursement' => 'Solo se puede desembolsar una solicitud aprobada con monto aprobado.']);
            }
            if ($application->loan()->exists()) {
                throw ValidationException::withMessages(['disbursement' => 'Esta solicitud ya tiene un préstamo generado.']);
            }

            $loan = Loan::create([
                'number' => $this->sequences->next('loan', 'PRE-'),
                'credit_application_id' => $application->id,
                'client_id' => $application->client_id,
                'seller_id' => $application->seller_id,
                'status' => 'active',
                'currency' => $application->currency,
                'principal' => $application->approved_amount,
                'principal_balance' => $application->approved_amount,
                'interest_balance' => 0,
                'fee_balance' => 0,
                'approved_terms' => [
                    'term' => $application->term,
                    'frequency' => $application->payment_frequency,
                    'interest_rate' => $application->interest_rate,
                    'interest_method' => $application->interest_method,
                    'first_payment_date' => $application->proposed_first_payment_date?->format('Y-m-d'),
                    'installment_amount' => $application->installment_amount,
                    'administrative_fee' => $application->administrative_fee,
                ],
                'disbursed_at' => $data['disbursed_at'],
            ]);

            $disbursement = LoanDisbursement::create([
                'idempotency_key' => $data['idempotency_key'],
                'number' => $this->sequences->next('loan_disbursement', 'DES-'),
                'credit_application_id' => $application->id,
                'loan_id' => $loan->id,
                'amount' => $application->approved_amount,
                'currency' => $application->currency,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'disbursed_at' => $data['disbursed_at'],
                'disbursed_by' => $userId,
            ]);

            $application->update(['status' => 'disbursed']);

            return $disbursement;
        });
    }
}
