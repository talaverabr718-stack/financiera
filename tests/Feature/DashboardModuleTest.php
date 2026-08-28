<?php

namespace Tests\Feature;

use App\Models\CollectionRoute;
use App\Models\Loan;
use App\Models\LoanInstallment;
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
            ->where('briefing.actions.0.label', 'Reportes del día')
            ->where('briefing.actions.0.url', '#reportes-del-dia')
            ->where('briefing.actions.0.opens', 'daily-report')
            ->has('dailyReport.routes')
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
            ->where('dailyReport.other_payments.0.receipt_number', 'REC-DASH-1')
            ->where('dailyReport.other_payments.0.has_balance', true)
        );
    }

    public function test_daily_report_lists_every_route_and_field_visits_with_paid_installments(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with(['stops', 'collector.user'])->firstOrFail();
        $stop = $route->stops->firstWhere('status', 'pending') ?? $route->stops->first();
        $stop->load('client');
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $installment = LoanInstallment::create([
            'loan_id' => $loan->id,
            'number' => 1,
            'due_date' => today()->subDay(),
            'principal_due' => '400.00',
            'interest_due' => '100.00',
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ]);
        $previous = $loan->outstanding_balance;

        $this->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '500.00',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $remaining = bcsub($previous, '500.00', 2);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('briefing.actions.0.label', 'Reportes del día')
            ->where('dailyReport.routes', fn ($routes) => collect($routes)->contains(fn ($item) => $item['id'] === $route->id
                && $item['collector'] === $route->collector?->user?->name
                && collect($item['visits'])->contains(fn ($visit) => (int) $visit['id'] === $stop->id
                    && $visit['client'] === $stop->client->full_name
                    && $visit['visitor']
                    && collect($visit['payments'])->contains(fn ($payment) => $payment['amount'] === '500.00'
                        && ($payment['loan_balance'] ?? null) === $remaining
                        && $payment['has_balance'] === (bccomp($remaining, '0.00', 2) === 1)
                        && collect($payment['installments'])->contains(fn ($row) => (int) $row['number'] === $installment->number
                            && $row['settled'] === true
                            && $row['remaining'] === '0.00')))))
            ->where('dailyReport.visits', fn ($count) => $count >= 1)
            ->where('dailyReport.payments', fn ($count) => $count >= 1));
    }
}
