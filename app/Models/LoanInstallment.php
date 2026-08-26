<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    protected $fillable = ['loan_id', 'number', 'due_date', 'principal_due', 'interest_due', 'fees_due', 'delinquency_due', 'paid_amount', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'principal_due' => 'decimal:2', 'interest_due' => 'decimal:2', 'fees_due' => 'decimal:2', 'delinquency_due' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function loan() { return $this->belongsTo(Loan::class); }
}
