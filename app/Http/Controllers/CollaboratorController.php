<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollaboratorRequest;
use App\Models\Branch;
use App\Models\SellerProfile;
use App\Models\Zone;
use App\Services\CollaboratorService;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function __construct(private CollaboratorService $collaborators) {}

    public function index(Request $request)
    {
        $collaborators = SellerProfile::with(['user', 'branch', 'zone'])
            ->withCount(['portfolioAssignments as active_clients_count' => fn ($q) => $q->whereNull('ended_at'), 'collectionRoutes'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', '%'.$request->search.'%')
                ->orWhere('identity_number', 'like', '%'.$request->search.'%')
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('capability'), fn ($q) => $q->whereJsonContains('capabilities', $request->capability))
            ->orderByDesc('status')->latest()->paginate(15)->withQueryString();

        return view('collaborators.index', compact('collaborators'));
    }

    public function create()
    {
        return $this->form(new SellerProfile);
    }

    public function store(CollaboratorRequest $request)
    {
        $collaborator = $this->collaborators->create($request->validated());

        return redirect()->route('collaborators.show', $collaborator)->with('success', 'Colaborador registrado correctamente.');
    }

    public function show(SellerProfile $collaborator)
    {
        $collaborator->load(['user', 'branch', 'zone', 'portfolioAssignments.client', 'collectionRoutes' => fn ($q) => $q->latest('scheduled_date')->limit(10)]);

        return view('collaborators.show', compact('collaborator'));
    }

    public function edit(SellerProfile $collaborator)
    {
        return $this->form($collaborator->load('user'));
    }

    public function update(CollaboratorRequest $request, SellerProfile $collaborator)
    {
        $this->collaborators->update($collaborator, $request->validated());

        return redirect()->route('collaborators.show', $collaborator)->with('success', 'Colaborador actualizado.');
    }

    public function destroy(SellerProfile $collaborator)
    {
        $this->collaborators->inactivate($collaborator);

        return back()->with('success', 'Colaborador inactivado sin eliminar su historial.');
    }

    private function form(SellerProfile $collaborator)
    {
        return view('collaborators.form', [
            'collaborator' => $collaborator,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'zones' => Zone::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
