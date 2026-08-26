<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientGuarantor extends Model
{
    protected $fillable = ['full_name', 'identity_number', 'relationship', 'phone', 'address', 'occupation', 'workplace', 'monthly_income', 'notes'];

    protected function casts(): array
    {
        return ['monthly_income' => 'decimal:2'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
