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
use Tests\TestCase;

class CollectionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_form_exposes_an_editable_amount_for_a_collectible_loan(): void
    {
        $this->seed(ClientModuleSeeder::class);

        $response = $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('name="amount"', false)
            ->assertSee('inputmode="decimal"', false)
            ->assertSee('aria-label="Cantidad cobrada"', false)
            ->assertSee('Pagar')
            ->assertSee('Cuotas pendientes')
            ->assertSee('Registrar cobro')
            ->assertSee('Cobrado hoy')
            ->assertSee('Próximas visitas')
            ->assertSee('Visitas pendientes')
            ->assertSee('Cobros retrasados')
            ->assertSee('Ver próximas visitas')
            ->assertSee('Ver visitas pendientes')
            ->assertSee('Ver pagos atrasados')
            ->assertSee('data-open-modal="late-collections"', false)
            ->assertSee('data-open-modal="pending-visits"', false)
            ->assertSee('data-open-modal="upcoming-visits"', false)
            ->assertSee('name="agenda_route"', false)
            ->assertSee('Estelí Centro')
            ->assertSee('visitas del día')
            ->assertViewHas('selectedRoute');
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
            ->assertOk()
            ->assertViewHas('upcomingVisits', 1)
            ->assertViewHas('upcomingStops', fn ($items) => $items->count() === 1 && $items->first()->route->name === 'Ruta de mañana')
            ->assertViewHas('lateCollections', 1)
            ->assertViewHas('lateInstallments', fn ($items) => $items->count() === 1)
            ->assertSee('Cuota 1')
            ->assertSee('Días de atraso')
            ->assertSee('Saldo de la cuota')
            ->assertSee('Ruta de mañana');
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
            ->assertOk()
            ->assertSee('Estelí Norte')
            ->assertSee($otherClient->full_name)
            ->assertViewHas('selectedRoute', fn ($route) => $route->id === $second->id && $route->stops->pluck('client_id')->all() === [$otherClient->id]);
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

        $this->actingAs($user)->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Promesa de pago')
            ->assertSee('Todas las actividades');
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
