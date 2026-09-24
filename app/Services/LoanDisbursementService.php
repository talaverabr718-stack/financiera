<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanDisbursementService
{
    public function __construct(private DocumentSequenceService $sequences) {}

    public function disburse(CreditApplication $application, array $data, int $userId): LoanDisbursement
    {
        try {
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

                Client::query()->lockForUpdate()->findOrFail($application->client_id);
                if (Loan::query()->where('client_id', $application->client_id)
                    ->whereIn('status', Loan::COLLECTIBLE_STATUSES)
                    ->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'disbursement' => 'Este cliente ya tiene un crédito activo o en mora. Debe finalizarlo antes de recibir otro crédito.',
                    ]);
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
                        'term_value' => $application->term_value,
                        'term_unit' => $application->term_unit,
                        'frequency' => $application->payment_frequency,
                        'interest_rate' => $application->interest_rate,
                        'interest_method' => $application->interest_method,
                        'first_payment_date' => $application->proposed_first_payment_date?->format('Y-m-d'),
                        'installment_amount' => $application->installment_amount,
                        'total_interest' => $application->total_interest,
                        'total_payable' => $application->total_payable,
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
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'loans_one_open_per_client_unique')
                || str_contains($message, 'loans.client_id, loans.open_guard')) {
                throw ValidationException::withMessages([
                    'disbursement' => 'Este cliente ya tiene un crédito activo o en mora. Debe finalizarlo antes de recibir otro crédito.',
                ]);
            }

            throw $exception;
        }
    }
}
