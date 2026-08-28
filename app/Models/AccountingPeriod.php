<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'status', 'closed_by_id', 'closed_at', 'closing_notes'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];
    }

    public function entries() { return $this->hasMany(JournalEntry::class); }
    public function closedBy() { return $this->belongsTo(User::class, 'closed_by_id'); }
    public function scopeOpen($query) { return $query->where('status', 'open'); }
}
