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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CollectionRouteModuleTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_route_status_can_be_updated_but_client_status_cannot_be_changed_from_routes(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with('stops')->firstOrFail();

        $this->patch(route('routes.status', $route), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $route->fresh()->status);
        $this->assertSame('pending', $route->stops->last()->fresh()->status);
        $this->assertFalse(Route::has('routes.stops.update'));
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

        $response->assertOk()
            ->assertSee($selectedRoute->stops->first()->client->full_name)
            ->assertDontSee($otherClient->full_name)
            ->assertSee('Solo lectura')
            ->assertDontSee('Visitado</button>', false);
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
        ]))->assertOk()
            ->assertSee("google.maps.importLibrary('routes')", false)
            ->assertSee('Route.computeRoutes', false)
            ->assertSee("travelMode:'DRIVING'", false)
            ->assertSee('route-distance', false)
            ->assertDontSee('unpkg.com/leaflet', false);
    }

    public function test_route_can_be_edited_and_unmanaged_clients_can_be_removed(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $route = CollectionRoute::with('stops')->firstOrFail();
        $keptClientId = $route->stops->last()->client_id;

        $this->get(route('routes.edit', $route))
            ->assertOk()
            ->assertSee('Editar ruta de cobranza')
            ->assertSee('Guardar cambios');

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
