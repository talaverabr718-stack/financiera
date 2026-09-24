<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAppearancePreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserAppearanceService
{
    public const FIELDS = [
        'theme',
        'primary_color',
        'sidebar_color',
        'accent_color',
        'background_color',
        'font_family',
        'density',
        'border_radius',
    ];

    private const DEFAULTS = [
        'theme' => 'day',
        'primary_color' => '#1d4ed8',
        'sidebar_color' => '#ffffff',
        'accent_color' => '#0f766e',
        'background_color' => '#f3f5f8',
        'font_family' => 'inter',
        'density' => 'comfortable',
        'border_radius' => 'soft',
    ];

    public function __construct(private SystemSettingService $settings) {}

    public function forUser(?User $user): array
    {
        $appearance = array_merge(
            self::DEFAULTS,
            Schema::hasTable('system_settings') ? $this->settings->group('appearance') : [],
        );

        if (! $user) {
            return $appearance;
        }

        $preference = $user->relationLoaded('appearancePreference')
            ? $user->appearancePreference
            : $user->appearancePreference()->first();

        return $preference
            ? array_merge($appearance, $preference->only(self::FIELDS))
            : $appearance;
    }

    public function save(User $user, array $values): UserAppearancePreference
    {
        return DB::transaction(function () use ($user, $values): UserAppearancePreference {
            $preference = UserAppearancePreference::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $preference) {
                $preference = new UserAppearancePreference(['user_id' => $user->id]);
            }

            $preference->fill($values);
            $preference->save();

            return $preference;
        });
    }
}
