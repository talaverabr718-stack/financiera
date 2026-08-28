<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSystemUserRequest;
use App\Http\Requests\UpdateSystemUserRequest;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\SettingsUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsUserController extends Controller
{
    public function __construct(private SettingsUserService $users) {}

    public function index()
    {
        return Inertia::render('Settings/Users', [
            'users' => User::with('sellerProfile.branch')->orderByDesc('is_active')->orderBy('name')->get()->map(fn (User $user) => $user->append('has_pin')),
            'collaborators' => SellerProfile::with('branch')->where(fn ($query) => $query->whereNull('user_id')->orWhereIn('user_id', User::pluck('id')))->where('status', 'active')->orderBy('full_name')->get(),
            'currentUserId' => auth()->id(),
            'endpoints' => [
                'store' => route('settings.users.store'),
                'update' => route('settings.users.update', ['user' => '__USER__']),
                'status' => route('settings.users.status', ['user' => '__USER__']),
            ],
            'tabs' => SettingsController::tabs('users'),
        ]);
    }

    public function store(StoreSystemUserRequest $request)
    {
        $this->users->create($request->validated());

        return back()->with('success', 'Usuario creado correctamente. Ya puedes asignarle permisos.');
    }

    public function update(UpdateSystemUserRequest $request, User $user)
    {
        $this->users->update($user, $request->validated());

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function status(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->users->setActive($user, (bool) $data['is_active'], (int) auth()->id());

        return back()->with('success', $data['is_active'] ? 'Usuario activado.' : 'Usuario desactivado y sus sesiones fueron cerradas.');
    }
}
