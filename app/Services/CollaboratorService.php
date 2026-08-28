<?php

namespace App\Services;

use App\Models\SellerProfile;
use Illuminate\Support\Facades\DB;

class CollaboratorService
{
    public function __construct(private DocumentSequenceService $sequences) {}

    public function create(array $data): SellerProfile
    {
        return DB::transaction(fn () => SellerProfile::create([
            'full_name' => $data['name'],
            'email' => $data['email'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'],
            'code' => $this->sequences->next('collaborator', 'COL-'),
            'status' => 'active',
        ]));
    }

    public function update(SellerProfile $collaborator, array $data): SellerProfile
    {
        return DB::transaction(function () use ($collaborator, $data) {
            $locked = SellerProfile::lockForUpdate()->findOrFail($collaborator->id);
            $locked->update([
                'full_name' => $data['name'],
                'email' => $data['email'] ?? null,
                'identity_number' => $data['identity_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'],
            ]);

            return $locked->fresh(['user', 'branch']);
        });
    }

    public function inactivate(SellerProfile $collaborator): void
    {
        DB::transaction(fn () => SellerProfile::lockForUpdate()->findOrFail($collaborator->id)->update(['status' => 'inactive']));
    }
}
