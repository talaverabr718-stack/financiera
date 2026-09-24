<?php

namespace App\Services;

use App\Models\CollectionAdditionalPaymentAuthorization;
use App\Models\CollectionRecord;
use App\Models\CollectionRouteStop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdditionalCollectionPaymentService
{
    public function __construct(private AuditService $audit) {}

    public function authorize(CollectionRouteStop $stop, User $actor, string $reason): CollectionAdditionalPaymentAuthorization
    {
        return DB::transaction(function () use ($stop, $actor, $reason): CollectionAdditionalPaymentAuthorization {
            $stop = CollectionRouteStop::query()->with('route')->lockForUpdate()->findOrFail($stop->id);

            $this->assertSameDayRoute($stop);
            if ($stop->status === 'pending' || $this->activePaymentsForToday($stop)->count() !== 1) {
                throw ValidationException::withMessages([
                    'reason' => 'Solo se puede habilitar el segundo pago después de un primer pago válido registrado hoy.',
                ]);
            }

            if ($stop->additionalPaymentAuthorization()->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta visita ya tiene una autorización de segundo pago.',
                ]);
            }

            $authorization = CollectionAdditionalPaymentAuthorization::create([
                'collection_route_stop_id' => $stop->id,
                'authorized_by' => $actor->id,
                'reason' => $reason,
                'authorized_at' => now(),
            ]);

            $this->audit->record(
                $authorization,
                'collection.second_payment.authorized',
                $actor->id,
                [],
                ['collection_route_stop_id' => $stop->id, 'authorized_at' => $authorization->authorized_at?->toISOString()],
                $reason,
            );

            return $authorization;
        });
    }

    public function lockForUse(CollectionRouteStop $stop, mixed $authorizationId): CollectionAdditionalPaymentAuthorization
    {
        if (! $authorizationId) {
            throw ValidationException::withMessages([
                'outcome' => 'El administrador debe habilitar el segundo pago antes de registrarlo.',
            ]);
        }

        $this->assertSameDayRoute($stop);
        $authorization = CollectionAdditionalPaymentAuthorization::query()
            ->whereKey($authorizationId)
            ->where('collection_route_stop_id', $stop->id)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if (! $authorization || ! $authorization->authorized_at?->isToday()) {
            throw ValidationException::withMessages([
                'outcome' => 'La autorización del segundo pago no existe, ya fue utilizada o venció.',
            ]);
        }

        if ($this->activePaymentsForToday($stop)->count() !== 1) {
            throw ValidationException::withMessages([
                'outcome' => 'El segundo pago solo puede registrarse una vez y después del primer pago válido.',
            ]);
        }

        return $authorization;
    }

    public function markUsed(
        CollectionAdditionalPaymentAuthorization $authorization,
        CollectionRecord $record,
        User $actor,
    ): void {
        $authorization->update([
            'used_at' => now(),
            'used_by' => $actor->id,
        ]);

        $this->audit->record(
            $authorization,
            'collection.second_payment.used',
            $actor->id,
            ['used_at' => null, 'used_by' => null],
            ['used_at' => $authorization->used_at?->toISOString(), 'used_by' => $actor->id],
            $authorization->reason,
            ['collection_record_id' => $record->id, 'payment_id' => $record->payment_id],
        );
    }

    private function activePaymentsForToday(CollectionRouteStop $stop)
    {
        return CollectionRecord::query()
            ->where('collection_route_stop_id', $stop->id)
            ->where('outcome', 'collected')
            ->whereDate('recorded_at', today())
            ->whereHas('payment', fn ($query) => $query->whereDoesntHave('reversal'));
    }

    private function assertSameDayRoute(CollectionRouteStop $stop): void
    {
        $stop->loadMissing('route');
        if (! $stop->route?->scheduled_date?->isToday()) {
            throw ValidationException::withMessages([
                'reason' => 'El segundo pago solo puede habilitarse y registrarse el mismo día de la ruta.',
            ]);
        }
    }
}
