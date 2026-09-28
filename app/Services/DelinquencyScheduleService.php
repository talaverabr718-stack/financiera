<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DelinquencyScheduleService
{
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
}
