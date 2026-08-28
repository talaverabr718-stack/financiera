<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', ['loginUrl' => route('login.store')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $method = $request->input('method', 'password');
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'method' => ['nullable', Rule::in(['password', 'pin'])],
            'password' => [$method === 'password' ? 'required' : 'nullable', 'string'],
            'pin' => [$method === 'pin' ? 'required' : 'nullable', 'digits:4'],
        ]);

        $authenticated = false;
        if ($method === 'pin') {
            $user = User::where('email', $credentials['email'])->where('is_active', true)->first();
            if ($user?->pin && Hash::check($credentials['pin'], $user->pin)) {
                Auth::login($user, $request->boolean('remember'));
                $authenticated = true;
            }
        } else {
            $authenticated = Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'));
        }

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'Las credenciales proporcionadas no son válidas o la cuenta está inactiva.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
