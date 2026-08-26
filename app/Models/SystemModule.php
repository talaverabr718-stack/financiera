<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemModule extends Model
{
    protected $fillable = ['key','name','description','is_enabled','is_visible','sort_order'];
    protected function casts(): array { return ['is_enabled'=>'boolean','is_visible'=>'boolean']; }
    public function users() { return $this->belongsToMany(User::class)->withPivot(['can_view','can_manage'])->withTimestamps(); }
}
