<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DelinquencyAccrual extends Model
{
    protected $fillable = [
        'idempotency_key', 'loan_id', 'installment_id', 'accrual_date',
        'base_amount', 'rate', 'method', 'days_overdue', 'amount',
        'policy_snapshot', 'status', 'reversal_of_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'policy_snapshot' => 'array',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delinquency accruals are immutable.'));
        static::deleting(fn () => throw new LogicException('Delinquency accruals cannot be deleted.'));
    }
}
