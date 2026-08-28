<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_login_is_an_inertia_vue_page(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('loginUrl', route('login.store'))
        );
    }

    public function test_user_can_authenticate_and_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'a-secure-test-password']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-secure-test-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
    public function test_active_user_can_authenticate_with_a_four_digit_pin(): void
    {
        $user = User::factory()->create(['pin' => '4829', 'is_active' => true]);

        $this->post(route('login.store'), ['method' => 'pin', 'email' => $user->email, 'pin' => '4829'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate_with_password_or_pin(): void
    {
        $user = User::factory()->create(['password' => 'a-secure-test-password', 'pin' => '4829', 'is_active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-secure-test-password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['method' => 'pin', 'email' => $user->email, 'pin' => '4829'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
