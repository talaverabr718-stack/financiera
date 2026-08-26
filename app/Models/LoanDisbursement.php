<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDisbursement extends Model
{
    protected $fillable = ['idempotency_key', 'number', 'credit_application_id', 'loan_id', 'amount', 'currency', 'payment_method', 'reference', 'disbursed_at', 'disbursed_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'disbursed_at' => 'date'];
    }

    public function application() { return $this->belongsTo(CreditApplication::class, 'credit_application_id'); }
    public function loan() { return $this->belongsTo(Loan::class); }
    public function disbursedBy() { return $this->belongsTo(User::class, 'disbursed_by'); }
}
