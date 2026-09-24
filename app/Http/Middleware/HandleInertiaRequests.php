<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\NavigationService;
use App\Services\PermissionService;
use App\Services\UserAppearanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $settings = Schema::hasTable('system_settings')
            ? SystemSetting::where('group', 'brand')->get()->groupBy('group')->map(fn ($items) => $items->pluck('value', 'key')->all())
            : collect();
        $brand = $settings->get('brand', []);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->loadMissing('role')->only('id', 'name', 'email', 'system_role_id'),
                'role' => $request->user()?->role?->only('id', 'name'),
                'permissions' => $request->user()
                    ? app(PermissionService::class)->effectiveFor($request->user())->map(fn (array $permission) => [
                        'view' => $permission['view'],
                        'manage' => $permission['manage'],
                        'full' => $permission['full'],
                    ])->all()
                    : [],
            ],
            'navigation' => app(NavigationService::class)->forUser($request->user()),
            'brand' => [
                'system_name' => $brand['system_name'] ?? 'Financiera',
                'system_tagline' => $brand['system_tagline'] ?? 'Gestión integral',
                'logo_url' => filled($brand['logo_path'] ?? null) ? route('settings.logo') : null,
            ],
            'appearance' => app(UserAppearanceService::class)->forUser($request->user()),

            'routes' => [
                'logout' => route('logout'),
                'search' => route('search'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'receipt' => $request->session()->get('receipt'),
            ],
        ];
    }
}
