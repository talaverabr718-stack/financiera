<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRole extends Model
{
    protected $fillable = ['key', 'name', 'description', 'is_system', 'is_active'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function modules()
    {
        return $this->belongsToMany(SystemModule::class, 'system_module_role')
            ->withPivot(['can_view', 'can_manage', 'can_full'])
            ->withTimestamps();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
