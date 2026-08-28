<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    public const CAPABILITIES = [
        'prospecting' => 'Captar prospectos y clientes',
        'credit_origination' => 'Levantar solicitudes de crédito',
        'collections' => 'Realizar cobros en ruta',
    ];

    protected $fillable = ['user_id', 'branch_id', 'zone_id', 'code', 'full_name', 'email', 'identity_number', 'phone', 'hired_at', 'capabilities', 'notes', 'status'];

    protected $appends = ['display_name', 'display_email'];

    protected function casts(): array
    {
        return ['hired_at' => 'date', 'capabilities' => 'array'];
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->user?->name ?: $this->code ?: '';
    }

    public function getDisplayEmailAttribute(): ?string
    {
        return $this->email ?: $this->user?->email;
    }
    protected static function booted(): void
    {
        static::creating(function (SellerProfile $profile): void {
            $profile->capabilities ??= array_keys(self::CAPABILITIES);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function portfolioAssignments()
    {
        return $this->hasMany(ClientPortfolioAssignment::class, 'seller_id');
    }

    public function collectionRoutes()
    {
        return $this->hasMany(CollectionRoute::class, 'collector_id');
    }
}
