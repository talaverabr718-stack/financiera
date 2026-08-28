<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditHistoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_history_lists_clients_and_locks_new_credit_while_a_loan_is_open(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $client = Client::firstOrFail();
        $loan = $client->loans()->firstOrFail();

        $this->get(route('credit-history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditHistory/Index')
                ->has('clients.data', 3)
                ->where('clients.data.0.can_originate_new_credit', false));

        $this->get(route('credit-history.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditHistory/Show')
                ->where('client.can_originate_new_credit', false)
                ->where('loans.0.number', $loan->number)
                ->where('endpoints.new_credit', route('applications.create', ['client_id' => $client->id])));

        $this->get(route('applications.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertDontSee('value="'.$client->id.'" selected', false);
    }

    public function test_paying_off_a_credit_unlocks_a_new_credit_button_to_applications(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $client = Client::firstOrFail();
        $loan = $client->loans()->firstOrFail();
        Payment::create([
            'idempotency_key' => (string) Str::uuid(),
            'receipt_number' => 'REC-HIST-1',
            'client_id' => $client->id,
            'loan_id' => $loan->id,
            'collector_id' => User::firstOrFail()->id,
            'received_at' => now(),
            'amount' => '500.00',
            'currency' => 'NIO',
            'payment_method' => 'cash',
            'previous_balance' => $loan->outstanding_balance,
            'new_balance' => bcsub($loan->outstanding_balance, '500.00', 2),
            'status' => 'applied',
            'created_by' => User::firstOrFail()->id,
        ]);
        $loan->update(['principal_balance' => 0, 'interest_balance' => 0, 'fee_balance' => 0, 'delinquency_balance' => 0, 'status' => 'paid']);

        $this->get(route('credit-history.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditHistory/Show')
                ->where('client.can_originate_new_credit', true)
                ->where('loans.0.payments.0.receipt_number', 'REC-HIST-1')
                ->where('client.paid_credits_count', 1));

        $this->get(route('applications.create', ['client_id' => $client->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Applications/Form')->where('application.client_id', $client->id));
    }
}
