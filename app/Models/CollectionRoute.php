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
}
