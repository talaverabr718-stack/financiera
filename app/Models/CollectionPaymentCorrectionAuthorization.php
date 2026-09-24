<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CollectionPaymentCorrectionAuthorization extends Model
{
    protected $fillable = [
        'collection_record_id', 'authorized_by', 'reason', 'authorized_at', 'used_at', 'used_by',
    ];

    protected function casts(): array
    {
        return ['authorized_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function record()
    {
        return $this->belongsTo(CollectionRecord::class, 'collection_record_id');
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Correction authorizations cannot be deleted.'));
    }
}
