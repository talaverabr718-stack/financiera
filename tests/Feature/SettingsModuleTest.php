<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_configuration_is_saved_with_protected_regional_values(): void
    {
        $this->put(route('settings.general.update'), ['institution_name' => 'Financiera Norte', 'timezone' => 'America/Managua', 'date_format' => 'd/m/Y'])->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['key' => 'institution_name', 'value' => 'Financiera Norte']);
        $this->assertDatabaseHas('system_settings', ['key' => 'base_currency', 'value' => 'NIO']);
    }

    public function test_only_active_postable_accounts_can_be_mapped(): void
    {
        $account = Account::create(['code' => '1101', 'name' => 'Caja', 'type' => 'asset_current', 'nature' => 'debit', 'level' => 1, 'is_postable' => false, 'is_active' => true]);
        $this->put(route('settings.accounting.update'), ['cash_account_id' => $account->id])->assertSessionHasErrors('cash_account_id');
        $account->update(['is_postable' => true]);
        $this->put(route('settings.accounting.update'), ['cash_account_id' => $account->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('system_settings', ['key' => 'cash_account_id', 'value' => (string) $account->id]);
    }

    public function test_sequence_format_changes_without_modifying_next_number(): void
    {
        DB::table('document_sequences')->insert(['key' => 'client', 'prefix' => 'CLI-', 'next_number' => 27, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        $this->put(route('settings.sequences.update'), ['sequences' => ['client' => ['prefix' => 'CL-', 'padding' => 8]]])->assertRedirect();
        $this->assertDatabaseHas('document_sequences', ['key' => 'client', 'prefix' => 'CL-', 'padding' => 8, 'next_number' => 27]);
    }

    public function test_appearance_is_persisted_and_applied_to_global_layout(): void
    {
        $this->put(route('settings.appearance.update'), ['primary_color' => '#0f766e', 'sidebar_color' => '#172554', 'accent_color' => '#2dd4bf', 'background_color' => '#f0fdfa', 'palette' => 'custom', 'font_family' => 'merriweather', 'density' => 'compact', 'border_radius' => 'rounded'])->assertRedirect();
        $this->get(route('settings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('appearance.primary_color', '#0f766e')
            ->where('appearance.density', 'compact')
            ->where('appearance.theme', 'night'));
    }

    public function test_appearance_can_switch_to_the_light_office_theme(): void
    {
        $this->put(route('settings.appearance.update'), [
            'theme' => 'day',
            'primary_color' => '#1d4ed8',
            'sidebar_color' => '#ffffff',
            'accent_color' => '#0f766e',
            'background_color' => '#f3f5f8',
            'font_family' => 'inter',
            'density' => 'comfortable',
            'border_radius' => 'soft',
        ])->assertRedirect();

        $this->get(route('settings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('appearance.theme', 'day')
            ->where('appearance.background_color', '#f3f5f8')
            ->where('appearance.primary_color', '#1d4ed8'));
        $this->get(route('settings.appearance'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('settings.theme', 'day'));
    }

    public function test_a_disabled_module_is_hidden_and_blocked_by_url(): void
    {
        $module = SystemModule::where('key', 'clients')->firstOrFail();
        $payload = SystemModule::get()->mapWithKeys(fn ($item) => [$item->id => ['enabled' => $item->id === $module->id ? 0 : 1, 'visible' => $item->id === $module->id ? 0 : 1, 'sort_order' => $item->sort_order]])->all();
        $this->put(route('settings.modules.update'), ['modules' => $payload])->assertRedirect();
        $this->get(route('clients.index'))->assertNotFound();
        $this->get(route('settings.index'))->assertDontSee('href="'.route('clients.index').'"', false);
    }

    public function test_explicit_user_permission_blocks_a_module(): void
    {
        $user = User::factory()->create();
        $module = SystemModule::where('key', 'reports')->firstOrFail();
        DB::table('system_module_user')->insert(['user_id' => $user->id, 'system_module_id' => $module->id, 'can_view' => false, 'can_manage' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_system_name_and_logo_are_saved_and_rendered(): void
    {
        Storage::fake('local');
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $this->put(route('settings.brand.update'), ['system_name' => 'Financiera Segovia', 'system_tagline' => 'Crédito responsable', 'logo' => $logo])->assertRedirect();
        $path = DB::table('system_settings')->where('key', 'logo_path')->value('value');
        Storage::disk('local')->assertExists($path);
        $this->get(route('settings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('brand.system_name', 'Financiera Segovia')
            ->where('brand.system_tagline', 'Crédito responsable')
            ->where('brand.logo_url', route('settings.logo')));
        $logoResponse = $this->get(route('settings.logo'))->assertOk();
        $this->assertStringContainsString('no-store', $logoResponse->headers->get('Cache-Control'));
        $replacement = UploadedFile::fake()->image('nuevo-logo.png', 300, 300);
        $this->put(route('settings.brand.update'), ['system_name' => 'Financiera Segovia', 'system_tagline' => 'Crédito responsable', 'logo' => $replacement])->assertRedirect();
        $newPath = DB::table('system_settings')->where('key', 'logo_path')->value('value');
        $this->assertNotSame($path, $newPath);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_topbar_has_a_secure_logout_action(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('routes.logout', route('logout')));
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
