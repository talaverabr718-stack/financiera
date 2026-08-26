<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditGuarantor extends Model
{
    protected $fillable = ['credit_application_id', 'loan_id', 'guarantor_id', 'guaranteed_amount', 'guarantee_type', 'status', 'relationship', 'evaluated_at', 'accepted_at', 'approved_by', 'approved_at', 'released_by', 'released_at', 'decision_reason', 'analyst_notes', 'signed_document_path'];

    protected function casts(): array
    {
        return ['guaranteed_amount' => 'decimal:2', 'evaluated_at' => 'datetime', 'accepted_at' => 'datetime', 'approved_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function application() { return $this->belongsTo(CreditApplication::class, 'credit_application_id'); }
    public function loan() { return $this->belongsTo(Loan::class); }
    public function guarantor() { return $this->belongsTo(Guarantor::class); }
    public function evaluations() { return $this->hasMany(GuarantorEvaluation::class); }
    public function latestEvaluation() { return $this->hasOne(GuarantorEvaluation::class)->latestOfMany('evaluated_at'); }
    public function documents() { return $this->hasMany(GuarantorDocument::class); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
