<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Requests\TransferClientRequest;
use App\Models\Client;
use App\Models\SellerProfile;
use App\Services\ClientService;
use App\Services\DelinquencyTrackingService;
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
        return $this->formPage(new Client);
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
        $client->load(['activeAssignment.seller.user', 'portfolioAssignments.seller.user', 'portfolioAssignments.previousSeller.user', 'portfolioAssignments.assignedBy', 'assets', 'creditApplications.product', 'creditApplications.disbursement.disbursedBy', 'loans.installments.paymentAllocations.payment.reversal', 'loans.activeDelinquencyCase', 'loans.collectionRecords.recordedBy', 'usedGuarantees.guarantor', 'usedGuarantees.loan', 'collectionRecords.collector.user', 'collectionRecords.recordedBy']);

        $timeline = collect([['type' => 'client', 'date' => $client->created_at, 'title' => 'Cliente registrado', 'description' => 'Se creó el expediente '.$client->code, 'url' => null]])
            ->concat($client->portfolioAssignments->map(fn ($item) => ['type' => 'activity', 'date' => $item->assigned_at, 'title' => $item->previousSeller ? 'Transferencia de gestor' : 'Asignación de cartera', 'description' => ($item->previousSeller ? $item->previousSeller->user->name.' → ' : '').$item->seller->user->name.' · '.$item->reason, 'url' => null]))
            ->concat($client->creditApplications->map(fn ($item) => ['type' => 'application', 'date' => $item->created_at, 'title' => 'Solicitud '.$item->number, 'description' => 'Estado: '.$item->status.' · '.$item->currency.' '.number_format((float) $item->requested_amount, 2), 'url' => route('applications.show', $item)]))
            ->concat($client->creditApplications->filter->disbursement->map(fn ($item) => ['type' => 'credit', 'date' => $item->disbursement->created_at, 'title' => 'Desembolso '.$item->disbursement->number, 'description' => $item->currency.' '.number_format((float) $item->disbursement->amount, 2), 'url' => route('loans.show', $item->loan)]))
            ->concat($client->collectionRecords->map(fn ($item) => ['type' => 'collection', 'date' => $item->recorded_at, 'title' => 'Gestión de cobranza', 'description' => ($item->amount ? 'C$ '.number_format((float) $item->amount, 2).' · ' : '').($item->notes ?: $item->outcome), 'url' => $item->loan_id ? route('loans.show', $item->loan_id) : null]))
            ->sortByDesc('date')->values();
        $delinquency = app(DelinquencyTrackingService::class)->summarizeClient($client);

        $clientData = array_merge($client->toArray(), [
            'seller_name' => $client->activeAssignment?->seller?->user?->name,
            'seller_code' => $client->activeAssignment?->seller?->code,
            'total_income' => (float) $client->estimated_income + (float) $client->other_income,
            'available' => max(0, (float) $client->estimated_income + (float) $client->other_income - (float) $client->estimated_expenses),
            'portfolio_assignments' => $client->portfolioAssignments->sortByDesc('assigned_at')->values()->map(fn ($assignment) => [
                'id' => $assignment->id,
                'seller_name' => $assignment->seller->user->name,
                'reason' => $assignment->reason,
                'assigned_at' => $assignment->assigned_at,
                'ended_at' => $assignment->ended_at,
            ]),
            'applications' => $client->creditApplications->sortByDesc('created_at')->values()->map(fn ($application) => [
                'id' => $application->id,
                'number' => $application->number,
                'product' => $application->product->name,
                'requested_amount' => $application->requested_amount,
                'currency' => $application->currency,
                'status' => $application->status,
                'url' => route('applications.show', $application),
            ]),
        ]);

        return Inertia::render('Clients/Show', [
            'client' => $clientData,
            'timeline' => $timeline,
            'delinquency' => $delinquency,
            'sellers' => $this->sellers(),
            'endpoints' => [
                'edit' => route('clients.edit', $client),
                'destroy' => route('clients.destroy', $client),
                'transfer' => route('clients.transfer', $client),
                'create_application' => route('applications.create', ['client_id' => $client->id]),
            ],
        ]);
    }

    public function edit(Client $client)
    {
        $client->load(['assets']);

        return $this->formPage($client);
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

    public function transfer(TransferClientRequest $request, Client $client)
    {
        $data = $request->validated();
        $this->clients->transfer($client, (int) $data['seller_id'], $data['reason']);

        return back()->with('success', 'Cartera transferida correctamente.');
    }

    private function sellers()
    {
        return SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'prospecting')->get();
    }

    private function formPage(Client $client)
    {
        return Inertia::render('Clients/Form', [
            'client' => $client,
            'sellers' => $this->sellers(),
            'locations' => config('nicaragua.locations'),
            'editing' => $client->exists,
            'endpoints' => [
                'index' => route('clients.index'),
                'save' => $client->exists ? route('clients.update', $client) : route('clients.store'),
            ],
        ]);
    }
}
