<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthenticationSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'loginUrl' => route('login.store'),
            'forgotPasswordUrl' => route('password.request'),
        ]);
    }

    public function store(Request $request, AuthenticationSecurityService $security): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);
        $email = strtolower($credentials['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $security->failedCredentials(null, $email, $request);
            throw $this->invalidCredentials();
        }
        if (! $user->is_active) {
            $security->inactiveAccount($user, $request);
            throw $this->invalidCredentials();
        }
        if ($security->isLocked($user, $request)) {
            throw ValidationException::withMessages(['email' => 'La cuenta está temporalmente bloqueada. Solicita recuperación de acceso o espera antes de volver a intentarlo.']);
        }
        if (! Hash::check($credentials['password'], $user->password)) {
            $security->failedCredentials($user, $email, $request);
            throw $this->invalidCredentials();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $security->successfulLogin($user, $request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/ingresar');
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages(['email' => 'Las credenciales proporcionadas no son válidas o la cuenta no está disponible.']);
    }
}
