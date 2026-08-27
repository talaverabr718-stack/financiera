<?php

namespace App\Services;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CollaboratorService
{
    public function create(array $data): SellerProfile
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(Arr::only($data, ['name', 'email', 'password']));

            return SellerProfile::create(Arr::only($data, ['branch_id', 'zone_id', 'code', 'identity_number', 'phone', 'hired_at', 'capabilities', 'notes', 'status']) + ['user_id' => $user->id]);
        });
    }

    public function update(SellerProfile $collaborator, array $data): SellerProfile
    {
        return DB::transaction(function () use ($collaborator, $data) {
            $locked = SellerProfile::lockForUpdate()->findOrFail($collaborator->id);
            $user = User::lockForUpdate()->findOrFail($locked->user_id);
            $userData = Arr::only($data, ['name', 'email']);
            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }
            $user->update($userData);
            $locked->update(Arr::only($data, ['branch_id', 'zone_id', 'code', 'identity_number', 'phone', 'hired_at', 'capabilities', 'notes', 'status']));

            return $locked->fresh(['user', 'branch', 'zone']);
        });
    }

    public function inactivate(SellerProfile $collaborator): void
    {
        DB::transaction(fn () => SellerProfile::lockForUpdate()->findOrFail($collaborator->id)->update(['status' => 'inactive']));
    }
}
