<?php

namespace App\Services;

use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionService
{
    public function allows(User $user, string $moduleKey, string $ability = 'view'): bool
    {
        if (! Schema::hasTable('system_roles')) {
            return true;
        }

        $permission = $this->effectiveFor($user)->get($moduleKey);

        return $permission ? (bool) ($permission[$ability] ?? false) : true;
    }

    public function effectiveFor(User $user, ?Collection $modules = null): Collection
    {
        $modules ??= SystemModule::orderBy('sort_order')->get();

        if (! Schema::hasTable('system_roles')) {
            return $modules->mapWithKeys(fn (SystemModule $module) => [$module->key => ['view' => true, 'manage' => true, 'full' => true]]);
        }

        $rolePermissions = $user->system_role_id
            ? DB::table('system_module_role')->where('system_role_id', $user->system_role_id)->get()->keyBy('system_module_id')
            : collect();
        $overrides = DB::table('system_module_user')->where('user_id', $user->id)->get()->keyBy('system_module_id');

        return $modules->mapWithKeys(function (SystemModule $module) use ($user, $rolePermissions, $overrides): array {
            $role = $rolePermissions->get($module->id);
            $override = $overrides->get($module->id);
            $legacyDefault = ! $user->system_role_id;
            $roleView = $role ? (bool) $role->can_view : $legacyDefault;
            $roleManage = $role ? (bool) $role->can_manage : $legacyDefault;
            $roleFull = $role ? (bool) $role->can_full : $legacyDefault;
            $view = $override && $override->can_view !== null ? (bool) $override->can_view : $roleView;
            $manage = $override && $override->can_manage !== null ? (bool) $override->can_manage : $roleManage;
            $full = $override && $override->can_full !== null ? (bool) $override->can_full : $roleFull;

            return [$module->key => [
                'view' => $view,
                'manage' => $view && $manage,
                'full' => $view && $manage && $full,
                'role_view' => $roleView,
                'role_manage' => $roleView && $roleManage,
                'role_full' => $roleView && $roleManage && $roleFull,
                'view_override' => $override?->can_view === null ? null : (bool) $override->can_view,
                'manage_override' => $override?->can_manage === null ? null : (bool) $override->can_manage,
                'full_override' => $override?->can_full === null ? null : (bool) $override->can_full,
            ]];
        });
    }
}
