<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    public const EXCLUDED_STATUSES = ['cancelled', 'refinanced', 'voided', 'waived', 'written_off'];

    protected $fillable = ['loan_id', 'number', 'due_date', 'principal_due', 'interest_due', 'fees_due', 'delinquency_due', 'principal_paid', 'interest_paid', 'fees_paid', 'delinquency_paid', 'paid_amount', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'principal_due' => 'decimal:2', 'interest_due' => 'decimal:2', 'fees_due' => 'decimal:2', 'delinquency_due' => 'decimal:2', 'principal_paid' => 'decimal:2', 'interest_paid' => 'decimal:2', 'fees_paid' => 'decimal:2', 'delinquency_paid' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function delinquencyAccruals()
    {
        return $this->hasMany(DelinquencyAccrual::class, 'installment_id');
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'installment_id');
    }

    public function delinquencyCaseItems()
    {
        return $this->hasMany(DelinquencyCaseInstallment::class, 'loan_installment_id');
    }

    public function amountDue(): string
    {
        $due = bcadd((string) $this->principal_due, (string) $this->interest_due, 2);
        $due = bcadd($due, (string) $this->fees_due, 2);

        return bcadd($due, (string) $this->delinquency_due, 2);
    }

    public function amountPaid(): string
    {
        $paid = bcadd((string) $this->principal_paid, (string) $this->interest_paid, 2);
        $paid = bcadd($paid, (string) $this->fees_paid, 2);
        $paid = bcadd($paid, (string) $this->delinquency_paid, 2);

        return bccomp((string) $this->paid_amount, $paid, 2) > 0 ? (string) $this->paid_amount : $paid;
    }

    public function outstandingAmount(): string
    {
        $outstanding = bcsub($this->amountDue(), $this->amountPaid(), 2);

        return bccomp($outstanding, '0.00', 2) > 0 ? $outstanding : '0.00';
    }

    public function isExcludedFromCollection(): bool
    {
        return in_array($this->status, self::EXCLUDED_STATUSES, true);
    }

    public function isOverdueOn(CarbonInterface $asOf): bool
    {
        if ($this->isExcludedFromCollection() || bccomp($this->outstandingAmount(), '0.00', 2) !== 1) {
            return false;
        }

        return $this->calendarDate($asOf)->gt($this->calendarDate($this->due_date));
    }

    public function daysOverdueOn(CarbonInterface $asOf): int
    {
        if (! $this->isOverdueOn($asOf)) {
            return 0;
        }

        return (int) $this->calendarDate($this->due_date)->diffInDays($this->calendarDate($asOf), true);
    }

    public function calendarDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->timezone(config('app.timezone'))->toDateString(), config('app.timezone'))->startOfDay();
    }
}
