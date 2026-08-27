<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionRouteRequest;
use App\Models\Client;
use App\Models\CollectionRoute;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectionRouteController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();
        $routes = CollectionRoute::with(['collector.user', 'stops.client'])
            ->whereDate('scheduled_date', $date)->orderBy('starts_at')->get();
        $selectedRoute = $routes->firstWhere('id', $request->integer('route')) ?? $routes->first();

        return view('routes.index', ['routes' => $routes, 'selectedRoute' => $selectedRoute, 'date' => $date, 'googleMapsKey' => config('services.google_maps.key')]);
    }

    public function create()
    {
        return $this->form(new CollectionRoute);
    }

    public function store(CollectionRouteRequest $request)
    {
        $data = $request->validated();

        $route = DB::transaction(function () use ($data) {
            $next = ((int) CollectionRoute::max('id')) + 1;
            $route = CollectionRoute::create($data + ['code' => 'RUT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT)]);
            foreach ($data['client_ids'] as $position => $clientId) {
                $route->stops()->create(['client_id' => $clientId, 'position' => $position + 1]);
            }

            return $route;
        });

        return redirect()->route('routes.index', ['date' => $route->scheduled_date->format('Y-m-d')])->with('success', 'Ruta de cobranza creada.');
    }

    public function edit(CollectionRoute $collectionRoute)
    {
        $collectionRoute->load('stops.records');

        return $this->form($collectionRoute);
    }

    public function update(CollectionRouteRequest $request, CollectionRoute $collectionRoute)
    {
        $data = $request->validated();
        $clientIds = collect($data['client_ids'])->map(fn ($id) => (int) $id)->values();

        DB::transaction(function () use ($collectionRoute, $data, $clientIds): void {
            $route = CollectionRoute::with('stops.records')->lockForUpdate()->findOrFail($collectionRoute->id);
            $removedStops = $route->stops->whereNotIn('client_id', $clientIds);
            if ($removedStops->contains(fn ($stop) => $stop->records->isNotEmpty())) {
                throw ValidationException::withMessages(['client_ids' => 'No puedes quitar clientes que ya tienen gestiones de cobranza registradas en esta ruta.']);
            }

            $removedStops->each->delete();
            $route->stops()->update(['position' => DB::raw('position + 100000')]);
            foreach ($clientIds as $position => $clientId) {
                $route->stops()->updateOrCreate(
                    ['client_id' => $clientId],
                    ['position' => $position + 1]
                );
            }
            $route->update(collect($data)->except('client_ids')->all());
        });

        return redirect()->route('routes.index', ['date' => $data['scheduled_date'], 'route' => $collectionRoute->id])
            ->with('success', 'Ruta y clientes asignados actualizados.');
    }

    public function updateStatus(Request $request, CollectionRoute $collectionRoute)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['planned', 'active', 'completed', 'cancelled'])]]);
        $collectionRoute->update($data);

        return back()->with('success', 'Estado de la ruta actualizado.');
    }

    private function form(CollectionRoute $route)
    {
        return view('routes.form', [
            'route' => $route,
            'sellers' => SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'collections')->get(),
            'clients' => Client::with('activeAssignment')->where('status', 'active')->orderBy('full_name')->get(),
        ]);
    }
}
