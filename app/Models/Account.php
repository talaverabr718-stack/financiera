<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    public const TYPES = ['asset_current'=>'Activo corriente','asset_non_current'=>'Activo no corriente','liability_current'=>'Pasivo corriente','liability_long_term'=>'Pasivo a largo plazo','equity'=>'Patrimonio','revenue'=>'Ingresos','expense'=>'Gastos','other_income'=>'Otros ingresos','other_expense'=>'Otros gastos'];
    public const NATURE_BY_TYPE = ['asset_current'=>'debit','asset_non_current'=>'debit','liability_current'=>'credit','liability_long_term'=>'credit','equity'=>'credit','revenue'=>'credit','expense'=>'debit','other_income'=>'credit','other_expense'=>'debit'];

    protected $fillable = ['code','name','description','type','nature','parent_id','level','is_postable','is_active'];
    protected function casts(): array { return ['is_postable'=>'boolean','is_active'=>'boolean','level'=>'integer']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('code'); }
    public function lines() { return $this->hasMany(JournalEntryLine::class); }
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopePostable($query) { return $query->where('is_postable', true); }
    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? $this->type; }
}
