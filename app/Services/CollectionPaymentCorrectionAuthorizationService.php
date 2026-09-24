<?php

namespace App\Services;

use App\Models\CollectionPaymentCorrectionAuthorization;
use App\Models\CollectionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionPaymentCorrectionAuthorizationService
{
    public function __construct(private AuditService $audit) {}

    public function authorize(CollectionRecord $record, User $actor, string $reason): CollectionPaymentCorrectionAuthorization
    {
        return DB::transaction(function () use ($record, $actor, $reason): CollectionPaymentCorrectionAuthorization {
            $record = CollectionRecord::query()->with('payment.reversal')->lockForUpdate()->findOrFail($record->id);
            $this->assertCorrectable($record);

            if ($record->correctionAuthorization()->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta gestión ya tiene una autorización de corrección.',
                ]);
            }

            $authorization = CollectionPaymentCorrectionAuthorization::create([
                'collection_record_id' => $record->id,
                'authorized_by' => $actor->id,
                'reason' => $reason,
                'authorized_at' => now(),
            ]);

            $this->audit->record(
                $authorization,
                'collection.payment_correction.authorized',
                $actor->id,
                [],
                ['authorized_at' => $authorization->authorized_at?->toISOString()],
                $reason,
                ['collection_record_id' => $record->id, 'payment_id' => $record->payment_id],
            );

            return $authorization;
        });
    }

    public function lockForUse(CollectionRecord $record, mixed $authorizationId): CollectionPaymentCorrectionAuthorization
    {
        if (! $authorizationId) {
            throw ValidationException::withMessages([
                'amount' => 'El administrador debe habilitar la corrección antes de aplicarla.',
            ]);
        }

        $authorization = CollectionPaymentCorrectionAuthorization::query()
            ->whereKey($authorizationId)
            ->where('collection_record_id', $record->id)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if (! $authorization) {
            throw ValidationException::withMessages([
                'amount' => 'La autorización de corrección no existe, ya fue utilizada o no corresponde a este pago.',
            ]);
        }

        return $authorization;
    }

    public function markUsed(CollectionPaymentCorrectionAuthorization $authorization, User $actor, int $replacementRecordId, int $paymentId): void
    {
        $authorization->update(['used_at' => now(), 'used_by' => $actor->id]);

        $this->audit->record(
            $authorization,
            'collection.payment_correction.used',
            $actor->id,
            ['used_at' => null, 'used_by' => null],
            ['used_at' => $authorization->used_at?->toISOString(), 'used_by' => $actor->id],
            $authorization->reason,
            ['replacement_record_id' => $replacementRecordId, 'payment_id' => $paymentId],
        );
    }

    private function assertCorrectable(CollectionRecord $record): void
    {
        if ($record->outcome !== 'collected' || ! $record->payment_id || $record->payment?->reversal || $record->correction()->exists()) {
            throw ValidationException::withMessages([
                'reason' => 'Solo se puede habilitar una corrección para un pago activo que aún no haya sido corregido.',
            ]);
        }
    }
}
