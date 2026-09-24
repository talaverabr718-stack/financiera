<?php

namespace App\Services;

use App\Models\SystemModule;
use App\Models\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PermissionSettingsService
{
    public function __construct(private PermissionService $permissions) {}

    public function createRole(array $data): SystemRole
    {
        return DB::transaction(function () use ($data): SystemRole {
            $base = Str::slug($data['name']) ?: 'rol';
            $key = $base;
            $suffix = 2;
            while (SystemRole::where('key', $key)->lockForUpdate()->exists()) {
                $key = $base.'-'.$suffix++;
            }

            $role = SystemRole::create([
                'key' => $key,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            if (array_key_exists('permissions', $data)) {
                $this->syncRolePermissions($role, $data['permissions']);
            }

            return $role;
        });
    }

    public function updateRole(SystemRole $role, array $data): void
    {
        DB::transaction(function () use ($role, $data): void {
            $locked = SystemRole::lockForUpdate()->findOrFail($role->id);
            $locked->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->syncRolePermissions($locked, $data['permissions']);
            $this->assertSettingsAdministratorRemains();
        });
    }

    private function syncRolePermissions(SystemRole $role, array $permissions): void
    {
        $now = now();
        $rows = SystemModule::lockForUpdate()->get()->map(function (SystemModule $module) use ($permissions, $role, $now): array {
            $permission = $permissions[$module->id] ?? [];
            $full = (bool) ($permission['full'] ?? false);
            $manage = (bool) ($permission['manage'] ?? false);

            return [
                'system_role_id' => $role->id,
                'system_module_id' => $module->id,
                'can_view' => $full || $manage || (bool) ($permission['view'] ?? false),
                'can_manage' => $full || $manage,
                'can_full' => $full,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('system_module_role')->upsert($rows, ['system_role_id', 'system_module_id'], ['can_view', 'can_manage', 'can_full', 'updated_at']);
    }

    public function updateUser(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $role = SystemRole::whereKey($data['system_role_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $locked->update(['system_role_id' => $role->id]);

            foreach (SystemModule::lockForUpdate()->get() as $module) {
                $row = $data['overrides'][$module->id] ?? [];
                $view = array_key_exists('view', $row) ? $row['view'] : null;
                $manage = array_key_exists('manage', $row) ? $row['manage'] : null;
                $full = array_key_exists('full', $row) ? $row['full'] : null;
                if ($full === true) {
                    $view = true;
                    $manage = true;
                }
                if ($manage === true) $view = true;
                if ($manage === false) $full = false;
                if ($view === false) {
                    $manage = false;
                    $full = false;
                }

                if ($view === null && $manage === null && $full === null) {
                    DB::table('system_module_user')->where(['user_id' => $locked->id, 'system_module_id' => $module->id])->delete();
                    continue;
                }

                DB::table('system_module_user')->updateOrInsert(
                    ['user_id' => $locked->id, 'system_module_id' => $module->id],
                    ['can_view' => $view, 'can_manage' => $manage, 'can_full' => $full, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            $this->assertSettingsAdministratorRemains();
        });
    }

    private function assertSettingsAdministratorRemains(): void
    {
        $hasAdministrator = User::where('is_active', true)->get()->contains(
            fn (User $candidate): bool => $this->permissions->allows($candidate, 'settings', 'full'),
        );

        if (! $hasAdministrator) {
            throw ValidationException::withMessages(['permissions' => 'Debe permanecer al menos un usuario activo con acceso total a Configuración.']);
        }
    }
}
