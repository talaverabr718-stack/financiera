<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\SellerProfile;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function index(Request $request)
    {
        $clients = Client::query()->with(['activeAssignment.seller.user', 'loans.installments', 'assets', 'usedGuarantees.guarantor', 'collectionRecords'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%')->orWhere('identity_number', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('seller'), fn ($q) => $q->whereHas('activeAssignment', fn ($assignment) => $assignment->where('seller_id', $request->integer('seller'))))
            ->latest()->paginate(12)->withQueryString();

        $selectedClient = $request->filled('client')
            ? $clients->getCollection()->firstWhere('id', $request->integer('client'))
            : $clients->getCollection()->first();
        $selectedClient?->load(['assets', 'creditApplications', 'loans.installments', 'usedGuarantees.guarantor', 'collectionRecords']);

        return Inertia::render('Clients/Index', compact('clients', 'selectedClient') + [
            'sellers' => $this->sellers(),
            'filters' => $request->only('search', 'status', 'seller'),
            'endpoints' => [
                'index' => route('clients.index'),
                'create' => route('clients.create'),
            ],
        ]);
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client, 'sellers' => $this->sellers()]);
    }

    public function sellerOptions(Request $request)
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $sellers = SellerProfile::query()->with('user:id,name')
            ->where('status', 'active')->whereJsonContains('capabilities', 'prospecting')
            ->where(fn ($query) => $query->where('code', 'like', '%'.$term.'%')
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%')))
            ->orderBy('code')->limit(15)->get()
            ->unique('id')->values()->map(fn (SellerProfile $seller) => [
                'id' => $seller->id,
                'label' => $seller->user->name,
                'description' => $seller->code,
            ]);

        return response()->json(['data' => $sellers]);
    }

    public function store(ClientRequest $request)
    {
        $client = $this->clients->create($request->validated());

        return redirect()->route('clients.show', $client)->with('success', 'Cliente registrado correctamente.');
    }

    public function show(Client $client)
    {
        $client->load(['activeAssignment.seller.user', 'portfolioAssignments.seller.user', 'portfolioAssignments.assignedBy', 'assets', 'creditApplications.product', 'creditApplications.disbursement.disbursedBy', 'loans.installments', 'loans.collectionRecords.recordedBy', 'usedGuarantees.guarantor', 'usedGuarantees.loan', 'collectionRecords.collector.user', 'collectionRecords.recordedBy']);

        $timeline = collect([['type' => 'client', 'date' => $client->created_at, 'title' => 'Cliente registrado', 'description' => 'Se creó el expediente '.$client->code, 'url' => null]])
            ->concat($client->portfolioAssignments->map(fn ($item) => ['type' => 'activity', 'date' => $item->assigned_at, 'title' => 'Asignación de cartera', 'description' => $item->seller->user->name.' · '.$item->reason, 'url' => null]))
            ->concat($client->creditApplications->map(fn ($item) => ['type' => 'application', 'date' => $item->created_at, 'title' => 'Solicitud '.$item->number, 'description' => 'Estado: '.$item->status.' · '.$item->currency.' '.number_format((float) $item->requested_amount, 2), 'url' => route('applications.show', $item)]))
            ->concat($client->creditApplications->filter->disbursement->map(fn ($item) => ['type' => 'credit', 'date' => $item->disbursement->created_at, 'title' => 'Desembolso '.$item->disbursement->number, 'description' => $item->currency.' '.number_format((float) $item->disbursement->amount, 2), 'url' => route('loans.show', $item->loan)]))
            ->concat($client->collectionRecords->map(fn ($item) => ['type' => 'collection', 'date' => $item->recorded_at, 'title' => 'Gestión de cobranza', 'description' => ($item->amount ? 'C$ '.number_format((float) $item->amount, 2).' · ' : '').($item->notes ?: $item->outcome), 'url' => $item->loan_id ? route('loans.show', $item->loan_id) : null]))
            ->sortByDesc('date')->values();

        return view('clients.show', compact('client', 'timeline'));
    }

    public function edit(Client $client)
    {
        $client->load(['assets']);

        return view('clients.form', compact('client') + ['sellers' => $this->sellers()]);
    }

    public function update(ClientRequest $request, Client $client)
    {
        $this->clients->update($client, $request->validated());

        return redirect()->route('clients.show', $client)->with('success', 'Expediente actualizado.');
    }

    public function destroy(Client $client)
    {
        $client->update(['status' => 'inactive']);

        return back()->with('success', 'Cliente inactivado sin eliminar su historial.');
    }

    public function transfer(Request $request, Client $client)
    {
        $data = $request->validate(['seller_id' => ['required', 'exists:seller_profiles,id'], 'reason' => ['required', 'string', 'max:500']]);
        $this->clients->transfer($client, (int) $data['seller_id'], $data['reason']);

        return back()->with('success', 'Cartera transferida correctamente.');
    }

    private function sellers()
    {
        return SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'prospecting')->get();
    }
}
