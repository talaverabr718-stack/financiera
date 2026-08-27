<?php

namespace App\Http\Middleware;

use App\Services\NavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email')],
            'navigation' => app(NavigationService::class)->forUser($request->user()),
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
