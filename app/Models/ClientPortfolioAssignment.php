<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortfolioAssignment extends Model
{
    protected $fillable = ['client_id', 'seller_id', 'previous_seller_id', 'assigned_at', 'ended_at', 'active_guard', 'reason', 'assigned_by'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function seller()
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function previousSeller()
    {
        return $this->belongsTo(SellerProfile::class, 'previous_seller_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
