<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionRouteStop extends Model
{
    protected $fillable = ['collection_route_id', 'client_id', 'position', 'status', 'visited_at', 'notes'];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function route()
    {
        return $this->belongsTo(CollectionRoute::class, 'collection_route_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function records()
    {
        return $this->hasMany(CollectionRecord::class)->latest('recorded_at');
    }
}
