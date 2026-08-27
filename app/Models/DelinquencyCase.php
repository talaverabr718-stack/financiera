<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DelinquencyCase extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'client_id', 'loan_id', 'status', 'started_on', 'oldest_due_on',
        'last_calculated_on', 'resolved_on', 'current_days', 'total_days',
        'overdue_installment_count', 'overdue_balance', 'oldest_installment_id', 'active_guard',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'oldest_due_on' => 'date',
            'last_calculated_on' => 'date',
            'resolved_on' => 'date',
            'overdue_balance' => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function oldestInstallment()
    {
        return $this->belongsTo(LoanInstallment::class, 'oldest_installment_id');
    }

    public function items()
    {
        return $this->hasMany(DelinquencyCaseInstallment::class)->orderBy('due_date')->orderBy('installment_number');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Delinquency cases cannot be deleted.'));
    }
}
