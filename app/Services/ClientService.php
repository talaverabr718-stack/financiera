<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientService
{
    public function __construct(private DocumentSequenceService $sequences) {}

    public function create(array $data): Client
    {
        $this->guardPhoneDuplicate($data);

        return DB::transaction(function () use ($data) {
            $client = Client::create(Arr::except($data, ['seller_id', 'confirm_duplicate', 'assets', 'guarantors']) + ['code' => $this->sequences->next('client', 'CLI-')]);
            $this->syncProfile($client, $data);
            $this->assignSeller($client, (int) $data['seller_id'], 'Asignación inicial');

            return $client;
        });
    }

    public function update(Client $client, array $data): Client
    {
        $this->guardPhoneDuplicate($data, $client);
        DB::transaction(function () use ($client, $data): void {
            $client->update(Arr::except($data, ['seller_id', 'confirm_duplicate', 'assets', 'guarantors']));
            $this->syncProfile($client, $data);
        });

        return $client->fresh();
    }

    public function transfer(Client $client, int $sellerId, string $reason): void
    {
        DB::transaction(function () use ($client, $sellerId, $reason) {
            $current = $client->activeAssignment()->lockForUpdate()->first();
            if ($current?->seller_id === $sellerId) {
                throw ValidationException::withMessages(['seller_id' => 'El cliente ya pertenece a este vendedor.']);
            }
            if ($current) {
                $current->update(['ended_at' => now(), 'active_guard' => null]);
            }
            $this->assignSeller($client, $sellerId, $reason);
        });
    }

    private function assignSeller(Client $client, int $sellerId, string $reason): void
    {
        ClientPortfolioAssignment::create(['client_id' => $client->id, 'seller_id' => $sellerId, 'assigned_at' => now(), 'active_guard' => 'ACTIVE', 'reason' => $reason, 'assigned_by' => auth()->id()]);
    }

    private function guardPhoneDuplicate(array $data, ?Client $client = null): void
    {
        if (empty($data['phone']) || ! empty($data['confirm_duplicate'])) {
            return;
        }
        $query = Client::where('phone', $data['phone']);
        if ($client) {
            $query->whereKeyNot($client->id);
        }
        if ($match = $query->first()) {
            throw ValidationException::withMessages(['phone' => "Posible duplicado: {$match->full_name} ({$match->code}) usa este teléfono. Marca la confirmación para continuar."]);
        }
    }

    private function syncProfile(Client $client, array $data): void
    {
        $client->assets()->delete();
        foreach (array_filter($data['assets'] ?? [], fn ($asset) => ! empty($asset['description'])) as $asset) {
            $client->assets()->create($asset);
        }
    }
}
