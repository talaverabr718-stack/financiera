<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAppearancePreference extends Model
{
    protected $fillable = [
        'user_id',
        'theme',
        'primary_color',
        'sidebar_color',
        'accent_color',
        'background_color',
        'font_family',
        'density',
        'border_radius',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
