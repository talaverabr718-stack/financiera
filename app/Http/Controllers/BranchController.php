<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Http\Requests\ZoneRequest;
use App\Models\Branch;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Branches', [
            'branches' => Branch::query()
                ->with(['zones' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name')])
                ->withCount(['zones', 'collaborators'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'tabs' => SettingsController::tabs('branches'),
            'endpoints' => [
                'store' => route('settings.branches.store'),
                'update' => route('settings.branches.update', '__BRANCH__'),
                'status' => route('settings.branches.status', '__BRANCH__'),
                'zoneStore' => route('settings.branches.zones.store', '__BRANCH__'),
                'zoneUpdate' => route('settings.branches.zones.update', ['branch' => '__BRANCH__', 'zone' => '__ZONE__']),
                'zoneStatus' => route('settings.branches.zones.status', ['branch' => '__BRANCH__', 'zone' => '__ZONE__']),
            ],
        ]);
    }

    public function store(BranchRequest $request)
    {
        Branch::create($request->validated());

        return back()->with('success', 'Sucursal creada y disponible para la operación.');
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        DB::transaction(function () use ($request, $branch): void {
            $branch->lockForUpdate()->update($request->validated());
        });

        return back()->with('success', 'Sucursal actualizada sin alterar sus asignaciones.');
    }

    public function status(Request $request, Branch $branch)
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($branch, $validated): void {
            $branch->lockForUpdate()->update(['is_active' => $validated['is_active']]);
        });

        return back()->with('success', $validated['is_active'] ? 'Sucursal activada.' : 'Sucursal desactivada para nuevas asignaciones.');
    }

    public function storeZone(ZoneRequest $request, Branch $branch)
    {
        $branch->zones()->create($request->validated());

        return back()->with('success', 'Zona operativa agregada a la sucursal.');
    }

    public function updateZone(ZoneRequest $request, Branch $branch, Zone $zone)
    {
        DB::transaction(function () use ($request, $zone): void {
            $zone->lockForUpdate()->update($request->validated());
        });

        return back()->with('success', 'Zona operativa actualizada.');
    }

    public function zoneStatus(Request $request, Branch $branch, Zone $zone)
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($zone, $validated): void {
            $zone->lockForUpdate()->update(['is_active' => $validated['is_active']]);
        });

        return back()->with('success', $validated['is_active'] ? 'Zona activada.' : 'Zona desactivada para nuevas asignaciones.');
    }
}
