<?php

namespace Tests\Feature;

use App\Models\Loan;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoanPortfolioModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_lists_real_loans_and_balances(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::firstOrFail();
        $this->get(route('loans.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Loans/Index')
            ->where('loans.data.0.number', $loan->number)
            ->where('loans.data.0.client.full_name', $loan->client->full_name)
            ->has('summary.outstanding'));
    }

    public function test_portfolio_filters_by_status_and_search(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::firstOrFail();
        Loan::whereKeyNot($loan->id)->update(['status' => 'paid']);
        $this->get(route('loans.index', ['status' => 'paid']))->assertOk()->assertDontSee($loan->number);
        $this->get(route('loans.index', ['search' => $loan->number]))->assertOk()->assertSee($loan->number);
    }

    public function test_portfolio_detail_uses_registered_relations(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::firstOrFail();
        $this->get(route('loans.show', $loan))->assertOk()
            ->assertSee($loan->application->number)->assertSee('Cuotas')->assertSee('Gestiones de cobranza');
    }

    public function test_credit_status_can_change_without_marking_a_balance_as_paid(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::firstOrFail();

        $this->patch(route('loans.status', $loan), ['status' => 'delinquent'])->assertSessionHasNoErrors();
        $this->assertSame('delinquent', $loan->fresh()->status);
        $this->patch(route('loans.status', $loan), ['status' => 'paid'])->assertSessionHasErrors('status');
        $loan->update(['principal_balance' => 0, 'interest_balance' => 0, 'fee_balance' => 0]);
        $this->patch(route('loans.status', $loan), ['status' => 'paid'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $loan->fresh()->status);
    }
}
