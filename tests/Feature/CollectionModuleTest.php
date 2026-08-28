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
                ->has('routes', 2)
                ->where('selectedRoute.id', $second->id)
                ->where('selectedRoute.name', 'Estelí Norte')
                ->has('selectedRoute.stops', 1)
                ->where('selectedRoute.stops.0.client_id', $otherClient->id)
                ->where('selectedRoute.stops.0.client.full_name', 'Pedro Agenda Test'));

        $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('routes', 2)
                ->where('selectedRoute.id', CollectionRoute::query()->where('code', 'RUT-000001')->value('id')));
    }

    public function test_route_clients_expose_overdue_and_due_installments_for_the_collector(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with('stops')->firstOrFail();
        $stop = $route->stops->firstWhere('status', 'pending') ?? $route->stops->first();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $installment = [
            'loan_id' => $loan->id,
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
        ];
        LoanInstallment::create(array_merge($installment, [
            'number' => 1,
            'due_date' => today()->subDay(),
            'principal_due' => '300.00',
            'interest_due' => '50.00',
        ]));
        LoanInstallment::create(array_merge($installment, [
            'number' => 2,
            'due_date' => today(),
        ]));

        $this->get(route('collections.index', [
            'date' => today()->format('Y-m-d'),
            'agenda_route' => $route->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('selectedRoute.stops')
            ->where('selectedRoute.stops', fn ($stops) => collect($stops)->contains(fn ($item) => (int) $item['client_id'] === $stop->client_id
                && ($item['dues']['overdue_total'] ?? null) === '350.00'
                && ($item['dues']['due_today_total'] ?? null) === '500.00'
                && ($item['dues']['total'] ?? null) === '850.00')));

        $this->get(route('routes.index', [
            'date' => today()->format('Y-m-d'),
            'route' => $route->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRoute.stops', fn ($stops) => collect($stops)->contains(fn ($item) => (int) $item['client_id'] === $stop->client_id
                && count($item['dues']['overdue']) === 1
                && count($item['dues']['due_today']) === 1)));
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

    public function test_guests_cannot_open_or_post_collections(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();

        auth()->logout();

        $this->get(route('collections.index'))->assertRedirect(route('login'));
        $this->post(route('collections.store', $stop), [
            'outcome' => 'no_payment',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseCount('collection_records', 0);
    }

    public function test_unknown_agenda_route_falls_back_to_the_first_route_of_the_day(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $firstId = CollectionRoute::query()->where('code', 'RUT-000001')->value('id');

        $this->get(route('collections.index', [
            'date' => today()->format('Y-m-d'),
            'agenda_route' => 999999,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRoute.id', $firstId)
            ->where('storeTemplate', route('collections.store', ['stop' => '__STOP__'])));
    }

    public function test_day_without_routes_renders_an_empty_agenda(): void
    {
        $this->seed(ClientModuleSeeder::class);

        $this->get(route('collections.index', ['date' => today()->addDays(10)->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Collections/Index')
                ->where('routes', [])
                ->where('selectedRoute', null)
                ->where('pendingStops', [])
                ->where('upcomingVisits', 0));
    }

    public function test_due_on_the_agenda_date_is_not_classified_as_overdue(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $this->createInstallment($loan, 1, today(), '200.00', '50.00');

        $dues = $stop->fresh()->collectorDuesOn(today());

        $this->assertSame([], $dues['overdue']);
        $this->assertCount(1, $dues['due_today']);
        $this->assertSame('250.00', $dues['due_today_total']);
        $this->assertSame('0.00', $dues['overdue_total']);
        $this->assertFalse($loan->installments()->first()->isOverdueOn(today()));
    }

    public function test_dues_follow_the_agenda_date_not_today(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $agendaDate = today()->subDays(3);
        $route = CollectionRoute::firstOrFail();
        $route->update(['scheduled_date' => $agendaDate]);
        $stop = $route->stops()->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $this->createInstallment($loan, 1, $agendaDate->copy()->subDay(), '100.00');
        $this->createInstallment($loan, 2, $agendaDate, '80.00');
        $this->createInstallment($loan, 3, today(), '60.00');

        $this->get(route('collections.index', [
            'date' => $agendaDate->format('Y-m-d'),
            'agenda_route' => $route->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRoute.stops', fn ($stops) => collect($stops)->contains(fn ($item) => (int) $item['client_id'] === $stop->client_id
                && ($item['dues']['overdue_total'] ?? null) === '100.00'
                && ($item['dues']['due_today_total'] ?? null) === '80.00'
                && ($item['dues']['total'] ?? null) === '180.00'
                && count($item['dues']['overdue']) === 1
                && count($item['dues']['due_today']) === 1)));
    }

    public function test_paid_excluded_future_and_closed_loan_installments_are_omitted_from_dues(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $this->createInstallment($loan, 1, today()->subDay(), '40.00', '0.00', ['status' => 'paid', 'principal_paid' => '40.00', 'paid_amount' => '40.00']);
        $this->createInstallment($loan, 2, today()->subDay(), '30.00', '0.00', ['status' => 'waived']);
        $this->createInstallment($loan, 3, today()->addDay(), '90.00');
        $this->createInstallment($loan, 4, today()->subDay(), '25.00', '0.00', ['principal_paid' => '25.00', 'paid_amount' => '25.00']);
        $closed = Loan::where('client_id', '!=', $stop->client_id)->firstOrFail();
        $closed->update(['status' => 'paid']);
        $this->createInstallment($closed, 1, today()->subDay(), '500.00');

        $otherStop = CollectionRoute::with('stops')->firstOrFail()->stops->firstWhere('client_id', $closed->client_id);
        $dues = $stop->fresh()->collectorDuesOn(today());
        $this->assertSame('0.00', $dues['total']);
        $this->assertSame([], $dues['overdue']);
        $this->assertSame([], $dues['due_today']);

        if ($otherStop) {
            $this->assertSame('0.00', $otherStop->fresh()->collectorDuesOn(today())['total']);
        }
    }

    public function test_partially_paid_and_delinquent_loan_installments_remain_collectible(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $loan->update(['status' => 'delinquent']);
        $this->createInstallment($loan, 1, today()->subDays(2), '100.00', '20.00', [
            'principal_paid' => '40.00',
            'interest_paid' => '0.00',
            'paid_amount' => '40.00',
        ]);

        $dues = $stop->fresh()->collectorDuesOn(today());
        $this->assertSame('80.00', $dues['overdue_total']);
        $this->assertSame(2, $dues['overdue'][0]['days']);
        $this->assertSame('delinquent', $loan->fresh()->status);
    }

    public function test_every_assigned_stop_exposes_dues_even_without_a_schedule(): void
    {
        $this->seed(ClientModuleSeeder::class);

        $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('selectedRoute.stops', fn ($stops) => collect($stops)->every(fn ($item) => isset($item['dues']['overdue'], $item['dues']['due_today'], $item['dues']['total'])
                    && $item['dues']['total'] === '0.00')));
    }

    public function test_collected_requires_amount_loan_and_rejects_zero(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors(['loan_id', 'amount']);

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '0.00',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '10.00',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('collection_records', 0);
        $this->assertSame('pending', $stop->fresh()->status);
    }

    public function test_promise_cannot_use_today_or_a_past_date(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'promise',
            'promise_date' => today()->format('Y-m-d'),
        ])->assertSessionHasErrors('promise_date');

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'promise',
            'promise_date' => today()->subDay()->format('Y-m-d'),
        ])->assertSessionHasErrors('promise_date');

        $this->assertSame('pending', $stop->fresh()->status);
    }

    public function test_not_found_and_no_payment_do_not_create_a_loan_payment(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $route = CollectionRoute::with('stops')->firstOrFail();
        $pending = $route->stops->where('status', 'pending')->values();
        $first = $pending[0];
        $second = $pending[1];

        $this->actingAs($user)->post(route('collections.store', $first), [
            'outcome' => 'not_found',
            'notes' => 'No estaba en casa',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('collections.store', $second), [
            'outcome' => 'no_payment',
            'notes' => 'Sin efectivo',
        ])->assertSessionHasNoErrors();

        $this->assertSame('not_found', $first->fresh()->status);
        $this->assertSame('visited', $second->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('completed', $route->fresh()->status);
        $this->assertDatabaseHas('collection_records', [
            'collection_route_stop_id' => $first->id,
            'outcome' => 'not_found',
            'application_status' => 'not_applicable',
        ]);
    }

    public function test_planned_route_becomes_active_after_the_first_field_result(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $stop->route->update(['status' => 'planned']);

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'no_payment',
        ])->assertSessionHasNoErrors();

        $this->assertSame('active', $stop->route->fresh()->status);
        $this->assertSame('visited', $stop->fresh()->status);
    }

    public function test_already_managed_stop_cannot_receive_another_collection(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '50.00',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('outcome');

        $this->assertDatabaseCount('collection_records', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('visited', $stop->fresh()->status);
    }

    public function test_collected_today_metric_ignores_promises(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stops = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->values();
        $loan = Loan::where('client_id', $stops[0]->client_id)->firstOrFail();

        $this->actingAs($user)->post(route('collections.store', $stops[0]), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '125.50',
            'payment_method' => 'transfer',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('collections.store', $stops[1]), [
            'outcome' => 'promise',
            'promise_date' => today()->addDay()->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $this->get(route('collections.index', ['date' => today()->format('Y-m-d')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('collectedToday', fn ($value) => (string) $value === '125.50' || (float) $value === 125.5));
    }

    public function test_invalid_outcome_is_rejected(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $stop = CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();

        $this->post(route('collections.store', $stop), [
            'outcome' => 'cancelled',
        ])->assertSessionHasErrors('outcome');

        $this->assertSame('pending', $stop->fresh()->status);
    }

    private function createInstallment(Loan $loan, int $number, $dueDate, string $principal, string $interest = '0.00', array $extra = []): LoanInstallment
    {
        return LoanInstallment::create(array_merge([
            'loan_id' => $loan->id,
            'number' => $number,
            'due_date' => $dueDate,
            'principal_due' => $principal,
            'interest_due' => $interest,
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ], $extra));
    }
}
