<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['system_role_id', 'name', 'email', 'password', 'is_active', 'failed_login_attempts', 'locked_until', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function role()
    {
        return $this->belongsTo(SystemRole::class, 'system_role_id');
    }

    public function moduleOverrides()
    {
        return $this->belongsToMany(SystemModule::class)
            ->withPivot(['can_view', 'can_manage', 'can_full'])
            ->withTimestamps();
    }

    public function appearancePreference()
    {
        return $this->hasOne(UserAppearancePreference::class);
    }

    public function authenticationEvents()
    {
        return $this->hasMany(AuthenticationEvent::class);
    }
}
