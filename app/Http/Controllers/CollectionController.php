<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();
        $routes = CollectionRoute::with(['collector.user', 'stops.client.loans', 'stops.records'])
            ->whereDate('scheduled_date', $date)->orderBy('starts_at')->get();
        $records = CollectionRecord::with(['client', 'loan', 'collector.user', 'recordedBy', 'stop.route'])
            ->whereDate('recorded_at', $date)->latest('recorded_at')->get();
        $paymentHistory = CollectionRecord::with(['client', 'loan', 'collector.user', 'recordedBy', 'stop.route'])
            ->where('outcome', 'collected')
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->integer('client')))
            ->when($request->filled('route'), fn ($q) => $q->whereHas('stop', fn ($q) => $q->where('collection_route_id', $request->integer('route'))))
            ->when($request->filled('collector'), fn ($q) => $q->where('collector_id', $request->integer('collector')))
            ->latest('recorded_at')->paginate(20)->withQueryString();

        return view('collections.index', compact('date', 'routes', 'records', 'paymentHistory'));
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
                'recorded_by' => auth()->id() ?? User::query()->value('id'),
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
