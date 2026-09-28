<?php

namespace Tests\Feature;

use App\Models\SystemRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_production_seed_contains_no_demo_users_or_known_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => 'admin@financiera.test']);
        $this->post(route('login.store'), ['email' => 'admin@financiera.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_demo_seed_is_blocked_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(LogicException::class);
        $this->seed(DemoDatabaseSeeder::class);
    }

    public function test_first_administrator_is_created_interactively_with_the_administrator_role(): void
    {
        $this->artisan('app:create-admin', ['email' => 'owner@example.test', '--name' => 'Owner'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'AdminSeguro!2026')
            ->expectsQuestion('Confirma la contraseña', 'AdminSeguro!2026')
            ->expectsOutput('Primer administrador creado correctamente.')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame(SystemRole::query()->where('key', 'administrator')->value('id'), $user->system_role_id);
        $this->assertTrue($user->is_active);
    }

    public function test_first_administrator_command_refuses_to_modify_an_existing_installation(): void
    {
        User::factory()->create();

        $this->artisan('app:create-admin', ['email' => 'owner@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'AdminSeguro!2026')
            ->expectsQuestion('Confirma la contraseña', 'AdminSeguro!2026')
            ->expectsOutput('Ya existe una cuenta de usuario. El primer administrador solo puede crearse en una instalación vacía.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
    }

    public function test_first_administrator_command_fails_safely_when_the_administrator_role_is_missing(): void
    {
        SystemRole::query()->delete();

        $this->artisan('app:create-admin', ['email' => 'owner@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'AdminSeguro!2026')
            ->expectsQuestion('Confirma la contraseña', 'AdminSeguro!2026')
            ->expectsOutput('No existe el rol Administrador. Ejecuta las migraciones antes de crear la cuenta inicial.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_entrypoint_never_seeds_and_only_runs_migrations_when_explicitly_enabled(): void
    {
        $entrypoint = file_get_contents(base_path('docker/php/entrypoint.sh'));

        $this->assertStringNotContainsString('artisan db:seed', $entrypoint);
        $this->assertStringContainsString('APP_RUN_MIGRATIONS:-false', $entrypoint);
        $this->assertStringContainsString('php artisan migrate --force --ansi', $entrypoint);
    }

    public function test_consecutive_structural_bootstraps_do_not_change_existing_data(): void
    {
        $user = User::factory()->create();
        $before = [
            'users' => User::query()->count(),
            'role_id' => $user->system_role_id,
            'modules' => DB::table('system_modules')->count(),
            'roles' => DB::table('system_roles')->count(),
        ];

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before['users'], User::query()->count());
        $this->assertSame($before['role_id'], $user->fresh()->system_role_id);
        $this->assertSame($before['modules'], DB::table('system_modules')->count());
        $this->assertSame($before['roles'], DB::table('system_roles')->count());
    }
}
