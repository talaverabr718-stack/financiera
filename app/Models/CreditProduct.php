<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditProduct extends Model
{
    protected $fillable = ['code', 'name', 'currency', 'allowed_frequencies', 'allowed_interest_methods', 'default_interest_rate', 'default_interest_method', 'default_administrative_fee', 'delinquency_method', 'delinquency_rate', 'payment_allocation_order', 'minimum_term', 'maximum_term', 'is_active'];

    protected function casts(): array
    {
        return ['allowed_frequencies' => 'array', 'allowed_interest_methods' => 'array', 'payment_allocation_order' => 'array', 'is_active' => 'boolean', 'default_interest_rate' => 'decimal:6', 'delinquency_rate' => 'decimal:6', 'default_administrative_fee' => 'decimal:2'];
    }
}
