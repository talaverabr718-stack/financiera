<?php

namespace Tests;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $authenticateByDefault = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->authenticateByDefault) {
            $this->authenticate();
        }
    }

    protected function authenticate(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    protected function cancelOpenCredits(): void
    {
        Loan::query()->whereIn('status', Loan::COLLECTIBLE_STATUSES)->update([
            'status' => 'paid',
            'principal_balance' => 0,
            'interest_balance' => 0,
            'fee_balance' => 0,
            'delinquency_balance' => 0,
        ]);
    }
}
