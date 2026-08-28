<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Requests\TransferClientRequest;
use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\SellerProfile;
use App\Services\ClientService;
use App\Services\DelinquencyTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function index(Request $request)
    {
        $query = $this->directoryQuery($request);
        $board = $this->directoryBoard($query);
        $directoryRelations = ['activeAssignment.seller.user', 'loans.installments', 'assets', 'usedGuarantees.guarantor', 'collectionRecords', 'creditApplications'];

        $clients = (clone $query)->with($directoryRelations)
            ->latest()->paginate(12)->withQueryString();

        $selectedClient = $request->filled('client')
            ? (clone $query)->with($directoryRelations)->find($request->integer('client'))
            : $clients->getCollection()->first();
        $selectedClient?->load(['assets', 'creditApplications', 'loans.installments', 'usedGuarantees.guarantor', 'collectionRecords']);

        return Inertia::render('Clients/Index', compact('clients', 'selectedClient') + [
            'board' => $board,
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
                'label' => $seller->display_name,
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
            ->concat($client->portfolioAssignments->map(fn ($item) => ['type' => 'activity', 'date' => $item->assigned_at, 'title' => $item->previousSeller ? 'Transferencia de gestor' : 'Asignación de cartera', 'description' => ($item->previousSeller ? $item->previousSeller->display_name.' → ' : '').$item->seller->display_name.' · '.$item->reason, 'url' => null]))
            ->concat($client->creditApplications->map(fn ($item) => ['type' => 'application', 'date' => $item->created_at, 'title' => 'Solicitud '.$item->number, 'description' => 'Estado: '.$item->status.' · '.$item->currency.' '.number_format((float) $item->requested_amount, 2), 'url' => route('applications.show', $item)]))
            ->concat($client->creditApplications->filter->disbursement->map(fn ($item) => ['type' => 'credit', 'date' => $item->disbursement->created_at, 'title' => 'Desembolso '.$item->disbursement->number, 'description' => $item->currency.' '.number_format((float) $item->disbursement->amount, 2), 'url' => $item->loan ? route('loans.show', $item->loan) : null]))
            ->concat($client->collectionRecords->map(fn ($item) => ['type' => 'collection', 'date' => $item->recorded_at, 'title' => 'Gestión de cobranza', 'description' => ($item->amount ? 'C$ '.number_format((float) $item->amount, 2).' · ' : '').($item->notes ?: $item->outcome), 'url' => $item->loan_id ? route('loans.show', $item->loan_id) : null]))
            ->sortByDesc('date')->values();
        $delinquency = app(DelinquencyTrackingService::class)->summarizeClient($client);

        $clientData = array_merge($client->toArray(), [
            'seller_name' => $client->activeAssignment?->seller?->user?->name,
            'seller_code' => $client->activeAssignment?->seller?->code,
            'total_income' => (float) $client->estimated_income + (float) $client->other_income,
            'available' => max(0, (float) $client->estimated_income + (float) $client->other_income - (float) $client->estimated_expenses),
            'portfolio_assignments' => $client->portfolioAssignments
                ->sort(fn ($left, $right) => [$right->assigned_at?->timestamp ?? 0, $right->id] <=> [$left->assigned_at?->timestamp ?? 0, $left->id])
                ->values()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'seller_name' => $assignment->seller->display_name,
                    'reason' => $assignment->reason,
                    'assigned_at' => $assignment->assigned_at,
                    'ended_at' => $assignment->ended_at,
                ]),
            'applications' => $client->creditApplications->sortByDesc('created_at')->values()->map(fn ($application) => [
                'id' => $application->id,
                'number' => $application->number,
                'product' => $application->product?->name,
                'requested_amount' => $application->requested_amount,
                'currency' => $application->currency,
                'status' => $application->status,
                'url' => route('applications.show', $application),
            ]),
        ]);

        return Inertia::render('Clients/Show', [
            'client' => $clientData,
            'timeline' => $timeline,
            'cycles' => $this->financialCycles($client),
            'delinquency' => $delinquency,
            'sellers' => $this->sellers(),
            'endpoints' => [
                'index' => route('clients.index'),
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

    private function directoryQuery(Request $request)
    {
        return Client::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%')->orWhere('identity_number', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('seller'), fn ($q) => $q->whereHas('activeAssignment', fn ($assignment) => $assignment->where('seller_id', $request->integer('seller'))));
    }

    private function financialCycles(Client $client): array
    {
        $applicationStatus = ['draft' => 'Borrador', 'submitted' => 'Enviada', 'review' => 'En revisión', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada', 'disbursed' => 'Desembolsada'];
        $installmentStatus = ['paid' => 'Pagada', 'pending' => 'Pendiente', 'overdue' => 'Vencida', 'partial' => 'Parcial', 'cancelled' => 'Cancelada', 'refinanced' => 'Refinanciada', 'voided' => 'Anulada', 'waived' => 'Condona', 'written_off' => 'Castigada'];

        return $client->creditApplications
            ->sortBy(fn (CreditApplication $application) => $application->applied_on?->timestamp ?? $application->created_at?->timestamp)
            ->values()
            ->map(function (CreditApplication $application) use ($client, $applicationStatus, $installmentStatus) {
                $loan = $client->loans->firstWhere('credit_application_id', $application->id);
                $disbursement = $application->disbursement;
                $loanUrl = $loan ? route('loans.show', $loan) : null;
                $rows = [[
                    'kind' => 'solicitud',
                    'label' => 'Solicitud',
                    'reference' => $application->number,
                    'date' => optional($application->applied_on ?? $application->created_at)->toDateString(),
                    'amount' => number_format((float) $application->requested_amount, 2, '.', ''),
                    'currency' => $application->currency,
                    'status' => $applicationStatus[$application->status] ?? $application->status,
                    'url' => route('applications.show', $application),
                ]];

                if ($disbursement || ($loan && $loan->disbursed_at)) {
                    $rows[] = [
                        'kind' => 'desembolso',
                        'label' => 'Desembolso',
                        'reference' => $disbursement?->number ?? $loan?->number,
                        'date' => optional($disbursement?->disbursed_at ?? $loan?->disbursed_at ?? $disbursement?->created_at)->toDateString(),
                        'amount' => number_format((float) ($disbursement?->amount ?? $loan?->principal ?? 0), 2, '.', ''),
                        'currency' => $disbursement?->currency ?? $loan?->currency ?? $application->currency,
                        'status' => 'Desembolsado',
                        'url' => $loanUrl,
                    ];
                }

                foreach (($loan?->installments ?? collect())->sortBy('number') as $installment) {
                    $rows[] = [
                        'kind' => 'cuota',
                        'label' => 'Cuota '.$installment->number,
                        'reference' => $loan->number,
                        'date' => $installment->due_date?->toDateString(),
                        'amount' => $installment->amountDue(),
                        'currency' => $loan->currency,
                        'status' => $installmentStatus[$installment->status] ?? $installment->status,
                        'url' => $loanUrl,
                    ];
                }

                return [
                    'id' => $application->id,
                    'title' => $application->number,
                    'product' => $application->product?->name,
                    'rows' => $rows,
                ];
            })
            ->all();
    }

    private function directoryBoard($query): array
    {
        $now = now()->timezone(config('app.timezone'));
        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();
        $blocked = (clone $query)->where('status', 'blocked')->count();
        $inArrears = (clone $query)->whereHas('loans', fn ($loans) => $loans->where('status', 'delinquent'))->count();

        $situation = $total === 0
            ? 'Todavía no hay clientes registrados.'
            : "{$total} cliente".($total === 1 ? '' : 's')." · {$active} activo".($active === 1 ? '' : 's')." · {$inactive} inactivo".($inactive === 1 ? '' : 's').($blocked > 0 ? " · {$blocked} bloqueado".($blocked === 1 ? '' : 's') : '').($inArrears > 0 ? " · {$inArrears} en mora" : '');

        return [
            'briefing' => [
                'title' => 'Clientes',
                'date_label' => $now->isoFormat('dddd D [de] MMMM'),
                'situation' => $situation,
            ],
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'blocked' => $blocked,
                'in_arrears' => $inArrears,
            ],
            'mix' => [
                ['key' => 'active', 'label' => 'Activos', 'value' => $active],
                ['key' => 'inactive', 'label' => 'Inactivos', 'value' => $inactive],
                ['key' => 'blocked', 'label' => 'Bloqueados', 'value' => $blocked],
            ],
            'growth' => $this->directoryGrowth($query, $now),
        ];
    }

    private function directoryGrowth($query, $now): array
    {
        $start = $now->copy()->startOfMonth()->subMonths(11);
        $prior = (clone $query)->where('created_at', '<', $start)->count();
        $created = (clone $query)
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->pluck('created_at');

        $buckets = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonths($offset);
            $buckets[$month->format('Y-m')] = [
                'label' => $month->translatedFormat('M'),
                'added' => 0,
            ];
        }

        foreach ($created as $date) {
            $key = Carbon::parse($date)->timezone(config('app.timezone'))->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['added']++;
            }
        }

        $running = $prior;
        $points = [];
        foreach ($buckets as $bucket) {
            $running += $bucket['added'];
            $points[] = [
                'label' => $bucket['label'],
                'added' => $bucket['added'],
                'total' => $running,
            ];
        }

        $latest = $points[count($points) - 1] ?? ['added' => 0, 'total' => 0];
        $previous = $points[count($points) - 2] ?? ['total' => 0];

        return [
            'points' => $points,
            'added' => $latest['added'],
            'delta' => ($latest['total'] ?? 0) - ($previous['total'] ?? 0),
        ];
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
