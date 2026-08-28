<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\CollectionRoute;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionRouteModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_route_skips_a_code_already_used_by_seed_data(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $seller = SellerProfile::query()->firstOrFail();
        $client = Client::query()->firstOrFail();
        DB::table('document_sequences')->where('key', 'collection_route')->delete();

        $this->post(route('routes.store'), [
            'name' => 'Ruta Estelí nueva',
            'scheduled_date' => today()->format('Y-m-d'),
            'collector_id' => $seller->id,
            'starts_at' => '08:00',
            'client_ids' => [$client->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('collection_routes', [
            'name' => 'Ruta Estelí nueva',
            'code' => 'RUT-000002',
        ]);
        $this->assertSame(1, CollectionRoute::query()->where('code', 'RUT-000001')->count());
    }

    public function test_route_is_created_with_ordered_client_stops(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'B-1', 'name' => 'Central']);
        $zone = Zone::create(['branch_id' => $branch->id, 'code' => 'Z-1', 'name' => 'Centro']);
        $seller = SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => 'V-1', 'status' => 'active']);
        $clients = collect([1, 2])->map(fn ($number) => Client::create(['code' => 'CLI-'.$number, 'full_name' => 'Cliente '.$number, 'address' => 'Estelí', 'status' => 'active']));

        $this->post(route('routes.store'), ['name' => 'Ruta Centro', 'scheduled_date' => today()->format('Y-m-d'), 'collector_id' => $seller->id, 'client_ids' => $clients->pluck('id')->all()])->assertSessionHasNoErrors();

        $route = CollectionRoute::with('stops')->firstOrFail();
        $this->assertSame([1, 2], $route->stops->pluck('position')->all());
    }

    public function test_daily_routes_are_created_for_an_inclusive_date_range(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'B-RANGE', 'name' => 'Central']);
        $zone = Zone::create(['branch_id' => $branch->id, 'code' => 'Z-RANGE', 'name' => 'Centro']);
        $seller = SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => 'V-RANGE', 'status' => 'active', 'capabilities' => ['collections']]);
        $client = Client::create(['code' => 'CLI-RANGE', 'full_name' => 'Cliente del rango', 'address' => 'Estelí', 'status' => 'active']);

        $this->post(route('routes.store'), [
            'name' => 'Ruta semanal',
            'scheduled_date' => '2025-04-17',
            'scheduled_until' => '2025-04-19',
            'collector_id' => $seller->id,
            'client_ids' => [$client->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Se crearon 3 rutas para el rango seleccionado.');

        $dates = CollectionRoute::orderBy('scheduled_date')->get()->map(fn ($route) => $route->scheduled_date->format('Y-m-d'))->all();
        $this->assertSame(['2025-04-17', '2025-04-18', '2025-04-19'], $dates);
        $this->assertSame(3, CollectionRoute::withCount('stops')->get()->sum('stops_count'));
    }

    public function test_route_status_can_be_updated_without_changing_client_status(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with('stops')->firstOrFail();

        $this->patch(route('routes.status', $route), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $route->fresh()->status);
        $this->assertSame('pending', $route->stops->last()->fresh()->status);
        $this->assertSame('active', $route->stops->last()->client->fresh()->status);
    }

    public function test_client_visit_can_be_confirmed_from_route_without_creating_a_collection(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $stop = CollectionRoute::with('stops.client')->firstOrFail()->stops->firstWhere('status', 'pending');
        $clientStatus = $stop->client->status;

        $this->actingAs($user)->patchJson(route('routes.stops.visit', $stop), [
            'notes' => 'Cliente atendido en su domicilio.',
        ])->assertOk()
            ->assertJsonPath('stop.status', 'visited')
            ->assertJsonPath('stop.visited_at_label', $stop->fresh()->visitedAtLabel());

        $this->assertSame('visited', $stop->fresh()->status);
        $this->assertNotNull($stop->fresh()->visited_at);
        $this->assertSame($clientStatus, $stop->client->fresh()->status);
        $this->assertDatabaseCount('collection_records', 0);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => $stop->getMorphClass(),
            'auditable_id' => $stop->id,
            'action' => 'route_stop_visited',
        ]);
    }

    public function test_completed_routes_move_to_the_completed_panel(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $completed = CollectionRoute::with('stops')->firstOrFail();
        $open = CollectionRoute::create([
            'code' => 'RUT-OPEN-01',
            'name' => 'Ruta aún pendiente del día',
            'scheduled_date' => $completed->scheduled_date,
            'collector_id' => $completed->collector_id,
            'starts_at' => '10:00',
        ]);
        $open->stops()->create([
            'client_id' => Client::query()->firstOrFail()->id,
            'position' => 1,
            'status' => 'pending',
        ]);

        foreach ($completed->stops->where('status', 'pending') as $stop) {
            $this->actingAs($user)->patchJson(route('routes.stops.visit', $stop), [])->assertOk();
        }

        $this->assertSame('completed', $completed->fresh()->status);

        $this->get(route('routes.index', [
            'date' => $completed->scheduled_date->format('Y-m-d'),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Index')->where('openRoutes.0.id', $open->id)
            ->where('completedRoutes.0.id', $completed->id)->where('selectedRoute.id', $open->id));

        $this->get(route('routes.index', [
            'date' => $completed->scheduled_date->format('Y-m-d'),
            'route' => $completed->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedRoute.id', $completed->id));
    }

    public function test_selected_route_only_lists_its_assigned_clients(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $selectedRoute = CollectionRoute::with('stops.client')->firstOrFail();
        $otherClient = Client::create([
            'code' => 'CLI-OUTSIDE',
            'full_name' => 'Cliente fuera de la ruta',
            'address' => 'Estelí',
            'status' => 'active',
        ]);
        $otherRoute = CollectionRoute::create([
            'code' => 'RUT-OUTSIDE',
            'name' => 'Ruta secundaria',
            'scheduled_date' => $selectedRoute->scheduled_date,
            'collector_id' => $selectedRoute->collector_id,
        ]);
        $otherRoute->stops()->create(['client_id' => $otherClient->id, 'position' => 1]);

        $response = $this->get(route('routes.index', [
            'date' => $selectedRoute->scheduled_date->format('Y-m-d'),
            'route' => $selectedRoute->id,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRoute.id', $selectedRoute->id)
            ->where('selectedRoute.stops.0.client.full_name', $selectedRoute->stops->first()->client->full_name)
            ->where('endpoints.visitTemplate', route('routes.stops.visit', ['stop' => '__STOP__'])));
    }

    public function test_google_maps_routes_library_maps_the_ordered_stops(): void
    {
        $this->seed(ClientModuleSeeder::class);
        config(['services.google_maps.key' => 'test-browser-key']);
        $selectedRoute = CollectionRoute::with('stops.client')->firstOrFail();
        $selectedRoute->stops->each(fn ($stop, $index) => $stop->client->update([
            'latitude' => 13.0918 + ($index * .001),
            'longitude' => -86.3538 - ($index * .001),
        ]));

        $this->get(route('routes.index', [
            'date' => $selectedRoute->scheduled_date->format('Y-m-d'),
            'route' => $selectedRoute->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('googleMapsKey', 'test-browser-key')->where('selectedRoute.id', $selectedRoute->id));
    }

    public function test_route_can_be_edited_and_unmanaged_clients_can_be_removed(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with('stops')->firstOrFail();
        $keptClientId = $route->stops->last()->client_id;

        $this->get(route('routes.edit', $route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Form')->where('editing', true)->where('route.id', $route->id));

        $this->put(route('routes.update', $route), [
            'name' => 'Ruta actualizada',
            'scheduled_date' => today()->addDay()->format('Y-m-d'),
            'collector_id' => $route->collector_id,
            'starts_at' => '09:00',
            'client_ids' => [$keptClientId],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ruta actualizada', $route->fresh()->name);
        $this->assertSame([$keptClientId], $route->stops()->pluck('client_id')->all());
        $this->assertSame([1], $route->stops()->pluck('position')->all());
    }

    public function test_client_with_collection_history_cannot_be_removed_from_route(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $route = CollectionRoute::with('stops')->firstOrFail();
        $managedStop = $route->stops->first();
        $remainingIds = $route->stops->where('id', '!=', $managedStop->id)->pluck('client_id')->all();

        $this->actingAs($user)->post(route('collections.store', $managedStop), [
            'outcome' => 'no_payment',
            'notes' => 'Visita registrada',
        ])->assertSessionHasNoErrors();

        $this->put(route('routes.update', $route), [
            'name' => $route->name,
            'scheduled_date' => $route->scheduled_date->format('Y-m-d'),
            'collector_id' => $route->collector_id,
            'client_ids' => $remainingIds,
        ])->assertSessionHasErrors('client_ids');

        $this->assertDatabaseHas('collection_route_stops', ['id' => $managedStop->id, 'client_id' => $managedStop->client_id]);
    }
}
