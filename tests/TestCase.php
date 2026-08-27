<?php

namespace Tests;

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
}
