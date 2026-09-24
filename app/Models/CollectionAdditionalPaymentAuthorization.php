<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CollectionAdditionalPaymentAuthorization extends Model
{
    protected $fillable = [
        'collection_route_stop_id', 'authorized_by', 'reason', 'authorized_at',
        'used_at', 'used_by',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function stop()
    {
        return $this->belongsTo(CollectionRouteStop::class, 'collection_route_stop_id');
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function paymentRecord()
    {
        return $this->hasOne(CollectionRecord::class, 'additional_payment_authorization_id');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Additional payment authorizations cannot be deleted.'));
    }
}
