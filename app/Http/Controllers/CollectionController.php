<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();
        $routes = CollectionRoute::with(['collector.user', 'stops.client.loans.installments'])
            ->whereDate('scheduled_date', $date)->orderBy('starts_at')->get();
        $collectedToday = CollectionRecord::query()
            ->where('outcome', 'collected')
            ->whereDate('recorded_at', $date)
            ->sum('amount');
        $paymentHistory = CollectionRecord::with(['client', 'loan', 'collector.user', 'recordedBy', 'stop.route'])
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
            ->when($request->filled('route'), fn ($q) => $q->whereHas('stop', fn ($q) => $q->where('collection_route_id', $request->integer('route'))))
            ->when($request->filled('collector'), fn ($q) => $q->where('collector_id', $request->integer('collector')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')))
            ->latest('recorded_at')->paginate(20)->withQueryString();
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
        $lateCollections = $lateInstallments->count();
        $selectedRoute = $routes->firstWhere('id', $request->integer('agenda_route')) ?? $routes->first();

        return view('collections.index', compact('date', 'routes', 'collectedToday', 'paymentHistory', 'upcomingVisits', 'upcomingStops', 'pendingStops', 'lateCollections', 'lateInstallments', 'selectedRoute'));
    }

    public function store(Request $request, CollectionRouteStop $stop)
    {
        $stop->load('route');
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

        DB::transaction(function () use ($data, $stop): void {
            CollectionRecord::create($data + [
                'idempotency_key' => (string) Str::uuid(),
                'collection_route_stop_id' => $stop->id,
                'client_id' => $stop->client_id,
                'loan_id' => $data['loan_id'] ?? null,
                'collector_id' => $stop->route->collector_id,
                'application_status' => $data['outcome'] === 'collected' ? 'pending' : 'not_applicable',
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
            ]);

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

        return back()->with('success', 'Gestión de cobranza registrada y ruta actualizada.');
    }
}
