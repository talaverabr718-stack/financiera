<?php

namespace Tests\Feature;

use App\Models\Loan;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_defines_active_portfolio_and_monthly_placed_amount(): void
    {
        $this->seed(ClientModuleSeeder::class);
        Loan::orderBy('id')->firstOrFail()->update(['principal' => 12345, 'disbursed_at' => today()]);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('stats.placed', 12345)
            ->where('monthName', now()->translatedFormat('F'))
            ->has('navigation', 4)
        );
    }
}
