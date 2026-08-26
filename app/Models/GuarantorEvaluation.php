<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuarantorEvaluation extends Model
{
    protected $fillable = ['occupation', 'workplace', 'workplace_address', 'monthly_income', 'other_income', 'monthly_expenses', 'assets_snapshot', 'has_overdue_obligations', 'notes', 'evaluated_by', 'evaluated_at'];

    protected function casts(): array
    {
        return ['monthly_income' => 'decimal:2', 'other_income' => 'decimal:2', 'monthly_expenses' => 'decimal:2', 'assets_snapshot' => 'array', 'has_overdue_obligations' => 'boolean', 'evaluated_at' => 'datetime'];
    }

    public function guarantee() { return $this->belongsTo(CreditGuarantor::class, 'credit_guarantor_id'); }
}
