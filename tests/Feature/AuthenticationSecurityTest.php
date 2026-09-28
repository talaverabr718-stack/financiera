<?php

namespace Tests\Feature;

use App\Models\AuthenticationEvent;
use App\Models\SystemRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_is_an_inertia_vue_page_without_pin_access(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('loginUrl', route('login.store'))
            ->where('forgotPasswordUrl', route('password.request')));
    }

    public function test_email_and_pin_cannot_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword!2026']);

        $this->post(route('login.store'), ['email' => $user->email, 'pin' => '4829'])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_valid_email_and_password_authenticate_and_regenerate_the_session(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword!2026']);
        $sessionId = session()->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ValidPassword!2026'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseHas('authentication_events', ['user_id' => $user->id, 'event' => 'login_succeeded']);
    }

    public function test_failed_attempts_from_different_ips_accumulate_for_the_same_account_and_lock_it(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword!2026']);

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$attempt"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect-password'])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(5, $user->fresh()->failed_login_attempts);
        $this->assertTrue($user->fresh()->locked_until->isFuture());
        $this->assertDatabaseHas('authentication_events', ['user_id' => $user->id, 'event' => 'temporarily_locked']);
    }

    public function test_temporary_lock_expires_and_valid_credentials_restore_access(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword!2026']);
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$attempt"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect-password']);
        }

        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ValidPassword!2026'])
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->locked_until);
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_inactive_account_cannot_authenticate_even_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword!2026', 'is_active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ValidPassword!2026'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('authentication_events', ['user_id' => $user->id, 'event' => 'inactive_account']);
    }

    public function test_password_recovery_resets_access_and_tokens_cannot_be_reused_or_expired(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'ValidPassword!2026', 'failed_login_attempts' => 5, 'locked_until' => now()->addMinute()]);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'RecoveredPassword!2026', 'password_confirmation' => 'RecoveredPassword!2026'];
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('RecoveredPassword!2026', $user->fresh()->password));
        $this->assertNull($user->fresh()->locked_until);
        $this->assertDatabaseHas('authentication_events', ['user_id' => $user->id, 'event' => 'access_recovered']);

        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');

        $expired = Password::createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $expired, 'password' => 'AnotherPassword!2026', 'password_confirmation' => 'AnotherPassword!2026'])
            ->assertSessionHasErrors('email');
    }

    public function test_roles_remain_available_after_authentication_hardening(): void
    {
        $role = SystemRole::query()->firstOrFail();
        $user = User::factory()->create(['system_role_id' => $role->id, 'password' => 'ValidPassword!2026']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ValidPassword!2026']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($role->id, auth()->user()->system_role_id);
    }
}