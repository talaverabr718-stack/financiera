<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PaymentReversal extends Model
{
    protected $fillable = [
        'payment_id', 'number', 'reason', 'authorized_by', 'created_by', 'reversed_at',
    ];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment reversals are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment reversals cannot be deleted.'));
    }
}
