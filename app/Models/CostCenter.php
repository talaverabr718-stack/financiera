<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function lines() { return $this->hasMany(JournalEntryLine::class); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
