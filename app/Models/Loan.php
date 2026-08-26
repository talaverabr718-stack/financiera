<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = ['number', 'credit_application_id', 'client_id', 'seller_id', 'status', 'currency', 'principal', 'principal_balance', 'interest_balance', 'fee_balance', 'approved_terms', 'disbursed_at'];

    protected function casts(): array
    {
        return ['principal' => 'decimal:2', 'principal_balance' => 'decimal:2', 'interest_balance' => 'decimal:2', 'fee_balance' => 'decimal:2', 'approved_terms' => 'array', 'disbursed_at' => 'date'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function seller()
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function application()
    {
        return $this->belongsTo(CreditApplication::class, 'credit_application_id');
    }

    public function collectionRecords()
    {
        return $this->hasMany(CollectionRecord::class);
    }

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    public function disbursement()
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    public function guarantees()
    {
        return $this->hasMany(CreditGuarantor::class);
    }

    protected static function booted(): void
    {
        static::created(function (Loan $loan): void {
            CreditGuarantor::where('credit_application_id', $loan->credit_application_id)
                ->where('status', 'approved')
                ->update(['loan_id' => $loan->id, 'status' => 'active']);
        });
    }

    public function getOutstandingBalanceAttribute(): string
    {
        return bcadd(bcadd((string) $this->principal_balance, (string) $this->interest_balance, 2), (string) $this->fee_balance, 2);
    }
}
