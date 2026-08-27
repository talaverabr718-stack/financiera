<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
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
}
