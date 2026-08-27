<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_uuid', 'auditable_type', 'auditable_id', 'action', 'actor_id',
        'occurred_at', 'before_values', 'after_values', 'reason', 'request_id',
        'ip_address', 'user_agent', 'metadata', 'previous_hash', 'event_hash',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted.'));
    }
}
