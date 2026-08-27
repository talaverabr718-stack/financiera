<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['number', 'date', 'concept', 'reference', 'status', 'total_debit', 'total_credit', 'reversal_of_id', 'user_id', 'posted_at', 'reversed_at', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total_debit' => 'decimal:2', 'total_credit' => 'decimal:2', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Borrador', 'posted' => 'Contabilizado', 'reversed' => 'Reversado'][$this->status] ?? $this->status;
    }
}
