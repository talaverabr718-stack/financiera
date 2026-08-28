<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionRouteRequest;
use App\Http\Requests\MarkRouteStopVisitedRequest;
use App\Models\Client;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\SellerProfile;
use App\Services\AuditService;
use App\Services\DocumentSequenceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionRouteController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();
        $routes = CollectionRoute::with(['collector.user', 'stops.client'])
            ->whereDate('scheduled_date', $date)->orderBy('starts_at')->get();
        $openRoutes = $routes->filter->isOpenForField()->values();
        $completedRoutes = $routes->reject->isOpenForField()->values();
        $requested = $routes->firstWhere('id', $request->integer('route'));
        $selectedRoute = $requested ?? $openRoutes->first();

        return Inertia::render('Routes/Index', [
            'routes' => $routes,
            'openRoutes' => $openRoutes,
            'completedRoutes' => $completedRoutes,
            'selectedRoute' => $selectedRoute,
            'date' => $date,
            'googleMapsKey' => config('services.google_maps.key'),
            'endpoints' => ['index' => route('routes.index'), 'create' => route('routes.create'), 'visitTemplate' => route('routes.stops.visit',['stop'=>'__STOP__'])],
        ]);
    }

    public function create()
    {
        return $this->form(new CollectionRoute);
    }

    public function store(CollectionRouteRequest $request, DocumentSequenceService $sequences)
    {
        $data = $request->validated();

        $routes = DB::transaction(function () use ($data, $sequences) {
            $start = Carbon::parse($data['scheduled_date'])->startOfDay();
            $end = Carbon::parse($data['scheduled_until'] ?? $data['scheduled_date'])->startOfDay();
            $created = collect();

            foreach (CarbonPeriod::create($start, $end) as $date) {
                $route = CollectionRoute::create([
                    'code' => $sequences->next('collection_route', 'RUT-'),
                    'name' => $data['name'],
                    'scheduled_date' => $date,
                    'collector_id' => $data['collector_id'],
                    'starts_at' => $data['starts_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
                foreach ($data['client_ids'] as $position => $clientId) {
                    $route->stops()->create(['client_id' => $clientId, 'position' => $position + 1]);
                }
                $created->push($route);
            }

            return $created;
        });

        $firstRoute = $routes->firstOrFail();
        $message = $routes->count() === 1 ? 'Ruta de cobranza creada.' : "Se crearon {$routes->count()} rutas para el rango seleccionado.";

        return redirect()->route('routes.index', ['date' => $firstRoute->scheduled_date->format('Y-m-d'), 'route' => $firstRoute->id])->with('success', $message);
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

    public function markVisited(MarkRouteStopVisitedRequest $request, CollectionRouteStop $stop, AuditService $audit)
    {
        $stop = DB::transaction(function () use ($request, $stop, $audit) {
            $lockedStop = CollectionRouteStop::with(['client', 'route'])->lockForUpdate()->findOrFail($stop->id);

            if ($lockedStop->status !== 'visited') {
                $before = $lockedStop->only(['status', 'visited_at', 'notes']);
                $lockedStop->update([
                    'status' => 'visited',
                    'visited_at' => now(),
                    'notes' => $request->validated('notes') ?: $lockedStop->notes,
                ]);

                if ($lockedStop->route->status === 'planned') {
                    $lockedStop->route->update(['status' => 'active']);
                }
                if (! $lockedStop->route->stops()->where('status', 'pending')->exists()) {
                    $lockedStop->route->update(['status' => 'completed']);
                }

                $audit->record(
                    $lockedStop,
                    'route_stop_visited',
                    $request->user()?->id,
                    $before,
                    $lockedStop->only(['status', 'visited_at', 'notes']),
                    'Visita confirmada desde el panel de rutas',
                    ['collection_route_id' => $lockedStop->collection_route_id],
                    $request->header('X-Request-ID'),
                    $request->ip(),
                    $request->userAgent(),
                );
            }

            return $lockedStop->fresh(['client', 'route']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'stop' => [
                    'id' => $stop->id,
                    'status' => $stop->status,
                    'visited_at' => $stop->visited_at?->timezone(config('app.timezone'))->toIso8601String(),
                    'visited_at_label' => $stop->visitedAtLabel(),
                ],
                'route_status' => $stop->route->status,
            ]);
        }

        return back()->with('success', 'Cliente marcado como visitado.');
    }

    private function form(CollectionRoute $route)
    {
        $selectedIds = $route->exists ? $route->stops->pluck('client_id')->all() : [];
        $lockedIds = $route->exists ? $route->stops->filter(fn ($stop) => $stop->records->isNotEmpty())->pluck('client_id')->all() : [];
        return Inertia::render('Routes/Form', [
            'route' => $route,
            'sellers' => SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'collections')->get(),
            'clients' => Client::with('activeAssignment')->where('status', 'active')->orderBy('full_name')->get(),
            'selectedIds' => $selectedIds, 'lockedIds' => $lockedIds, 'editing' => $route->exists,
            'endpoints' => ['index' => route('routes.index'), 'save' => $route->exists ? route('routes.update',$route) : route('routes.store')],
        ]);
    }
}
