<?php

namespace App\Policies;

use App\Models\CreditGuarantor;
use App\Models\User;

class CreditGuarantorPolicy
{
    public function decide(User $user, CreditGuarantor $guarantee): bool
    {
        return ! in_array($guarantee->status, ['active', 'released'], true);
    }

    public function release(User $user, CreditGuarantor $guarantee): bool
    {
        return in_array($guarantee->status, ['approved', 'active'], true);
    }
}
