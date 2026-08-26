<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionRecord extends Model
{
    protected $fillable = ['idempotency_key', 'collection_route_stop_id', 'client_id', 'loan_id', 'collector_id', 'outcome', 'amount', 'currency', 'payment_method', 'reference', 'promise_date', 'notes', 'application_status', 'recorded_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'promise_date' => 'date', 'recorded_at' => 'datetime'];
    }

    public function stop()
    {
        return $this->belongsTo(CollectionRouteStop::class, 'collection_route_stop_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function collector()
    {
        return $this->belongsTo(SellerProfile::class, 'collector_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
