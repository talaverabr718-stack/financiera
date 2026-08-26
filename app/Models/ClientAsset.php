<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAsset extends Model
{
    protected $fillable = ['type', 'description', 'estimated_value', 'ownership_status', 'document_reference'];

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
