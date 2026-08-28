<?php

namespace App\Services;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsUserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $collaborator = $this->availableCollaborator($data['collaborator_id'] ?? null);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'pin' => $data['pin'] ?? null,
                'is_active' => true,
            ]);
            $collaborator?->update(['user_id' => $user->id]);

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $currentProfile = SellerProfile::where('user_id', $locked->id)->lockForUpdate()->first();
            $targetProfile = null;
            if (! empty($data['collaborator_id'])) {
                $targetProfile = SellerProfile::lockForUpdate()->findOrFail($data['collaborator_id']);
                if ($targetProfile->user_id && $targetProfile->user_id !== $locked->id) {
                    throw ValidationException::withMessages(['collaborator_id' => 'El colaborador ya tiene otra cuenta de usuario.']);
                }
            }
            if ($currentProfile && $currentProfile->isNot($targetProfile)) $currentProfile->update(['user_id' => null]);
            $targetProfile?->update(['user_id' => $locked->id]);

            $values = ['name' => $data['name'], 'email' => $data['email']];
            if (! empty($data['password'])) $values['password'] = $data['password'];
            if (! empty($data['pin'])) $values['pin'] = $data['pin'];
            if (! empty($data['remove_pin'])) $values['pin'] = null;
            $locked->update($values);

            return $locked->fresh('sellerProfile.branch');
        });
    }

    public function setActive(User $user, bool $active, int $actorId): void
    {
        if (! $active && $user->id === $actorId) {
            throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propia cuenta mientras la estás utilizando.']);
        }

        DB::transaction(function () use ($user, $active): void {
            User::lockForUpdate()->findOrFail($user->id)->update(['is_active' => $active]);
            if (! $active) DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    private function availableCollaborator(mixed $id): ?SellerProfile
    {
        if (empty($id)) return null;
        $collaborator = SellerProfile::lockForUpdate()->findOrFail($id);
        if ($collaborator->user_id) {
            throw ValidationException::withMessages(['collaborator_id' => 'El colaborador ya tiene una cuenta de usuario.']);
        }

        return $collaborator;
    }
}
