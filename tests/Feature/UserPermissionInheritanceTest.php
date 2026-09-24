<?php

namespace Tests\Feature;

use App\Models\SystemModule;
use App\Models\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserPermissionInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_are_inherited_and_manage_is_enforced_by_backend(): void
    {
        $user = $this->userWithRolePermission('clients', view: true, manage: false);

        $this->actingAs($user)->get(route('clients.index'))->assertOk();
        $this->actingAs($user)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($user)->post(route('clients.store'), [])->assertForbidden();
    }

    public function test_user_can_receive_an_additional_permission_not_granted_by_role(): void
    {
        $user = $this->userWithRolePermission('clients', view: false, manage: false);
        $module = SystemModule::where('key', 'clients')->firstOrFail();
        DB::table('system_module_user')->insert([
            'user_id' => $user->id,
            'system_module_id' => $module->id,
            'can_view' => true,
            'can_manage' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('clients.index'))->assertOk();
        $this->actingAs($user)->get(route('clients.create'))->assertOk();
    }

    public function test_user_denial_overrides_a_permission_granted_by_role_and_hides_navigation(): void
    {
        $user = $this->userWithRolePermission('reports', view: true, manage: true);
        $module = SystemModule::where('key', 'reports')->firstOrFail();
        DB::table('system_module_user')->insert([
            'user_id' => $user->id,
            'system_module_id' => $module->id,
            'can_view' => false,
            'can_manage' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dashboard = SystemModule::where('key', 'dashboard')->firstOrFail();
        DB::table('system_module_role')->insert([
            'system_role_id' => $user->system_role_id,
            'system_module_id' => $dashboard->id,
            'can_view' => true,
            'can_manage' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions.reports.view', false)
            ->where('auth.permissions.reports.manage', false)
            ->where('navigation', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->doesntContain(fn ($item) => $item['key'] === 'reports')));
    }

    public function test_user_can_inherit_view_but_be_denied_management(): void
    {
        $user = $this->userWithRolePermission('clients', view: true, manage: true);
        $module = SystemModule::where('key', 'clients')->firstOrFail();
        DB::table('system_module_user')->insert([
            'user_id' => $user->id,
            'system_module_id' => $module->id,
            'can_view' => null,
            'can_manage' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('clients.index'))->assertOk();
        $this->actingAs($user)->get(route('clients.create'))->assertForbidden();
    }

    public function test_permissions_page_groups_role_defaults_and_user_overrides_by_module(): void
    {
        $target = User::factory()->create();

        $this->get(route('settings.permissions'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Permissions')
            ->has('modules')
            ->has('roles')
            ->where('users', fn ($users) => collect($users)->contains(fn ($user) => $user['id'] === $target->id && count($user['permissions']) > 0)));
    }

    public function test_settings_can_assign_a_role_and_store_tri_state_overrides(): void
    {
        $target = User::factory()->create();
        $role = SystemRole::create(['key' => 'collector', 'name' => 'Gestor de cobranza', 'is_active' => true]);
        $clients = SystemModule::where('key', 'clients')->firstOrFail();
        $reports = SystemModule::where('key', 'reports')->firstOrFail();

        $overrides = SystemModule::get()->mapWithKeys(fn (SystemModule $module) => [(string) $module->id => ['view' => null, 'manage' => null, 'full' => null]])->all();
        $overrides[$clients->id] = ['view' => true, 'manage' => true, 'full' => false];
        $overrides[$reports->id] = ['view' => false, 'manage' => false, 'full' => false];

        $this->put(route('settings.permissions.users.update', $target), [
            'system_role_id' => $role->id,
            'overrides' => $overrides,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($role->id, $target->fresh()->system_role_id);
        $this->assertDatabaseHas('system_module_user', ['user_id' => $target->id, 'system_module_id' => $clients->id, 'can_view' => true, 'can_manage' => true]);
        $this->assertDatabaseHas('system_module_user', ['user_id' => $target->id, 'system_module_id' => $reports->id, 'can_view' => false, 'can_manage' => false]);
        $this->assertDatabaseMissing('system_module_user', ['user_id' => $target->id, 'system_module_id' => SystemModule::where('key', 'loans')->value('id')]);
    }

    public function test_role_defaults_can_be_created_and_updated_from_existing_permissions_module(): void
    {
        $this->post(route('settings.permissions.roles.store'), [
            'name' => 'Analista',
            'description' => 'Consulta y evaluación de solicitudes.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = SystemRole::where('name', 'Analista')->firstOrFail();
        $applications = SystemModule::where('key', 'applications')->firstOrFail();
        $permissions = SystemModule::get()->mapWithKeys(fn (SystemModule $module) => [(string) $module->id => ['view' => false, 'manage' => false, 'full' => false]])->all();
        $permissions[$applications->id] = ['view' => false, 'manage' => true, 'full' => false];

        $this->put(route('settings.permissions.roles.update', $role), [
            'name' => 'Analista',
            'description' => 'Consulta y evaluación de solicitudes.',
            'permissions' => $permissions,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_module_role', [
            'system_role_id' => $role->id,
            'system_module_id' => $applications->id,
            'can_view' => true,
            'can_manage' => true,
        ]);
    }

    public function test_role_can_be_created_with_initial_permissions_from_the_modal(): void
    {
        $applications = SystemModule::where('key', 'applications')->firstOrFail();
        $permissions = SystemModule::get()->mapWithKeys(fn (SystemModule $module) => [
            (string) $module->id => ['view' => false, 'manage' => false, 'full' => false],
        ])->all();
        $permissions[$applications->id] = ['view' => false, 'manage' => false, 'full' => true];

        $this->post(route('settings.permissions.roles.store'), [
            'name' => 'Supervisor de crédito',
            'description' => 'Supervisa solicitudes y decisiones de crédito.',
            'permissions' => $permissions,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = SystemRole::where('name', 'Supervisor de crédito')->firstOrFail();

        $this->assertDatabaseHas('system_module_role', [
            'system_role_id' => $role->id,
            'system_module_id' => $applications->id,
            'can_view' => true,
            'can_manage' => true,
            'can_full' => true,
        ]);
    }

    public function test_edit_access_cannot_execute_actions_reserved_for_full_access(): void
    {
        $user = $this->userWithRolePermission('settings', view: true, manage: true, full: false);

        $this->actingAs($user)->get(route('settings.permissions'))->assertOk();
        $this->actingAs($user)->post(route('settings.permissions.roles.store'), [
            'name' => 'Rol no autorizado',
        ])->assertForbidden();
    }

    public function test_full_access_can_execute_critical_actions(): void
    {
        $user = $this->userWithRolePermission('settings', view: true, manage: true, full: true);

        $this->actingAs($user)->post(route('settings.permissions.roles.store'), [
            'name' => 'Supervisor total',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_roles', ['name' => 'Supervisor total']);
    }

    public function test_critical_operations_require_management_or_full_access_on_the_server(): void
    {
        $settingsViewer = $this->userWithRolePermission('settings', view: true, manage: true, full: false);
        $this->actingAs($settingsViewer)->put(route('settings.general.update'), [
            'institution_name' => 'Cambio no autorizado',
            'timezone' => 'America/Managua',
            'date_format' => 'd/m/Y',
        ])->assertForbidden();
        $this->actingAs($settingsViewer)->post(route('products.store'), [])->assertForbidden();

        $accountingViewer = $this->userWithRolePermission('accounting', view: true, manage: false, full: false);
        $this->actingAs($accountingViewer)->post(route('accounting.entries.store'), [])->assertForbidden();
    }

    private function userWithRolePermission(string $moduleKey, bool $view, bool $manage, bool $full = false): User
    {
        $role = SystemRole::create(['key' => 'role-'.uniqid(), 'name' => 'Rol '.uniqid(), 'is_active' => true]);
        $module = SystemModule::where('key', $moduleKey)->firstOrFail();
        DB::table('system_module_role')->insert([
            'system_role_id' => $role->id,
            'system_module_id' => $module->id,
            'can_view' => $view,
            'can_manage' => $manage,
            'can_full' => $full,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['system_role_id' => $role->id]);
    }
}
