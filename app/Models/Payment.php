<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'idempotency_key', 'receipt_number', 'client_id', 'loan_id', 'collector_id',
        'received_at', 'amount', 'currency', 'payment_method', 'reference',
        'previous_balance', 'new_balance', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'amount' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'new_balance' => 'decimal:2',
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

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('application_order');
    }

    public function reversal()
    {
        return $this->hasOne(PaymentReversal::class);
    }
}
