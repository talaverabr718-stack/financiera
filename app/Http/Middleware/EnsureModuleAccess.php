<?php

namespace App\Http\Middleware;

use App\Models\SystemModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Schema;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $key, string $ability = 'view'): Response
    {
        if (! Schema::hasTable('system_modules')) return $next($request);
        $module = SystemModule::where('key', $key)->first();
        if (! $module) return $next($request);
        abort_unless($module->is_enabled, 404, 'Este módulo está desactivado.');
        if ($request->user()) {
            $permission = $module->users()->whereKey($request->user()->id)->first()?->pivot;
            if ($permission) abort_unless($ability === 'manage' ? $permission->can_manage : $permission->can_view, 403, 'No tienes permiso para acceder a este módulo.');
        }
        return $next($request);
    }
}
