<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelinquencyCaseInstallment extends Model
{
    protected $fillable = [
        'delinquency_case_id', 'loan_installment_id', 'installment_number', 'due_date',
        'amount_due', 'amount_paid', 'outstanding_amount', 'days_overdue',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
        ];
    }

    public function delinquencyCase()
    {
        return $this->belongsTo(DelinquencyCase::class);
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }
}
