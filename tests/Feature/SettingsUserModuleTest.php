<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsUserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_submodule_lists_accounts_and_available_collaborators(): void
    {
        $branch = Branch::create(['code' => 'EST-01', 'name' => 'Central']);
        $collaborator = SellerProfile::create(['branch_id' => $branch->id, 'code' => 'COL-000001', 'full_name' => 'María López']);

        $this->get(route('settings.users'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Users')
            ->where('collaborators.0.id', $collaborator->id)
            ->where('tabs.3.label', 'Usuarios'));
    }

    public function test_user_can_be_created_and_linked_to_an_available_collaborator(): void
    {
        $branch = Branch::create(['code' => 'EST-01', 'name' => 'Central']);
        $collaborator = SellerProfile::create(['branch_id' => $branch->id, 'code' => 'COL-000001', 'full_name' => 'María López']);

        $this->post(route('settings.users.store'), [
            'name' => 'María López',
            'email' => 'maria@financiera.test',
            'password' => 'Segura-2026',
            'password_confirmation' => 'Segura-2026',
            'pin' => '4829',
            'pin_confirmation' => '4829',
            'collaborator_id' => $collaborator->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'maria@financiera.test')->firstOrFail();
        $this->assertTrue(Hash::check('Segura-2026', $user->password));
        $this->assertTrue(Hash::check('4829', $user->pin));
        $this->assertSame($user->id, $collaborator->fresh()->user_id);
    }

    public function test_user_requires_a_unique_email_and_confirmed_password(): void
    {
        $existing = User::factory()->create(['email' => 'existente@financiera.test']);

        $this->post(route('settings.users.store'), [
            'name' => 'Cuenta duplicada',
            'email' => $existing->email,
            'password' => 'Segura-2026',
            'password_confirmation' => 'Diferente-2026',
        ])->assertSessionHasErrors(['email', 'password']);
    }
    public function test_user_can_be_edited_without_replacing_existing_password_or_pin(): void
    {
        $user = User::factory()->create(['password' => 'Clave-original', 'pin' => '4829']);

        $this->put(route('settings.users.update', $user), [
            'name' => 'Nombre actualizado',
            'email' => 'actualizado@financiera.test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nombre actualizado', $user->name);
        $this->assertTrue(Hash::check('Clave-original', $user->password));
        $this->assertTrue(Hash::check('4829', $user->pin));
    }

    public function test_another_user_can_be_deactivated_but_current_user_cannot(): void
    {
        $current = auth()->user();
        $other = User::factory()->create(['is_active' => true]);

        $this->patch(route('settings.users.status', $other), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        $this->patch(route('settings.users.status', $current), ['is_active' => false])->assertSessionHasErrors('user');
        $this->assertTrue($current->fresh()->is_active);
    }
}
