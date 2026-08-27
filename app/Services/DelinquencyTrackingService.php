<?php

namespace App\Services;

use App\Models\DelinquencyAccrual;
use App\Models\DelinquencyCase;
use App\Models\DelinquencyCaseInstallment;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DelinquencyTrackingService
{
    public function __construct(
        private DocumentSequenceService $sequences,
        private AuditService $audit,
    ) {}

    public function calendarDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->timezone(config('app.timezone'))->toDateString(), config('app.timezone'))->startOfDay();
    }

    public function overdueInstallments(Loan $loan, CarbonInterface $asOf): Collection
    {
        if (! $loan->isCollectible()) {
            return collect();
        }

        return $loan->installments
            ->filter(fn (LoanInstallment $installment) => $installment->isOverdueOn($asOf))
            ->sortBy(fn (LoanInstallment $installment) => [$installment->due_date->toDateString(), $installment->number])
            ->values();
    }

    public function summarizeLoan(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOf = $this->calendarDate($asOf ?? now());
        $loan->loadMissing(['installments.paymentAllocations.payment.reversal', 'activeDelinquencyCase.items', 'payments.allocations.installment', 'payments.creator', 'payments.reversal']);
        $overdue = $this->overdueInstallments($loan, $asOf);
        $oldest = $overdue->first();
        $case = $loan->activeDelinquencyCase;

        return [
            'in_arrears' => $overdue->isNotEmpty(),
            'case' => $case,
            'code' => $case?->code,
            'current_days' => $oldest ? $oldest->daysOverdueOn($asOf) : 0,
            'started_on' => $oldest ? $this->startedOn($oldest, $asOf) : null,
            'overdue_count' => $overdue->count(),
            'overdue_balance' => $overdue->reduce(fn (string $total, LoanInstallment $installment) => bcadd($total, $installment->outstandingAmount(), 2), '0.00'),
            'as_of' => $asOf,
            'timezone' => config('app.timezone'),
            'oldest_due_on' => $oldest ? $this->calendarDate($oldest->due_date) : null,
            'daily_rate' => $loan->delinquency_daily_rate,
            'monetary_delinquency_enabled' => filled($loan->delinquency_daily_rate) || filled(config('financial.delinquency_method')),
            'installments' => $overdue->map(fn (LoanInstallment $installment) => $this->explainInstallment($installment, $asOf, $loan)),
            'ledger' => $loan->installments->sortBy('number')->values()->map(fn (LoanInstallment $installment) => $this->explainInstallment($installment, $asOf, $loan)),
            'paid_history' => $this->paidHistory($loan),
        ];
    }

    public function explainInstallment(LoanInstallment $installment, ?CarbonInterface $asOf = null, ?Loan $loan = null): array
    {
        $asOf = $this->calendarDate($asOf ?? now());
        $due = $this->calendarDate($installment->due_date);
        $loan ??= $installment->loan;
        $history = $this->installmentHistory($installment, $due);
        $outstanding = $installment->outstandingAmount();
        $paid = $installment->amountPaid();
        $paidInFull = bccomp($outstanding, '0.00', 2) === 0 && bccomp($paid, '0.00', 2) === 1;
        $lastActive = $history->filter(fn (array $row) => $row['status'] !== 'reversed')->last();
        $paidOn = $lastActive['date'] ?? ($paidInFull ? $installment->updated_at : null);
        $daysOverdue = $installment->daysOverdueOn($asOf);

        [$settlement, $settlementLabel] = $this->settlement($installment, $asOf, $due, $paidInFull, $paid, $paidOn);

        return [
            'id' => $installment->id,
            'number' => $installment->number,
            'due_date' => $installment->due_date,
            'status' => $installment->status,
            'principal_due' => (string) $installment->principal_due,
            'interest_due' => (string) $installment->interest_due,
            'fees_due' => (string) $installment->fees_due,
            'delinquency_due' => (string) $installment->delinquency_due,
            'principal_paid' => (string) $installment->principal_paid,
            'interest_paid' => (string) $installment->interest_paid,
            'fees_paid' => (string) $installment->fees_paid,
            'delinquency_paid' => (string) $installment->delinquency_paid,
            'amount_due' => $installment->amountDue(),
            'amount_paid' => $paid,
            'outstanding_amount' => $outstanding,
            'is_overdue' => $installment->isOverdueOn($asOf),
            'days_overdue' => $daysOverdue,
            'mora_label' => $daysOverdue > 0 ? $daysOverdue.' '.($daysOverdue === 1 ? 'día' : 'días') : '—',
            'mora_amount' => (string) $installment->delinquency_due,
            'mora_outstanding' => $this->moraOutstanding($installment),
            'settlement' => $settlement,
            'settlement_label' => $settlementLabel,
            'paid_on' => $paidOn,
            'history' => $history,
            'as_of' => $asOf,
            'due_on' => $due,
            'loan_id' => $loan?->id ?? $installment->loan_id,
            'loan_number' => $loan?->number,
            'currency' => $loan?->currency ?? 'NIO',
            'rule' => $asOf->gt($due)
                ? 'La fecha de cálculo es posterior al vencimiento.'
                : 'Aún no vence: el mismo día de vencimiento cuenta 0 días de mora.',
        ];
    }

    public function paidHistory(Loan $loan): Collection
    {
        $loan->loadMissing(['payments.allocations.installment', 'payments.creator', 'payments.reversal', 'installments.paymentAllocations']);
        $receipts = $loan->payments->sortByDesc('received_at')->values()->map(function ($payment) {
            return [
                'source' => 'payment',
                'date' => $payment->received_at,
                'title' => $payment->receipt_number,
                'amount' => $payment->amount,
                'status' => $payment->reversal ? 'reversed' : $payment->status,
                'method' => $payment->payment_method,
                'actor' => $payment->creator?->name,
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'installment' => $allocation->installment?->number,
                    'component' => $allocation->component,
                    'component_label' => $this->componentLabel($allocation->component),
                    'amount' => $allocation->amount,
                ]),
            ];
        });

        $withoutReceipt = $loan->installments
            ->filter(fn (LoanInstallment $installment) => bccomp($installment->amountPaid(), '0.00', 2) === 1 && $installment->paymentAllocations->isEmpty())
            ->map(fn (LoanInstallment $installment) => [
                'source' => 'installment',
                'date' => $installment->updated_at,
                'title' => 'Cuota '.$installment->number,
                'amount' => $installment->amountPaid(),
                'status' => $installment->status,
                'method' => 'Registrado en la cuota',
                'actor' => null,
                'allocations' => collect([
                    ['installment' => $installment->number, 'component' => 'principal', 'amount' => $installment->principal_paid],
                    ['installment' => $installment->number, 'component' => 'interest', 'amount' => $installment->interest_paid],
                    ['installment' => $installment->number, 'component' => 'fees', 'amount' => $installment->fees_paid],
                    ['installment' => $installment->number, 'component' => 'delinquency', 'amount' => $installment->delinquency_paid],
                ])->filter(fn (array $row) => bccomp((string) $row['amount'], '0.00', 2) === 1)
                    ->map(fn (array $row) => $row + ['component_label' => $this->componentLabel($row['component'])])
                    ->values(),
            ]);

        return $receipts->concat($withoutReceipt)->sortByDesc(fn (array $row) => optional($row['date'])->timestamp ?? 0)->values();
    }

    public function summarizeClient($client, ?CarbonInterface $asOf = null): array
    {
        $client->loadMissing(['loans.installments.paymentAllocations.payment.reversal', 'loans.activeDelinquencyCase']);
        $summaries = $client->loans->map(fn (Loan $loan) => $this->summarizeLoan($loan, $asOf));
        $inArrears = $summaries->firstWhere('in_arrears', true);

        return [
            'in_arrears' => (bool) $inArrears,
            'summaries' => $summaries,
            'ledger' => $summaries->flatMap(fn (array $summary) => $summary['ledger'])->values(),
            'active_cases' => $client->loans->map->activeDelinquencyCase->filter()->values(),
        ];
    }

    public function recalculateLoan(Loan $loan, ?CarbonInterface $asOf = null, array $context = []): ?DelinquencyCase
    {
        $asOf = $this->calendarDate($asOf ?? now());
        $trigger = $context['trigger'] ?? 'system';
        $actorId = $context['actor_id'] ?? auth()->id();

        return DB::transaction(function () use ($loan, $asOf, $trigger, $actorId, $context) {
            $loan = Loan::query()->with('installments')->lockForUpdate()->findOrFail($loan->id);
            $rate = $this->resolveDailyRate($loan, $context);
            if ($rate !== null) {
                $this->applyDailyPercentageCharges($loan, $asOf, $rate, $actorId);
                $loan->unsetRelation('installments');
                $loan->load('installments');
            }

            $overdue = $this->overdueInstallments($loan, $asOf);
            $active = DelinquencyCase::query()->where('loan_id', $loan->id)->where('status', DelinquencyCase::STATUS_ACTIVE)->lockForUpdate()->first();

            $this->syncInstallmentStatuses($loan, $asOf);

            if ($overdue->isEmpty()) {
                $resolved = null;
                if ($active) {
                    $this->resolveCase($active, $asOf, $actorId, $trigger, $context['reason'] ?? null);
                    $resolved = $active;
                }
                $this->syncLoanStatus($loan, false, (bool) $resolved);

                return $active?->fresh(['items']);
            }

            if ($active) {
                $this->refreshCase($active, $loan, $overdue, $asOf, $actorId, $trigger);

                return $active->fresh(['items']);
            }

            $reactivatable = $this->findReactivatableCase($loan, $overdue);
            if ($reactivatable) {
                $this->reactivateCase($reactivatable, $loan, $overdue, $asOf, $actorId, $trigger);

                return $reactivatable->fresh(['items']);
            }

            return $this->openCase($loan, $overdue, $asOf, $actorId, $trigger);
        });
    }

    public function recalculateDueLoans(?CarbonInterface $asOf = null, array $context = []): array
    {
        $asOf = $this->calendarDate($asOf ?? now());
        $processed = 0;
        $failed = 0;

        Loan::query()
            ->where(function ($query) {
                $query->whereIn('status', Loan::COLLECTIBLE_STATUSES)
                    ->orWhereHas('delinquencyCases', fn ($cases) => $cases->where('status', DelinquencyCase::STATUS_ACTIVE));
            })
            ->orderBy('id')
            ->chunkById(100, function ($loans) use ($asOf, $context, &$processed, &$failed) {
                foreach ($loans as $loan) {
                    try {
                        $this->recalculateLoan($loan, $asOf, $context + ['trigger' => $context['trigger'] ?? 'schedule']);
                        $processed++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                    }
                }
            });

        return compact('processed', 'failed');
    }

    public function cancel(DelinquencyCase $case, string $reason, ?int $actorId = null): DelinquencyCase
    {
        return DB::transaction(function () use ($case, $reason, $actorId) {
            $case = DelinquencyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($case->status === DelinquencyCase::STATUS_CANCELLED) {
                return $case;
            }

            $before = $case->only(['status', 'active_guard', 'resolved_on']);
            $case->update([
                'status' => DelinquencyCase::STATUS_CANCELLED,
                'active_guard' => null,
                'resolved_on' => $this->calendarDate(now())->toDateString(),
                'total_days' => $case->current_days,
            ]);
            $this->syncLoanStatus($case->loan()->lockForUpdate()->firstOrFail(), DelinquencyCase::query()->where('loan_id', $case->loan_id)->where('status', DelinquencyCase::STATUS_ACTIVE)->exists(), true);
            $this->audit->record($case, 'delinquency.cancelled', $actorId, $before, $case->fresh()->only(['status', 'active_guard', 'resolved_on']), $reason, ['trigger' => 'manual']);

            return $case->fresh(['items']);
        });
    }

    public function reopen(DelinquencyCase $case, string $reason, ?int $actorId = null, ?CarbonInterface $asOf = null): DelinquencyCase
    {
        $asOf = $this->calendarDate($asOf ?? now());

        return DB::transaction(function () use ($case, $reason, $actorId, $asOf) {
            $case = DelinquencyCase::query()->lockForUpdate()->findOrFail($case->id);
            $loan = Loan::query()->with('installments')->lockForUpdate()->findOrFail($case->loan_id);
            if (DelinquencyCase::query()->where('loan_id', $loan->id)->where('status', DelinquencyCase::STATUS_ACTIVE)->whereKeyNot($case->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'Ya existe un expediente de mora activo para este crédito.']);
            }

            $overdue = $this->overdueInstallments($loan, $asOf);
            if ($overdue->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'No hay cuotas vencidas pendientes para reactivar este expediente.']);
            }

            $this->reactivateCase($case, $loan, $overdue, $asOf, $actorId, 'manual', $reason);

            return $case->fresh(['items']);
        });
    }

    private function openCase(Loan $loan, Collection $overdue, CarbonImmutable $asOf, ?int $actorId, string $trigger): DelinquencyCase
    {
        $snapshot = $this->snapshot($overdue, $asOf);
        $case = DelinquencyCase::create([
            'code' => $this->sequences->next('delinquency_case', 'MORA-', 3),
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'status' => DelinquencyCase::STATUS_ACTIVE,
            'started_on' => $snapshot['started_on'],
            'oldest_due_on' => $snapshot['oldest_due_on'],
            'last_calculated_on' => $asOf->toDateString(),
            'resolved_on' => null,
            'current_days' => $snapshot['current_days'],
            'total_days' => $snapshot['current_days'],
            'overdue_installment_count' => $snapshot['count'],
            'overdue_balance' => $snapshot['balance'],
            'oldest_installment_id' => $snapshot['oldest_installment_id'],
            'active_guard' => 'ACTIVE',
        ]);
        $this->syncItems($case, $overdue, $asOf);
        $this->syncLoanStatus($loan, true);
        $this->audit->record($case, 'delinquency.opened', $actorId, [], $case->only(['code', 'status', 'started_on', 'current_days', 'overdue_balance']), null, ['trigger' => $trigger]);

        return $case->fresh(['items']);
    }

    private function refreshCase(DelinquencyCase $case, Loan $loan, Collection $overdue, CarbonImmutable $asOf, ?int $actorId, string $trigger): void
    {
        $before = $case->only(['started_on', 'oldest_due_on', 'current_days', 'overdue_installment_count', 'overdue_balance', 'oldest_installment_id']);
        $snapshot = $this->snapshot($overdue, $asOf);
        $case->update([
            'started_on' => $snapshot['started_on'],
            'oldest_due_on' => $snapshot['oldest_due_on'],
            'last_calculated_on' => $asOf->toDateString(),
            'current_days' => $snapshot['current_days'],
            'total_days' => $snapshot['current_days'],
            'overdue_installment_count' => $snapshot['count'],
            'overdue_balance' => $snapshot['balance'],
            'oldest_installment_id' => $snapshot['oldest_installment_id'],
            'status' => DelinquencyCase::STATUS_ACTIVE,
            'active_guard' => 'ACTIVE',
            'resolved_on' => null,
        ]);
        $this->syncItems($case, $overdue, $asOf);
        $this->syncLoanStatus($loan, true);

        if ($before !== $case->fresh()->only(array_keys($before)) && in_array($trigger, ['manual', 'payment', 'reversal'], true)) {
            $this->audit->record($case, 'delinquency.recalculated', $actorId, $before, $case->only(array_keys($before)), null, ['trigger' => $trigger]);
        }
    }

    private function resolveCase(DelinquencyCase $case, CarbonImmutable $asOf, ?int $actorId, string $trigger, ?string $reason = null): void
    {
        $before = $case->only(['status', 'resolved_on', 'current_days', 'total_days', 'active_guard']);
        $case->update([
            'status' => DelinquencyCase::STATUS_RESOLVED,
            'resolved_on' => $asOf->toDateString(),
            'last_calculated_on' => $asOf->toDateString(),
            'total_days' => $case->current_days,
            'overdue_installment_count' => 0,
            'overdue_balance' => '0.00',
            'active_guard' => null,
        ]);
        $this->audit->record($case, 'delinquency.resolved', $actorId, $before, $case->fresh()->only(array_keys($before)), $reason, ['trigger' => $trigger]);
    }

    private function reactivateCase(DelinquencyCase $case, Loan $loan, Collection $overdue, CarbonImmutable $asOf, ?int $actorId, string $trigger, ?string $reason = null): void
    {
        $before = $case->only(['status', 'resolved_on', 'active_guard', 'current_days']);
        $snapshot = $this->snapshot($overdue, $asOf);
        $case->update([
            'status' => DelinquencyCase::STATUS_ACTIVE,
            'started_on' => $snapshot['started_on'],
            'oldest_due_on' => $snapshot['oldest_due_on'],
            'last_calculated_on' => $asOf->toDateString(),
            'resolved_on' => null,
            'current_days' => $snapshot['current_days'],
            'total_days' => $snapshot['current_days'],
            'overdue_installment_count' => $snapshot['count'],
            'overdue_balance' => $snapshot['balance'],
            'oldest_installment_id' => $snapshot['oldest_installment_id'],
            'active_guard' => 'ACTIVE',
        ]);
        $this->syncItems($case, $overdue, $asOf);
        $this->syncLoanStatus($loan, true);
        $this->audit->record($case, 'delinquency.reactivated', $actorId, $before, $case->fresh()->only(array_keys($before)), $reason, ['trigger' => $trigger]);
    }

    private function findReactivatableCase(Loan $loan, Collection $overdue): ?DelinquencyCase
    {
        $overdueIds = $overdue->pluck('id');
        $resolved = DelinquencyCase::query()
            ->where('loan_id', $loan->id)
            ->where('status', DelinquencyCase::STATUS_RESOLVED)
            ->with('items')
            ->latest('resolved_on')
            ->latest('id')
            ->lockForUpdate()
            ->get();

        return $resolved->first(fn (DelinquencyCase $case) => $case->items->pluck('loan_installment_id')->intersect($overdueIds)->isNotEmpty());
    }

    private function snapshot(Collection $overdue, CarbonImmutable $asOf): array
    {
        $oldest = $overdue->first();

        return [
            'oldest_installment_id' => $oldest->id,
            'oldest_due_on' => $this->calendarDate($oldest->due_date)->toDateString(),
            'started_on' => $this->startedOn($oldest, $asOf)->toDateString(),
            'current_days' => $oldest->daysOverdueOn($asOf),
            'count' => $overdue->count(),
            'balance' => $overdue->reduce(fn (string $total, LoanInstallment $installment) => bcadd($total, $installment->outstandingAmount(), 2), '0.00'),
        ];
    }

    private function resolveDailyRate(Loan $loan, array $context): ?string
    {
        if (array_key_exists('daily_rate', $context) && $context['daily_rate'] !== null && $context['daily_rate'] !== '') {
            $rate = number_format((float) $context['daily_rate'], 6, '.', '');
            $loan->update(['delinquency_daily_rate' => $rate]);

            return $rate;
        }

        if ($loan->delinquency_daily_rate === null) {
            return null;
        }

        return number_format((float) $loan->delinquency_daily_rate, 6, '.', '');
    }

    private function applyDailyPercentageCharges(Loan $loan, CarbonImmutable $asOf, string $dailyRate, ?int $actorId): void
    {
        foreach ($loan->installments as $installment) {
            if ($installment->isExcludedFromCollection()) {
                continue;
            }

            $days = $installment->daysOverdueOn($asOf);
            $base = $this->moraBaseAmount($installment);
            $charge = $this->dailyPercentageCharge($base, $dailyRate, $days);

            if (bccomp($base, '0.00', 2) !== 1) {
                $charge = (string) $installment->delinquency_due;
            }

            if (bccomp((string) $installment->delinquency_due, $charge, 2) !== 0) {
                $installment->update(['delinquency_due' => $charge]);
                $installment->delinquency_due = $charge;
            }

            if ($actorId && $days > 0 && bccomp($charge, '0.00', 2) === 1) {
                $this->recordAccrual($loan, $installment, $asOf, $base, $dailyRate, $days, $charge, $actorId);
            }
        }

        $balance = $loan->installments->reduce(
            fn (string $total, LoanInstallment $installment) => bcadd($total, $this->moraOutstanding($installment), 2),
            '0.00'
        );
        $loan->update(['delinquency_balance' => $balance]);
    }

    private function moraBaseAmount(LoanInstallment $installment): string
    {
        $due = bcadd(bcadd((string) $installment->principal_due, (string) $installment->interest_due, 2), (string) $installment->fees_due, 2);
        $paid = bcadd(bcadd((string) $installment->principal_paid, (string) $installment->interest_paid, 2), (string) $installment->fees_paid, 2);
        $base = bcsub($due, $paid, 2);

        return bccomp($base, '0.00', 2) === 1 ? $base : '0.00';
    }

    private function moraOutstanding(LoanInstallment $installment): string
    {
        $outstanding = bcsub((string) $installment->delinquency_due, (string) $installment->delinquency_paid, 2);

        return bccomp($outstanding, '0.00', 2) === 1 ? $outstanding : '0.00';
    }

    private function dailyPercentageCharge(string $base, string $dailyRatePercent, int $days): string
    {
        if ($days < 1 || bccomp($base, '0.00', 2) !== 1) {
            return '0.00';
        }

        $factor = bcdiv($dailyRatePercent, '100', 10);

        return bcmul(bcmul($base, $factor, 8), (string) $days, 2);
    }

    private function recordAccrual(
        Loan $loan,
        LoanInstallment $installment,
        CarbonImmutable $asOf,
        string $base,
        string $dailyRate,
        int $days,
        string $amount,
        int $actorId,
    ): void {
        $current = DelinquencyAccrual::query()
            ->where('installment_id', $installment->id)
            ->where('status', 'posted')
            ->whereDoesntHave('reversal')
            ->latest('id')
            ->first();

        if ($current
            && bccomp((string) $current->amount, $amount, 2) === 0
            && bccomp((string) $current->rate, $dailyRate, 6) === 0
            && (int) $current->days_overdue === $days
        ) {
            return;
        }

        if ($current) {
            DelinquencyAccrual::create([
                'idempotency_key' => (string) Str::uuid(),
                'loan_id' => $loan->id,
                'installment_id' => $installment->id,
                'accrual_date' => $asOf->toDateString(),
                'base_amount' => $current->base_amount,
                'rate' => $current->rate,
                'method' => $current->method,
                'days_overdue' => $current->days_overdue,
                'amount' => $current->amount,
                'policy_snapshot' => ['action' => 'reversal', 'of' => $current->id],
                'status' => 'reversed',
                'reversal_of_id' => $current->id,
                'created_by' => $actorId,
            ]);
        }

        DelinquencyAccrual::create([
            'idempotency_key' => (string) Str::uuid(),
            'loan_id' => $loan->id,
            'installment_id' => $installment->id,
            'accrual_date' => $asOf->toDateString(),
            'base_amount' => $base,
            'rate' => $dailyRate,
            'method' => 'daily_percentage',
            'days_overdue' => $days,
            'amount' => $amount,
            'policy_snapshot' => [
                'formula' => 'saldo_cuota × (% / 100) × días',
                'daily_rate' => $dailyRate,
            ],
            'status' => 'posted',
            'created_by' => $actorId,
        ]);
    }

    private function installmentHistory(LoanInstallment $installment, CarbonImmutable $due): Collection
    {
        $installment->loadMissing(['paymentAllocations.payment.reversal']);

        $fromReceipts = $installment->paymentAllocations
            ->filter(fn ($allocation) => $allocation->payment)
            ->map(function ($allocation) use ($due) {
                $receivedAt = $allocation->payment->received_at;
                $reversed = (bool) $allocation->payment->reversal;
                $onTime = $receivedAt && ! $reversed
                    ? $this->calendarDate($receivedAt)->lte($due)
                    : null;

                return [
                    'date' => $receivedAt,
                    'amount' => $allocation->amount,
                    'title' => $allocation->payment->receipt_number,
                    'status' => $reversed ? 'reversed' : $allocation->payment->status,
                    'on_time' => $onTime,
                    'timing_label' => $reversed ? 'Anulado' : ($onTime === true ? 'A tiempo' : ($onTime === false ? 'Tarde' : '—')),
                ];
            })
            ->sortBy(fn (array $row) => optional($row['date'])->timestamp ?? 0)
            ->values();

        if ($fromReceipts->isNotEmpty()) {
            return $fromReceipts;
        }

        if (bccomp($installment->amountPaid(), '0.00', 2) !== 1) {
            return collect();
        }

        $paidOn = $installment->updated_at;
        $onTime = $paidOn ? $this->calendarDate($paidOn)->lte($due) : null;

        return collect([[
            'date' => $paidOn,
            'amount' => $installment->amountPaid(),
            'title' => 'Abono en cuota',
            'status' => $installment->status,
            'on_time' => $onTime,
            'timing_label' => $onTime === true ? 'A tiempo' : ($onTime === false ? 'Tarde' : '—'),
        ]]);
    }

    private function settlement(LoanInstallment $installment, CarbonImmutable $asOf, CarbonImmutable $due, bool $paidInFull, string $paid, mixed $paidOn): array
    {
        if ($installment->isExcludedFromCollection()) {
            return ['excluded', 'No cobrable'];
        }

        if ($paidInFull) {
            $onTime = $paidOn ? $this->calendarDate($paidOn)->lte($due) : true;

            return $onTime ? ['on_time', 'A tiempo'] : ['late', 'Pagada tarde'];
        }

        if ($installment->isOverdueOn($asOf)) {
            return ['overdue', 'En mora'];
        }

        if (bccomp($paid, '0.00', 2) === 1) {
            return ['partial', 'Abono parcial'];
        }

        return $asOf->lte($due) ? ['pending', 'Por vencer'] : ['pending', 'Pendiente'];
    }

    private function componentLabel(string $component): string
    {
        return [
            'principal' => 'Principal',
            'interest' => 'Interés',
            'fees' => 'Cargos',
            'delinquency' => 'Cargo por mora',
        ][$component] ?? $component;
    }

    private function startedOn(LoanInstallment $oldest, CarbonImmutable $asOf): CarbonImmutable
    {
        $start = $this->calendarDate($oldest->due_date)->addDay();

        return $start->greaterThan($asOf) ? $asOf : $start;
    }

    private function syncItems(DelinquencyCase $case, Collection $overdue, CarbonImmutable $asOf): void
    {
        $keep = [];
        foreach ($overdue as $installment) {
            $keep[] = $installment->id;
            DelinquencyCaseInstallment::query()->updateOrCreate(
                ['delinquency_case_id' => $case->id, 'loan_installment_id' => $installment->id],
                [
                    'installment_number' => $installment->number,
                    'due_date' => $this->calendarDate($installment->due_date)->toDateString(),
                    'amount_due' => $installment->amountDue(),
                    'amount_paid' => $installment->amountPaid(),
                    'outstanding_amount' => $installment->outstandingAmount(),
                    'days_overdue' => $installment->daysOverdueOn($asOf),
                ]
            );
        }

        DelinquencyCaseInstallment::query()
            ->where('delinquency_case_id', $case->id)
            ->whereNotIn('loan_installment_id', $keep)
            ->delete();
    }

    private function syncInstallmentStatuses(Loan $loan, CarbonImmutable $asOf): void
    {
        foreach ($loan->installments as $installment) {
            if ($installment->isExcludedFromCollection()) {
                continue;
            }

            $next = 'pending';
            if (bccomp($installment->outstandingAmount(), '0.00', 2) === 0) {
                $next = 'paid';
            } elseif ($installment->isOverdueOn($asOf)) {
                $next = 'overdue';
            }

            if ($installment->status !== $next) {
                $installment->update(['status' => $next]);
            }
        }
    }

    private function syncLoanStatus(Loan $loan, bool $inArrears, bool $fromCaseChange = false): void
    {
        if ($inArrears && $loan->status === 'active') {
            $loan->update(['status' => 'delinquent']);
        }

        if (! $inArrears && $fromCaseChange && $loan->status === 'delinquent') {
            $loan->update(['status' => 'active']);
        }
    }
}
