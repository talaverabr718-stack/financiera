<?php

namespace Tests\Feature;

use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_form_exposes_an_editable_amount_for_a_collectible_loan(): void
    {
        $this->seed(ClientModuleSeeder::class);

        $response = $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('Cantidad cobrada (C$)')
            ->assertSee('name="amount"', false)
            ->assertSee('inputmode="decimal"', false);
    }

    public function test_collection_result_updates_the_linked_route_stop(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $route = CollectionRoute::with('stops')->firstOrFail();
        $stop = $route->stops->where('status', 'pending')->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => Loan::where('client_id', $stop->client_id)->firstOrFail()->id,
            'amount' => '750.00',
            'payment_method' => 'cash',
            'notes' => 'Pago recibido en domicilio',
        ])->assertSessionHasNoErrors();

        $this->assertSame('visited', $stop->fresh()->status);
        $this->assertSame('pending', CollectionRecord::firstOrFail()->application_status);
        $this->assertSame('750.00', CollectionRecord::firstOrFail()->amount);
    }

    public function test_promise_reschedules_stop_and_requires_a_future_date(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), ['outcome' => 'promise'])->assertSessionHasErrors('promise_date');
        $this->actingAs($user)->post(route('collections.store', $stop), ['outcome' => 'promise', 'promise_date' => today()->addDay()->format('Y-m-d')])->assertSessionHasNoErrors();

        $this->assertSame('rescheduled', $stop->fresh()->status);
    }

    public function test_payment_cannot_be_linked_to_another_clients_loan(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $route = CollectionRoute::with('stops')->firstOrFail();
        $stop = $route->stops->first();
        $otherLoan = Loan::where('client_id', '!=', $stop->client_id)->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), ['outcome' => 'collected', 'loan_id' => $otherLoan->id, 'amount' => '500.00', 'payment_method' => 'cash'])->assertSessionHasErrors('loan_id');

        $this->assertDatabaseCount('collection_records', 0);
    }

    public function test_payment_cannot_use_a_non_collectible_loan(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $loan->update(['status' => 'paid']);

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected', 'loan_id' => $loan->id,
            'amount' => '500.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors('loan_id');

        $this->assertDatabaseCount('collection_records', 0);
    }
}
