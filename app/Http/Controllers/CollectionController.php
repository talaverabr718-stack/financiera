<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizeCollectionPaymentCorrectionRequest;
use App\Http\Requests\AuthorizeSecondCollectionPaymentRequest;
use App\Http\Requests\CorrectCollectionAmountRequest;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\AdditionalCollectionPaymentService;
use App\Services\CollectionPaymentCorrectionAuthorizationService;
use App\Services\CollectionPaymentCorrectionService;
use App\Services\CollectionReceiptPresenter;
use App\Services\PaymentApplicationService;
use App\Services\PortfolioAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function __construct(private PortfolioAccessService $portfolioAccess) {}

    public function index(Request $request, CollectionReceiptPresenter $receipts)
    {
        $date = $request->date('date') ?? today();
        $routes = $this->portfolioAccess->scopeBySeller(CollectionRoute::query(), $request->user(), 'collector_id')
            ->with(['collector.user', 'stops:id,collection_route_id,status'])
            ->whereDate('scheduled_date', $date)
            ->orderBy('starts_at')
            ->get();
        $selectedRoute = $routes->firstWhere('id', $request->integer('agenda_route')) ?? $routes->first();
        $collectedRecords = $this->portfolioAccess->scopeByClient(CollectionRecord::query(), $request->user())
            ->with(['client', 'collector.user'])
            ->where('outcome', 'collected')
            ->whereHas('payment', fn ($query) => $query->whereDoesntHave('reversal'))
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at')
            ->get();
        $collectedToday = $collectedRecords->reduce(
            fn (string $total, CollectionRecord $record) => bcadd($total, (string) $record->amount, 2),
            '0.00',
        );
        $collectedTodayBreakdown = $this->collectedTodayBreakdown($collectedRecords);
        $paymentHistory = $this->portfolioAccess->scopeByClient(CollectionRecord::query(), $request->user())->with([
            'client',
            'loan',
            'payment.client',
            'payment.loan',
            'payment.collector',
            'payment.creator',
            'payment.allocations.installment',
            'payment.reversal',
            'correctionAuthorization.authorizer',
            'correctionAuthorization.usedBy',
            'collector.user',
            'recordedBy',
            'stop.route',
        ])
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
            ->when($request->filled('route'), fn ($q) => $q->whereHas('stop', fn ($q) => $q->where('collection_route_id', $request->integer('route'))))
            ->when($request->filled('collector'), fn ($q) => $q->where('collector_id', $request->integer('collector')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')))
            ->latest('recorded_at')->latest('id')->paginate(8)->withQueryString();
        $paymentHistory->getCollection()->each(function (CollectionRecord $record) use ($receipts): void {
            $record->setAttribute('ticket', $record->payment ? $receipts->fromPayment($record->payment) : null);
            $record->setAttribute('can_authorize_correction', (bool) ($record->payment && ! $record->payment->reversal && ! $record->correction_of_id && ! $record->correctionAuthorization));
            $record->setAttribute('can_correct', (bool) ($record->payment && ! $record->payment->reversal && $record->correctionAuthorization && ! $record->correctionAuthorization->used_at));
        });
        $pendingStops = $this->portfolioAccess->scopeByClient(CollectionRouteStop::query(), $request->user())
            ->select(['id', 'client_id', 'collection_route_id'])
            ->where('status', 'pending')
            ->whereHas('route', fn ($query) => $query->whereDate('scheduled_date', $date))
            ->get();
        $upcomingStops = $this->portfolioAccess->scopeByClient(CollectionRouteStop::query(), $request->user())
            ->with('route:id,name,scheduled_date,starts_at,collector_id')
            ->where('status', 'pending')
            ->whereHas('route', fn ($query) => $query->whereDate('scheduled_date', '>', $date))
            ->get()
            ->sortBy(fn (CollectionRouteStop $stop) => [$stop->route->scheduled_date->toDateString(), $stop->route->starts_at ?? '', $stop->position])
            ->values();
        $upcomingVisits = $upcomingStops->count();
        $lateInstallments = LoanInstallment::query()
            ->with(['loan.client', 'loan.seller.user'])
            ->whereHas('loan', fn ($query) => $this->portfolioAccess
                ->scopeByClient($query, $request->user())
                ->whereIn('status', Loan::COLLECTIBLE_STATUSES))
            ->whereNotIn('status', LoanInstallment::EXCLUDED_STATUSES)
            ->whereDate('due_date', '<', $date)
            ->orderBy('due_date')
            ->orderBy('number')
            ->get()
            ->filter(fn (LoanInstallment $installment) => $installment->isOverdueOn($date))
            ->values();
        $lateInstallments->each(function (LoanInstallment $installment): void {
            $installment->setAttribute('mora', $installment->moraOutstanding());
            $installment->setAttribute('outstanding', $installment->outstandingAmount());
        });
        $lateCollections = $lateInstallments->count();
        $lateInstallments = $lateInstallments->take(6)->values();

        if ($selectedRoute) {
            $selectedRoute->load([
                'stops.client.loans.installments',
                'stops.records' => fn ($query) => $query->where('outcome', 'collected')->whereNotNull('payment_id')->latest('recorded_at')->latest('id'),
                'stops.records.payment.client',
                'stops.records.payment.loan',
                'stops.records.payment.collector',
                'stops.records.payment.creator',
                'stops.records.payment.allocations.installment',
                'stops.records.payment.reversal',
                'stops.additionalPaymentAuthorization.authorizer',
            ]);
            $selectedRoute->withCollectorDues($date);
            $selectedRoute->stops->each(function (CollectionRouteStop $stop) use ($receipts, $selectedRoute): void {
                $activePaymentsToday = $stop->records->filter(fn (CollectionRecord $record) => $record->outcome === 'collected'
                    && $record->payment
                    && ! $record->payment->reversal
                    && $record->recorded_at?->isToday()
                );
                $payment = $activePaymentsToday->first()?->payment;
                $authorization = $stop->additionalPaymentAuthorization;
                $isToday = $selectedRoute->scheduled_date?->isToday() ?? false;

                $stop->setAttribute('ticket', $payment ? $receipts->fromPayment($payment) : null);
                $stop->setAttribute('can_authorize_second_payment', $isToday
                    && $stop->status !== 'pending'
                    && $activePaymentsToday->count() === 1
                    && ! $authorization);
                $stop->setAttribute('can_register_second_payment', $isToday
                    && $activePaymentsToday->count() === 1
                    && $authorization
                    && ! $authorization->used_at);
                $stop->unsetRelation('records');
            });
        }

        $routeOptions = $routes->map(fn (CollectionRoute $route) => [
            'id' => $route->id,
            'name' => $route->name,
            'status' => $route->status,
            'collector' => $route->collector,
            'pending_count' => $route->stops->where('status', 'pending')->count(),
        ])->values();

        return Inertia::render('Collections/Index', [
            'date' => $date,
            'routes' => $routeOptions,
            'collectedToday' => $collectedToday,
            'collectedTodayBreakdown' => $collectedTodayBreakdown,
            'paymentHistory' => $paymentHistory,
            'upcomingVisits' => $upcomingVisits,
            'upcomingStops' => $upcomingStops,
            'pendingStops' => $pendingStops,
            'lateCollections' => $lateCollections,
            'lateInstallments' => $lateInstallments,
            'selectedRoute' => $selectedRoute,
            'storeTemplate' => route('collections.store', ['stop' => '__STOP__']),
            'correctionTemplate' => route('collections.correct-amount', ['record' => '__RECORD__']),
            'correctionAuthorizationTemplate' => route('collections.authorize-correction', ['record' => '__RECORD__']),
            'secondPaymentAuthorizationTemplate' => route('collections.authorize-second-payment', ['stop' => '__STOP__']),
        ]);
    }

    public function authorizeSecondPayment(
        AuthorizeSecondCollectionPaymentRequest $request,
        CollectionRouteStop $stop,
        AdditionalCollectionPaymentService $additionalPayments,
    ) {
        $stop->load('route');
        $this->portfolioAccess->authorizeSellerId($request->user(), (int) $stop->route->collector_id);
        $this->portfolioAccess->authorizeClientId($request->user(), (int) $stop->client_id);
        $additionalPayments->authorize($stop, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Segundo pago habilitado para el gestor asignado.');
    }

    public function authorizeCorrection(
        AuthorizeCollectionPaymentCorrectionRequest $request,
        CollectionRecord $record,
        CollectionPaymentCorrectionAuthorizationService $authorizations,
    ) {
        $this->portfolioAccess->authorizeClientId($request->user(), (int) $record->client_id);
        $authorizations->authorize($record, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Corrección de monto habilitada para el gestor asignado.');
    }

    public function correctAmount(
        CorrectCollectionAmountRequest $request,
        CollectionRecord $record,
        CollectionPaymentCorrectionService $corrections,
        CollectionReceiptPresenter $receipts,
    ) {
        $this->portfolioAccess->authorizeClientId($request->user(), (int) $record->client_id);
        $data = $request->validated();
        $payment = $corrections->correct(
            $record,
            (string) $data['amount'],
            $data['reason'],
            $request->user(),
            $data['correction_authorization_id'],
        );

        return back()
            ->with('success', 'Monto corregido. El pago anterior fue revertido y se generó un recibo nuevo.')
            ->with('receipt', $receipts->fromPayment($payment));
    }

    public function store(
        Request $request,
        CollectionRouteStop $stop,
        PaymentApplicationService $payments,
        CollectionReceiptPresenter $receipts,
        AdditionalCollectionPaymentService $additionalPayments,
    ) {
        $stop->load('route.collector');
        $this->portfolioAccess->authorizeSellerId($request->user(), (int) $stop->route->collector_id);
        $this->portfolioAccess->authorizeClientId($request->user(), (int) $stop->client_id);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['collected', 'promise', 'no_payment', 'not_found'])],
            'loan_id' => [
                'nullable',
                'required_if:outcome,collected',
                Rule::exists('loans', 'id')
                    ->where('client_id', $stop->client_id)
                    ->where(fn ($query) => $query->whereIn('status', ['active', 'delinquent'])),
            ],
            'amount' => ['nullable', 'required_if:outcome,collected', 'decimal:0,2', 'gt:0'],
            'payment_method' => ['nullable', 'required_if:outcome,collected', Rule::in(['cash', 'transfer', 'deposit'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'promise_date' => ['nullable', 'required_if:outcome,promise', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'additional_payment_authorization_id' => ['nullable', 'integer', 'exists:collection_additional_payment_authorizations,id'],
        ], [
            'loan_id.required_if' => 'Este cliente no tiene un préstamo seleccionado para registrar el abono.',
            'loan_id.exists' => 'El préstamo seleccionado no pertenece al cliente o ya no admite abonos.',
            'amount.required_if' => 'Ingresa el monto del abono.',
            'payment_method.required_if' => 'Selecciona la forma de pago.',
        ]);

        $ticket = null;
        $isAdditionalPayment = false;
        $actor = $request->user();

        DB::transaction(function () use ($data, $stop, $payments, $receipts, $additionalPayments, $actor, &$ticket, &$isAdditionalPayment): void {
            $stop = CollectionRouteStop::query()->with('route.collector')->lockForUpdate()->findOrFail($stop->id);

            $authorization = null;
            $isAdditionalPayment = $stop->status !== 'pending';
            if ($isAdditionalPayment) {
                if ($data['outcome'] !== 'collected') {
                    throw ValidationException::withMessages([
                        'outcome' => 'La autorización adicional solo permite registrar un segundo pago.',
                    ]);
                }
                $authorization = $additionalPayments->lockForUse(
                    $stop,
                    $data['additional_payment_authorization_id'] ?? null,
                );
            } elseif (! empty($data['additional_payment_authorization_id'])) {
                throw ValidationException::withMessages([
                    'outcome' => 'La autorización adicional no corresponde a una visita pendiente.',
                ]);
            }

            $recordData = $data;
            $recordData['additional_payment_authorization_id'] = $authorization?->id;
            $record = CollectionRecord::create($recordData + [
                'idempotency_key' => (string) Str::uuid(),
                'collection_route_stop_id' => $stop->id,
                'client_id' => $stop->client_id,
                'loan_id' => $data['loan_id'] ?? null,
                'collector_id' => $stop->route->collector_id,
                'currency' => $data['outcome'] === 'collected'
                    ? (string) (Loan::query()->whereKey($data['loan_id'])->value('currency') ?: 'NIO')
                    : 'NIO',
                'application_status' => $data['outcome'] === 'collected' ? 'pending' : 'not_applicable',
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
            ]);

            if ($data['outcome'] === 'collected') {
                $payment = $payments->applyCollection($record->load('collector'));
                $ticket = $receipts->fromPayment($payment);
            }

            if ($authorization) {
                $additionalPayments->markUsed($authorization, $record->refresh(), $actor);

                return;
            }

            $stop->update([
                'status' => match ($data['outcome']) {
                    'not_found' => 'not_found',
                    'promise' => 'rescheduled',
                    default => 'visited',
                },
                'visited_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($stop->route->status === 'planned') {
                $stop->route->update(['status' => 'active']);
            }

            if (! $stop->route->stops()->where('status', 'pending')->exists()) {
                $stop->route->update(['status' => 'completed']);
            }
        });

        $redirect = back()->with('success', $isAdditionalPayment
            ? 'Segundo pago aplicado. La autorización quedó utilizada.'
            : ($data['outcome'] === 'collected'
                ? 'Cobro aplicado a cartera y ruta actualizada.'
                : 'Gestión de cobranza registrada y ruta actualizada.'));

        if ($ticket) {
            $redirect->with('receipt', $ticket);
        }

        return $redirect;
    }

    /**
     * @param  Collection<int, CollectionRecord>  $records
     * @return list<array{id: int, collector: string, amount: string, payments: list<array{client: string, amount: string}>}>
     */
    private function collectedTodayBreakdown($records): array
    {
        return $records
            ->groupBy(fn (CollectionRecord $record) => $record->collector_id ?: 0)
            ->map(function ($group) {
                $collector = $group->first()?->collector;

                return [
                    'id' => $collector?->id ?: 0,
                    'collector' => $collector?->display_name ?: 'Sin cobrador',
                    'amount' => $group->reduce(
                        fn (string $total, CollectionRecord $record) => bcadd($total, (string) $record->amount, 2),
                        '0.00',
                    ),
                    'payments' => $group->map(fn (CollectionRecord $record) => [
                        'client' => $record->client?->full_name ?: 'Cliente',
                        'amount' => (string) $record->amount,
                    ])->values()->all(),
                ];
            })
            ->sortBy('collector')
            ->values()
            ->all();
    }
}
