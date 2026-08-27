<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    public function record(
        Model $subject,
        string $action,
        ?int $actorId = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        array $metadata = [],
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditEvent {
        return DB::transaction(function () use (
            $subject, $action, $actorId, $before, $after, $reason,
            $metadata, $requestId, $ipAddress, $userAgent
        ): AuditEvent {
            $previousHash = AuditEvent::query()->latest('id')->lockForUpdate()->value('event_hash');
            $eventUuid = (string) Str::uuid();
            $occurredAt = now();

            $payload = [
                'event_uuid' => $eventUuid,
                'auditable_type' => $subject->getMorphClass(),
                'auditable_id' => $subject->getKey(),
                'action' => $action,
                'actor_id' => $actorId,
                'occurred_at' => $occurredAt->toISOString(),
                'before_values' => $this->sortRecursively($before),
                'after_values' => $this->sortRecursively($after),
                'reason' => $reason,
                'request_id' => $requestId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $this->sortRecursively($metadata),
                'previous_hash' => $previousHash,
            ];

            $eventHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return AuditEvent::create($payload + ['event_hash' => $eventHash]);
        });
    }

    private function sortRecursively(array $values): array
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = $this->sortRecursively($value);
            }
        }
        unset($value);

        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }
}
