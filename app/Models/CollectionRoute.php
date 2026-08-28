<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionRoute extends Model
{
    protected $fillable = ['code', 'name', 'scheduled_date', 'collector_id', 'status', 'starts_at', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date'];
    }

    public function collector()
    {
        return $this->belongsTo(SellerProfile::class, 'collector_id');
    }

    public function stops()
    {
        return $this->hasMany(CollectionRouteStop::class)->orderBy('position');
    }

    public function withCollectorDues(\Carbon\CarbonInterface $asOf): self
    {
        $this->stops->each(function (CollectionRouteStop $stop) use ($asOf) {
            $stop->setAttribute('dues', $stop->collectorDuesOn($asOf));
        });

        return $this;
    }

    public function isOpenForField(): bool
    {
        if ($this->relationLoaded('stops')) {
            return $this->stops->isEmpty() || $this->stops->contains(fn (CollectionRouteStop $stop) => $stop->status === 'pending');
        }

        return $this->stops()->where('status', 'pending')->exists() || ! $this->stops()->exists();
    }
}
