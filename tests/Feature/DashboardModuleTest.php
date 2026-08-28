<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->has('briefing.greeting')
            ->has('briefing.situation')
            ->has('till.collected')
            ->has('aging', 3)
            ->has('todayStops')
            ->has('decisionQueue')
            ->has('overdueWatch')
            ->has('closing.closes_at')
            ->has('paymentMix')
            ->has('neighborhoods')
            ->has('promisesToday')
        );
    }

    public function test_dashboard_lists_applied_payments_not_posted(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::query()->with('client')->firstOrFail();
        $userId = User::query()->value('id');
        Payment::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'receipt_number' => 'REC-DASH-1',
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'collector_id' => $userId,
            'received_at' => now(),
            'amount' => 250,
            'currency' => 'NIO',
            'payment_method' => 'cash',
            'previous_balance' => 1000,
            'new_balance' => 750,
            'status' => 'applied',
            'created_by' => $userId,
        ]);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('recentPayments', 1)
            ->where('recentPayments.0.receipt_number', 'REC-DASH-1')
        );
    }
}
