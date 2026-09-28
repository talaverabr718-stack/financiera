<?php

namespace App\Services;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuthenticationSecurityService
{
    public function isLocked(User $user, Request $request): bool
    {
        if (! $user->locked_until || ! $user->locked_until->isFuture()) {
            if ($user->locked_until) $user->update(['locked_until' => null]);

            return false;
        }

        $this->record('temporarily_locked', $request, $user, ['locked_until' => $user->locked_until->toISOString()]);

        return true;
    }

    public function failedCredentials(?User $user, string $email, Request $request): void
    {
        if (! $user) {
            $this->record('invalid_credentials', $request, null, ['email' => $email]);

            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $seconds = $this->lockSeconds($attempts);
        $values = ['failed_login_attempts' => $attempts];
        if ($seconds) $values['locked_until'] = now()->addSeconds($seconds);
        $user->update($values);

        $metadata = [
            'attempts' => $attempts,
            'locked_until' => $seconds ? $user->fresh()->locked_until?->toISOString() : null,
        ];
        $this->record('invalid_credentials', $request, $user, $metadata);
        if ($seconds) $this->record('temporarily_locked', $request, $user, $metadata);
    }

    public function successfulLogin(User $user, Request $request): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
        $this->record('login_succeeded', $request, $user);
    }

    public function inactiveAccount(User $user, Request $request): void
    {
        $this->record('inactive_account', $request, $user);
    }

    public function passwordRecovery(User $user, Request $request): void
    {
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        $this->record('access_recovered', $request, $user);
    }

    public function record(string $event, Request $request, ?User $user = null, array $metadata = []): void
    {
        AuthenticationEvent::query()->create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? ($metadata['email'] ?? null),
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function lockSeconds(int $attempts): int
    {
        return match (true) {
            $attempts >= 11 => 900,
            $attempts >= 8 => 300,
            $attempts >= 5 => 60,
            default => 0,
        };
    }
}