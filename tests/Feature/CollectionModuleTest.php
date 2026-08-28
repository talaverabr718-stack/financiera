<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_form_exposes_an_editable_amount_for_a_collectible_loan(): void
    {
        $this->seed(ClientModuleSeeder::class);

        $response = $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Collections/Index')
            ->has('pendingStops')
            ->has('paymentHistory.data')
            ->has('selectedRoute')
            ->where('storeTemplate', route('collections.store', ['stop' => '__STOP__'])));
    }

    public function test_dashboard_counts_upcoming_visits_and_late_collections(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $loan = Loan::firstOrFail();
        LoanInstallment::create([
            'loan_id' => $loan->id,
            'number' => 1,
            'due_date' => today()->subDay(),
            'principal_due' => '500.00',
            'interest_due' => '0.00',
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ]);
        $futureRoute = CollectionRoute::create([
            'code' => 'RUT-FUT-01',
            'name' => 'Ruta de mañana',
            'scheduled_date' => today()->addDay(),
            'collector_id' => SellerProfile::firstOrFail()->id,
            'starts_at' => '08:00',
            'status' => 'planned',
        ]);
        $futureRoute->stops()->create([
            'client_id' => $loan->client_id,
            'position' => 1,
            'status' => 'pending',
        ]);

        $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Collections/Index')->where('upcomingVisits', 1)
                ->where('upcomingStops.0.route.name', 'Ruta de mañana')
                ->where('lateCollections', 1)->has('lateInstallments', 1));
    }

    public function test_agenda_route_selector_shows_the_selected_days_visits(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $otherClient = Client::create([
            'code' => 'CLI-AGENDA',
            'full_name' => 'Pedro Agenda Test',
            'address' => 'Estelí',
            'status' => 'active',
        ]);
        $second = CollectionRoute::create([
            'code' => 'RUT-000002',
            'name' => 'Estelí Norte',
            'scheduled_date' => today(),
            'collector_id' => SellerProfile::firstOrFail()->id,
            'starts_at' => '09:00',
            'status' => 'planned',
        ]);
        $second->stops()->create([
            'client_id' => $otherClient->id,
            'position' => 1,
            'status' => 'pending',
        ]);

        $this->get(route('collections.index', ['date' => today()->format('Y-m-d'), 'agenda_route' => $second->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('selectedRoute.id', $second->id)
                ->where('selectedRoute.stops.0.client_id', $otherClient->id));
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
        $this->assertNotNull($stop->fresh()->visited_at);
        $visitLabel = $stop->fresh()->visitedAtLabel();
        $this->assertNotNull($visitLabel);

        $this->actingAs($user)->get(route('routes.index', [
            'date' => $route->scheduled_date->format('Y-m-d'),
            'route' => $route->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedRoute.stops.0.status', 'visited'));

        $this->actingAs($user)->get(route('collections.index', [
            'date' => $route->scheduled_date->format('Y-m-d'),
            'agenda_route' => $route->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('paymentHistory.data'));
        $record = CollectionRecord::firstOrFail();
        $this->assertSame('applied', $record->application_status);
        $this->assertSame('750.00', $record->amount);
        $this->assertNotNull($record->payment_id);
        $this->assertDatabaseHas('payments', [
            'id' => $record->payment_id,
            'loan_id' => $record->loan_id,
            'amount' => '750.00',
            'status' => 'applied',
        ]);
        $this->assertSame($record->loan->fresh()->outstanding_balance, $record->payment->fresh()->new_balance);
    }

    public function test_promise_reschedules_stop_and_requires_a_future_date(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), ['outcome' => 'promise'])->assertSessionHasErrors('promise_date');
        $this->actingAs($user)->post(route('collections.store', $stop), ['outcome' => 'promise', 'promise_date' => today()->addDay()->format('Y-m-d')])->assertSessionHasNoErrors();

        $this->assertSame('rescheduled', $stop->fresh()->status);

        $this->actingAs($user)->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('paymentHistory.data.0.outcome', 'promise'));
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

    public function test_collection_payment_updates_loan_installments_in_portfolio(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
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

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '500.00',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame('paid', $installment->fresh()->status);
        $this->assertSame('0.00', $installment->fresh()->outstandingAmount());
        $this->assertSame('400.00', $installment->fresh()->principal_paid);
        $this->assertSame('100.00', $installment->fresh()->interest_paid);
        $this->assertSame(bcsub($previous, '500.00', 2), $loan->fresh()->outstanding_balance);
        $this->assertDatabaseHas('payment_allocations', [
            'installment_id' => $installment->id,
            'component' => 'principal',
            'amount' => '400.00',
        ]);
        $this->get(route('loans.show', $loan))->assertOk()->assertSee('REC-');
    }

    public function test_collection_payment_cannot_exceed_loan_balance(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => bcadd($loan->outstanding_balance, '1.00', 2),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('collection_records', 0);
    }
}
