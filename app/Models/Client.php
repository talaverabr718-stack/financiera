<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'full_name', 'identity_number', 'birth_date', 'phone', 'email', 'address', 'department', 'municipality', 'neighborhood', 'latitude', 'longitude', 'economic_activity', 'workplace', 'job_position', 'workplace_address', 'employment_duration_months', 'estimated_income', 'other_income', 'estimated_expenses', 'housing_status', 'dependents', 'status', 'notes'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'estimated_income' => 'decimal:2', 'estimated_expenses' => 'decimal:2'];
    }

    public function portfolioAssignments()
    {
        return $this->hasMany(ClientPortfolioAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(ClientPortfolioAssignment::class)->whereNull('ended_at');
    }

    public function creditApplications()
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function hasOpenCredit(): bool
    {
        return $this->loans()->whereIn('status', Loan::COLLECTIBLE_STATUSES)->exists();
    }

    public function canOriginateNewCredit(): bool
    {
        return ! $this->hasOpenCredit();
    }

    public function scopeWithoutOpenCredit($query)
    {
        return $query->whereDoesntHave('loans', fn ($loans) => $loans->whereIn('status', Loan::COLLECTIBLE_STATUSES));
    }

    public function delinquencyCases()
    {
        return $this->hasMany(DelinquencyCase::class);
    }

    public function assets()
    {
        return $this->hasMany(ClientAsset::class);
    }

    public function collectionRecords()
    {
        return $this->hasMany(CollectionRecord::class);
    }

    public function legacyGuarantors()
    {
        return $this->hasMany(ClientGuarantor::class);
    }

    public function usedGuarantees()
    {
        return $this->hasManyThrough(CreditGuarantor::class, CreditApplication::class, 'client_id', 'credit_application_id');
    }
}
