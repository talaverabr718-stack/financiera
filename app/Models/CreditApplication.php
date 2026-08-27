<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = ['number', 'client_id', 'seller_id', 'credit_product_id', 'status', 'requested_amount', 'approved_amount', 'currency', 'purpose', 'term', 'payment_frequency', 'interest_rate', 'interest_method', 'proposed_first_payment_date', 'administrative_fee', 'economic_snapshot', 'requires_guarantor', 'seller_notes', 'analyst_notes', 'decided_by', 'decided_at', 'approved_at', 'estimated_last_payment_date', 'decision_reason'];

    protected function casts(): array
    {
        return ['requested_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'interest_rate' => 'decimal:6', 'administrative_fee' => 'decimal:2', 'economic_snapshot' => 'array', 'requires_guarantor' => 'boolean', 'proposed_first_payment_date' => 'date', 'decided_at' => 'datetime', 'approved_at' => 'datetime', 'estimated_last_payment_date' => 'date'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function seller()
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function product()
    {
        return $this->belongsTo(CreditProduct::class, 'credit_product_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function guarantees()
    {
        return $this->hasMany(CreditGuarantor::class);
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function disbursement()
    {
        return $this->hasOne(LoanDisbursement::class);
    }
}
