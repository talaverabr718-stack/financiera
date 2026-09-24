<?php

namespace App\Http\Middleware;

use App\Models\SystemModule;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $key, string $ability = 'view'): Response
    {
        if (! Schema::hasTable('system_modules')) {
            return $next($request);
        }
        $module = SystemModule::where('key', $key)->first();
        if (! $module) {
            return $next($request);
        }
        abort_unless($module->is_enabled, 404, 'Este módulo está desactivado.');
        abort_unless($request->user(), 401);
        abort_unless($this->permissions->allows($request->user(), $key, $ability), 403, 'No tienes permiso para realizar esta acción.');

        return $next($request);
    }
}