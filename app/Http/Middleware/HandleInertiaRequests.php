<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $settings = Schema::hasTable('system_settings')
            ? SystemSetting::whereIn('group', ['brand', 'appearance'])->get()->groupBy('group')->map(fn ($items) => $items->pluck('value', 'key')->all())
            : collect();
        $brand = $settings->get('brand', []);

        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email')],
            'navigation' => app(NavigationService::class)->forUser($request->user()),
            'brand' => [
                'system_name' => $brand['system_name'] ?? 'Financiera',
                'system_tagline' => $brand['system_tagline'] ?? 'Gestión integral',
                'logo_url' => filled($brand['logo_path'] ?? null) ? route('settings.logo') : null,
            ],
            'appearance' => array_merge(
                ['theme' => 'night'],
                $settings->get('appearance', []) ?: [],
            ),
            'routes' => [
                'logout' => route('logout'),
                'search' => route('search'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }
}
