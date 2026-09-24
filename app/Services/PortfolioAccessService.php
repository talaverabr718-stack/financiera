<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PortfolioAccessService
{
    public function __construct(private PermissionService $permissions) {}

    public function canAccessAll(User $user): bool
    {
        return $this->permissions->allows($user, 'settings', 'full');
    }

    public function sellerId(User $user): ?int
    {
        return $user->sellerProfile()
            ->where('status', 'active')
            ->value('id');
    }

    public function scopeClients(Builder $query, User $user): Builder
    {
        if ($this->canAccessAll($user)) {
            return $query;
        }

        $sellerId = $this->sellerId($user);

        return $sellerId
            ? $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment->where('seller_id', $sellerId))
            : $query->whereRaw('1 = 0');
    }

    public function scopeByClient(Builder $query, User $user, string $clientColumn = 'client_id'): Builder
    {
        if ($this->canAccessAll($user)) {
            return $query;
        }

        $sellerId = $this->sellerId($user);
        if (! $sellerId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($clientColumn, ClientPortfolioAssignment::query()
            ->select('client_id')
            ->where('seller_id', $sellerId)
            ->whereNull('ended_at'));
    }

    public function scopeBySeller(Builder $query, User $user, string $sellerColumn = 'seller_id'): Builder
    {
        if ($this->canAccessAll($user)) {
            return $query;
        }

        $sellerId = $this->sellerId($user);

        return $sellerId
            ? $query->where($sellerColumn, $sellerId)
            : $query->whereRaw('1 = 0');
    }

    public function scopeSellers(Builder $query, User $user): Builder
    {
        if ($this->canAccessAll($user)) {
            return $query;
        }

        $sellerId = $this->sellerId($user);

        return $sellerId
            ? $query->whereKey($sellerId)
            : $query->whereRaw('1 = 0');
    }

    public function authorizeClient(User $user, Client $client): void
    {
        $this->authorizeClientId($user, $client->id);
    }

    public function authorizeClientId(User $user, int $clientId): void
    {
        if ($this->canAccessAll($user)) {
            return;
        }

        $sellerId = $this->sellerId($user);
        abort_unless(
            $sellerId && ClientPortfolioAssignment::query()
                ->where('client_id', $clientId)
                ->where('seller_id', $sellerId)
                ->whereNull('ended_at')
                ->exists(),
            403,
            'Este cliente pertenece a la cartera de otro gestor.',
        );
    }

    public function authorizeSellerId(User $user, int $sellerId): void
    {
        if ($this->canAccessAll($user)) {
            return;
        }

        abort_unless($this->sellerId($user) === $sellerId, 403, 'No puedes operar la cartera de otro gestor.');
    }

    public function authorizeGlobal(User $user): void
    {
        abort_unless($this->canAccessAll($user), 403, 'Esta operación requiere acceso total al sistema.');
    }
}
