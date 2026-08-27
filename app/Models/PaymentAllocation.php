<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'installment_id', 'component', 'amount',
        'application_order', 'policy_snapshot',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'policy_snapshot' => 'array'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment allocations are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment allocations cannot be deleted.'));
    }
}
