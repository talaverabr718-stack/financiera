<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public const COLLECTIBLE_STATUSES = ['active', 'delinquent'];

    protected $fillable = ['number', 'credit_application_id', 'restructured_from_id', 'client_id', 'seller_id', 'status', 'currency', 'principal', 'principal_balance', 'interest_balance', 'fee_balance', 'delinquency_balance', 'delinquency_daily_rate', 'approved_terms', 'disbursed_at', 'maturity_date', 'closed_at'];

    protected function casts(): array
    {
        return ['principal' => 'decimal:2', 'principal_balance' => 'decimal:2', 'interest_balance' => 'decimal:2', 'fee_balance' => 'decimal:2', 'delinquency_balance' => 'decimal:2', 'delinquency_daily_rate' => 'decimal:6', 'approved_terms' => 'array', 'disbursed_at' => 'date', 'maturity_date' => 'date', 'closed_at' => 'datetime'];
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

    public function delinquencyAccruals()
    {
        return $this->hasMany(DelinquencyAccrual::class);
    }

    public function delinquencyCases()
    {
        return $this->hasMany(DelinquencyCase::class);
    }

    public function activeDelinquencyCase()
    {
        return $this->hasOne(DelinquencyCase::class)->where('status', DelinquencyCase::STATUS_ACTIVE);
    }

    public function isCollectible(): bool
    {
        return in_array($this->status, self::COLLECTIBLE_STATUSES, true);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function restructuredFrom()
    {
        return $this->belongsTo(self::class, 'restructured_from_id');
    }

    public function restructurings()
    {
        return $this->hasMany(self::class, 'restructured_from_id');
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
        $nonPrincipal = bcadd((string) $this->interest_balance, (string) $this->fee_balance, 2);
        $nonPrincipal = bcadd($nonPrincipal, (string) $this->delinquency_balance, 2);

        return bcadd((string) $this->principal_balance, $nonPrincipal, 2);
    }
}
