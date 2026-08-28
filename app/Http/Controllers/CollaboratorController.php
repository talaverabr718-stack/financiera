<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollaboratorRequest;
use App\Models\Branch;
use App\Models\SellerProfile;
use App\Services\CollaboratorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollaboratorController extends Controller
{
    public function __construct(private CollaboratorService $collaborators) {}

    public function index(Request $request)
    {
        $collaborators = SellerProfile::with(['user', 'branch'])
            ->withCount(['portfolioAssignments as active_clients_count' => fn ($q) => $q->whereNull('ended_at'), 'collectionRoutes'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', '%'.$request->search.'%')
                ->orWhere('full_name', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')
                ->orWhere('identity_number', 'like', '%'.$request->search.'%')
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('status')->latest()->paginate(15)->withQueryString();

        return Inertia::render('Collaborators/Index', [
            'collaborators' => $collaborators,
            'filters' => $request->only('search', 'status'),
            'endpoints' => ['index' => route('collaborators.index'), 'create' => route('collaborators.create')],
        ]);
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
        $collaborator->load(['user', 'branch', 'portfolioAssignments.client', 'collectionRoutes' => fn ($q) => $q->latest('scheduled_date')->limit(10)]);

        return Inertia::render('Collaborators/Show', ['collaborator' => $collaborator, 'endpoints' => ['edit' => route('collaborators.edit', $collaborator), 'destroy' => route('collaborators.destroy', $collaborator), 'index' => route('collaborators.index')]]);
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
        return Inertia::render('Collaborators/Form', [
            'collaborator' => $collaborator,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'editing' => $collaborator->exists,
            'endpoints' => ['index' => route('collaborators.index'), 'save' => $collaborator->exists ? route('collaborators.update', $collaborator) : route('collaborators.store')],
        ]);
    }
}
