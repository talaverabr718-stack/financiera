<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSystemRoleRequest;
use App\Http\Requests\UpdateRolePermissionSettingsRequest;
use App\Http\Requests\UpdateUserPermissionSettingsRequest;
use App\Models\SystemModule;
use App\Models\SystemRole;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\PermissionSettingsService;
use Inertia\Inertia;

class SettingsPermissionController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private PermissionSettingsService $settings,
    ) {}

    public function index()
    {
        $modules = SystemModule::orderBy('sort_order')->get(['id', 'key', 'name', 'description', 'is_enabled']);
        $roles = SystemRole::with('modules')->withCount('users')->where('is_active', true)->orderByDesc('is_system')->orderBy('name')->get()->map(function (SystemRole $role) use ($modules): array {
            $stored = $role->modules->keyBy('id');

            return [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $modules->mapWithKeys(function (SystemModule $module) use ($stored): array {
                    $grant = $stored->get($module->id)?->pivot;

                    return [(string) $module->id => [
                        'view' => (bool) ($grant?->can_view ?? false),
                        'manage' => (bool) ($grant?->can_manage ?? false),
                        'full' => (bool) ($grant?->can_full ?? false),
                    ]];
                })->all(),
            ];
        });
        $users = User::with('role')->orderBy('name')->get(['id', 'system_role_id', 'name', 'email', 'is_active'])->map(function (User $user) use ($modules): array {
            $effective = $this->permissions->effectiveFor($user, $modules);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'system_role_id' => $user->system_role_id,
                'role_name' => $user->role?->name,
                'permissions' => $modules->mapWithKeys(fn (SystemModule $module): array => [(string) $module->id => $effective->get($module->key)])->all(),
            ];
        });

        return Inertia::render('Settings/Permissions', [
            'modules' => $modules,
            'roles' => $roles,
            'users' => $users,
            'canManage' => $this->permissions->allows(auth()->user(), 'settings', 'full'),
            'endpoints' => [
                'roleStore' => route('settings.permissions.roles.store'),
                'roleUpdate' => route('settings.permissions.roles.update', ['role' => '__ROLE__']),
                'userUpdate' => route('settings.permissions.users.update', ['user' => '__USER__']),
            ],
            'tabs' => SettingsController::tabs('permissions'),
        ]);
    }

    public function storeRole(StoreSystemRoleRequest $request)
    {
        $this->settings->createRole($request->validated());

        return back()->with('success', 'Rol creado con sus permisos predeterminados.');
    }

    public function updateRole(UpdateRolePermissionSettingsRequest $request, SystemRole $role)
    {
        $this->settings->updateRole($role, $request->validated());

        return back()->with('success', 'Permisos predeterminados del rol actualizados.');
    }

    public function updateUser(UpdateUserPermissionSettingsRequest $request, User $user)
    {
        $this->settings->updateUser($user, $request->validated());

        return back()->with('success', 'Rol y permisos individuales del usuario actualizados.');
    }
}
