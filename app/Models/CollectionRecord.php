<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionRecord extends Model
{
    protected $fillable = ['idempotency_key', 'collection_route_stop_id', 'client_id', 'loan_id', 'payment_id', 'correction_of_id', 'additional_payment_authorization_id', 'correction_authorization_id', 'collector_id', 'outcome', 'amount', 'currency', 'payment_method', 'reference', 'promise_date', 'notes', 'correction_reason', 'application_status', 'recorded_at', 'recorded_by', 'corrected_by'];

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

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function additionalPaymentAuthorization()
    {
        return $this->belongsTo(CollectionAdditionalPaymentAuthorization::class, 'additional_payment_authorization_id');
    }

    public function correctionAuthorization()
    {
        return $this->belongsTo(CollectionPaymentCorrectionAuthorization::class, 'correction_authorization_id');
    }

    public function correctionOf()
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function correction()
    {
        return $this->hasOne(self::class, 'correction_of_id');
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
