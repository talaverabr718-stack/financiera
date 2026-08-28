<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\CollectionReceiptPresenter;
use App\Services\PaymentApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index(Request $request, CollectionReceiptPresenter $receipts)
    {
        $date = $request->date('date') ?? today();
        $routes = CollectionRoute::with([
            'collector.user',
            'stops.client.loans.installments',
            'stops.records' => fn ($query) => $query->where('outcome', 'collected')->whereNotNull('payment_id')->latest('recorded_at'),
            'stops.records.payment.client',
            'stops.records.payment.loan',
            'stops.records.payment.collector',
            'stops.records.payment.creator',
            'stops.records.payment.allocations.installment',
        ])
            ->whereDate('scheduled_date', $date)->orderBy('starts_at')->get();
        $collectedRecords = CollectionRecord::query()
            ->with(['client', 'collector.user'])
            ->where('outcome', 'collected')
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at')
            ->get();
        $collectedToday = $collectedRecords->reduce(
            fn (string $total, CollectionRecord $record) => bcadd($total, (string) $record->amount, 2),
            '0.00',
        );
        $collectedTodayBreakdown = $this->collectedTodayBreakdown($collectedRecords);
        $paymentHistory = CollectionRecord::with([
            'client',
            'loan',
            'payment.client',
            'payment.loan',
            'payment.collector',
            'payment.creator',
            'payment.allocations.installment',
            'collector.user',
            'recordedBy',
            'stop.route',
        ])
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
            ->when($request->filled('route'), fn ($q) => $q->whereHas('stop', fn ($q) => $q->where('collection_route_id', $request->integer('route'))))
            ->when($request->filled('collector'), fn ($q) => $q->where('collector_id', $request->integer('collector')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')))
            ->latest('recorded_at')->paginate(20)->withQueryString();
        $paymentHistory->getCollection()->each(function (CollectionRecord $record) use ($receipts): void {
            $record->setAttribute('ticket', $record->payment ? $receipts->fromPayment($record->payment) : null);
        });
        $pendingStops = CollectionRouteStop::query()
            ->with(['client.loans.installments', 'route.collector.user'])
            ->where('status', 'pending')
            ->whereHas('route', fn ($query) => $query->whereDate('scheduled_date', $date))
            ->get()
            ->sortBy(fn (CollectionRouteStop $stop) => [$stop->route->starts_at ?? '', $stop->position])
            ->values();
        $upcomingStops = CollectionRouteStop::query()
            ->with(['client.loans.installments', 'route.collector.user'])
            ->where('status', 'pending')
            ->whereHas('route', fn ($query) => $query->whereDate('scheduled_date', '>', $date))
            ->get()
            ->sortBy(fn (CollectionRouteStop $stop) => [$stop->route->scheduled_date->toDateString(), $stop->route->starts_at ?? '', $stop->position])
            ->values();
        $upcomingVisits = $upcomingStops->count();
        $lateInstallments = LoanInstallment::query()
            ->with(['loan.client', 'loan.seller.user'])
            ->whereHas('loan', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))
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
        $selectedRoute = $routes->firstWhere('id', $request->integer('agenda_route')) ?? $routes->first();
        $routes->each(function (CollectionRoute $route) use ($date, $receipts): void {
            $route->withCollectorDues($date);
            $route->stops->each(function (CollectionRouteStop $stop) use ($receipts): void {
                $payment = $stop->records
                    ->first(fn (CollectionRecord $record) => $record->outcome === 'collected' && $record->payment)
                    ?->payment;
                $stop->setAttribute('ticket', $payment ? $receipts->fromPayment($payment) : null);
                $stop->unsetRelation('records');
            });
        });

        return Inertia::render('Collections/Index', compact('date', 'routes', 'collectedToday', 'collectedTodayBreakdown', 'paymentHistory', 'upcomingVisits', 'upcomingStops', 'pendingStops', 'lateCollections', 'lateInstallments', 'selectedRoute') + ['storeTemplate' => route('collections.store', ['stop' => '__STOP__'])]);
    }

    public function store(Request $request, CollectionRouteStop $stop, PaymentApplicationService $payments, CollectionReceiptPresenter $receipts)
    {
        $stop->load('route.collector');
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
        ], [
            'loan_id.required_if' => 'Este cliente no tiene un préstamo seleccionado para registrar el abono.',
            'loan_id.exists' => 'El préstamo seleccionado no pertenece al cliente o ya no admite abonos.',
            'amount.required_if' => 'Ingresa el monto del abono.',
            'payment_method.required_if' => 'Selecciona la forma de pago.',
        ]);

        $ticket = null;

        DB::transaction(function () use ($data, $stop, $payments, $receipts, &$ticket): void {
            $stop = CollectionRouteStop::query()->with('route.collector')->lockForUpdate()->findOrFail($stop->id);

            if ($stop->status !== 'pending') {
                throw ValidationException::withMessages([
                    'outcome' => 'Esta visita ya tiene una gestión registrada.',
                ]);
            }

            $record = CollectionRecord::create($data + [
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

        $redirect = back()->with('success', $data['outcome'] === 'collected'
            ? 'Cobro aplicado a cartera y ruta actualizada.'
            : 'Gestión de cobranza registrada y ruta actualizada.');

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
