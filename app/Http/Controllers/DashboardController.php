<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\CreditApplication;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (! Schema::hasTable('loans')) {
            return Inertia::render('Dashboard/Index', $this->emptyPayload());
        }

        $now = now()->timezone(config('app.timezone'));
        $loans = Loan::query();
        $stats = [
            'activePortfolio' => (clone $loans)->whereIn('status', Loan::COLLECTIBLE_STATUSES)->selectRaw('COALESCE(SUM(principal_balance + interest_balance + fee_balance),0) total')->value('total'),
            'placed' => (clone $loans)->whereBetween('disbursed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('principal'),
            'placedLastMonth' => (clone $loans)->whereBetween('disbursed_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('principal'),
            'collectedToday' => CollectionRecord::where('outcome', 'collected')->whereDate('recorded_at', today())->sum('amount'),
            'collectedYesterday' => CollectionRecord::where('outcome', 'collected')->whereDate('recorded_at', today()->subDay())->sum('amount'),
            'activeLoans' => (clone $loans)->whereIn('status', Loan::COLLECTIBLE_STATUSES)->count(),
            'delinquentLoans' => (clone $loans)->where('status', 'delinquent')->count(),
            'pendingApplications' => CreditApplication::whereIn('status', ['submitted', 'review', 'approved'])->count(),
            'clients' => Client::where('status', 'active')->count(),
            'routesToday' => CollectionRoute::whereDate('scheduled_date', today())->count(),
        ];
        $stats['delinquencyRate'] = $stats['activeLoans'] ? round($stats['delinquentLoans'] / $stats['activeLoans'] * 100, 1) : 0;

        $dueToday = LoanInstallment::query()
            ->whereHas('loan', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))
            ->whereDate('due_date', today())
            ->whereNotIn('status', LoanInstallment::EXCLUDED_STATUSES)
            ->get()
            ->filter(fn (LoanInstallment $installment) => bccomp($installment->outstandingAmount(), '0.00', 2) === 1)
            ->values();
        $expectedToday = $dueToday->reduce(fn (string $total, LoanInstallment $installment) => bcadd($total, $installment->outstandingAmount(), 2), '0.00');
        $remainingToday = bccomp($expectedToday, (string) $stats['collectedToday'], 2) === 1
            ? bcsub($expectedToday, (string) $stats['collectedToday'], 2)
            : '0.00';
        $tillPercent = (float) $expectedToday > 0
            ? min(100, round(((float) $stats['collectedToday'] / (float) $expectedToday) * 100, 1))
            : ((float) $stats['collectedToday'] > 0 ? 100 : 0);

        $overdueInstallments = LoanInstallment::with('loan.client')
            ->whereHas('loan', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', LoanInstallment::EXCLUDED_STATUSES)
            ->orderBy('due_date')
            ->limit(250)
            ->get()
            ->filter(fn (LoanInstallment $installment) => $installment->isOverdueOn(today()))
            ->values();

        $aging = collect([
            '1-7' => ['key' => '1-7', 'label' => '1 a 7 días', 'hint' => 'Temprana', 'count' => 0, 'amount' => '0.00'],
            '8-30' => ['key' => '8-30', 'label' => '8 a 30 días', 'hint' => 'Intermedia', 'count' => 0, 'amount' => '0.00'],
            '31+' => ['key' => '31+', 'label' => 'Más de 30 días', 'hint' => 'Crítica', 'count' => 0, 'amount' => '0.00'],
        ]);
        foreach ($overdueInstallments as $installment) {
            $days = $installment->daysOverdueOn(today());
            $key = $days <= 7 ? '1-7' : ($days <= 30 ? '8-30' : '31+');
            $bucket = $aging->get($key);
            $bucket['count']++;
            $bucket['amount'] = bcadd($bucket['amount'], $installment->outstandingAmount(), 2);
            $aging->put($key, $bucket);
        }
        $aging = $aging->values();
        $overdueAmount = $overdueInstallments->reduce(fn (string $total, LoanInstallment $installment) => bcadd($total, $installment->outstandingAmount(), 2), '0.00');

        $todayStops = CollectionRouteStop::query()
            ->with(['client', 'route.collector.user', 'records'])
            ->whereHas('route', fn ($query) => $query->whereDate('scheduled_date', today()))
            ->get()
            ->sortBy(fn (CollectionRouteStop $stop) => [$stop->route?->starts_at ?? '', $stop->position])
            ->values()
            ->map(function (CollectionRouteStop $stop) {
                $note = $stop->records->first();

                return [
                    'id' => $stop->id,
                    'position' => $stop->position,
                    'status' => $stop->status,
                    'status_label' => $this->stopLabel($stop->status),
                    'visited_at' => $stop->visitedAtLabel(),
                    'note' => $note?->notes,
                    'route' => $stop->route?->name,
                    'collector' => $stop->route?->collector?->user?->name,
                    'client' => [
                        'id' => $stop->client?->id,
                        'full_name' => $stop->client?->full_name,
                        'phone' => $stop->client?->phone,
                        'neighborhood' => $stop->client?->neighborhood,
                        'municipality' => $stop->client?->municipality,
                        'url' => $stop->client ? route('clients.show', $stop->client) : null,
                    ],
                    'lat' => $stop->client?->latitude !== null ? (float) $stop->client->latitude : null,
                    'lng' => $stop->client?->longitude !== null ? (float) $stop->client->longitude : null,
                ];
            });
        $pendingStops = $todayStops->where('status', 'pending')->count();
        $visitedStops = $todayStops->where('status', 'visited')->count();

        $period = in_array((int) request('period'), [6, 12], true) ? (int) request('period') : 6;
        $portfolioTrend = collect(range($period - 1, 0))->map(function (int $monthsAgo) {
            $month = now()->subMonths($monthsAgo);

            return [
                'label' => $month->translatedFormat('M Y'),
                'value' => (float) Loan::whereBetween('disbursed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('principal'),
            ];
        });
        $collectionTrend = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = today()->subDays($daysAgo);

            return [
                'label' => $date->translatedFormat('D d'),
                'value' => (float) CollectionRecord::where('outcome', 'collected')->whereDate('recorded_at', $date)->sum('amount'),
            ];
        });

        $decisionQueue = CreditApplication::with(['client', 'product'])
            ->whereIn('status', ['submitted', 'review', 'approved'])
            ->orderBy('created_at')
            ->take(6)
            ->get()
            ->map(fn (CreditApplication $application) => [
                'id' => $application->id,
                'number' => $application->number,
                'status' => $application->status,
                'status_label' => match ($application->status) {
                    'submitted' => 'Enviada',
                    'review' => 'En revisión',
                    'approved' => 'Por desembolsar',
                    default => $application->status,
                },
                'requested_amount' => $application->requested_amount,
                'currency' => $application->currency,
                'days_waiting' => (int) $application->created_at?->startOfDay()->diffInDays(now()->startOfDay()),
                'client' => $application->client?->full_name,
                'product' => $application->product?->name,
                'url' => route('applications.show', $application),
            ]);

        $overdueWatch = $overdueInstallments
            ->sortByDesc(fn (LoanInstallment $installment) => (float) $installment->outstandingAmount())
            ->take(6)
            ->values()
            ->map(function (LoanInstallment $installment) {
                $days = $installment->daysOverdueOn(today());

                return [
                    'id' => $installment->id,
                    'client' => $installment->loan->client?->full_name,
                    'phone' => $installment->loan->client?->phone,
                    'place' => $installment->loan->client?->neighborhood ?: $installment->loan->client?->municipality,
                    'loan_number' => $installment->loan->number,
                    'loan_url' => route('loans.show', $installment->loan),
                    'client_url' => $installment->loan->client ? route('clients.show', $installment->loan->client) : null,
                    'installment' => $installment->number,
                    'due_date' => $installment->due_date?->toDateString(),
                    'days' => $days,
                    'outstanding' => $installment->outstandingAmount(),
                    'bucket' => $days <= 7 ? '1-7' : ($days <= 30 ? '8-30' : '31+'),
                ];
            });

        $upcomingInstallments = LoanInstallment::with('loan.client')
            ->whereHas('loan', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))
            ->whereDate('due_date', '>=', today())
            ->whereNotIn('status', array_merge(LoanInstallment::EXCLUDED_STATUSES, ['paid']))
            ->orderBy('due_date')
            ->take(6)
            ->get()
            ->map(fn (LoanInstallment $installment) => [
                'id' => $installment->id,
                'number' => $installment->number,
                'due_date' => $installment->due_date?->toDateString(),
                'outstanding' => $installment->outstandingAmount(),
                'loan' => ['number' => $installment->loan->number, 'url' => route('loans.show', $installment->loan)],
                'client' => $installment->loan->client?->only('id', 'full_name'),
            ]);

        $recentPayments = Payment::with('client')
            ->where('status', 'applied')
            ->latest('received_at')
            ->take(8)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'receipt_number' => $payment->receipt_number,
                'method' => $payment->payment_method,
                'received_at' => $payment->received_at?->timezone(config('app.timezone'))->format('H:i'),
                'received_date' => $payment->received_at?->timezone(config('app.timezone'))->toDateString(),
                'is_today' => $payment->received_at?->timezone(config('app.timezone'))->isToday(),
                'client' => $payment->client?->full_name,
                'url' => $payment->client ? route('clients.show', $payment->client) : route('collections.index'),
            ]);

        $fieldActivity = CollectionRecord::with(['client', 'collector.user'])
            ->whereDate('recorded_at', today())
            ->latest('recorded_at')
            ->take(10)
            ->get()
            ->map(function (CollectionRecord $record) {
                $place = $record->client?->neighborhood ?: 'Estelí';
                $collector = $record->collector?->user?->name ?: 'Operación';
                $client = $record->client?->full_name ?: 'un cliente';
                $line = match ($record->outcome) {
                    'collected' => "{$collector} cobró {$this->cordoba($record->amount)} a {$client} en {$place}",
                    'promise' => "{$collector} dejó promesa con {$client} en {$place}",
                    'no_payment' => "{$collector} no cobró a {$client} en {$place}",
                    'not_found' => "{$collector} no halló a {$client} en {$place}",
                    default => "{$collector} registró gestión con {$client}",
                };

                return [
                    'id' => $record->id,
                    'outcome' => $record->outcome,
                    'outcome_label' => match ($record->outcome) {
                        'collected' => 'Cobrado',
                        'promise' => 'Promesa',
                        'no_payment' => 'Sin pago',
                        'not_found' => 'No hallado',
                        default => $record->outcome,
                    },
                    'amount' => $record->amount,
                    'client' => $record->client?->full_name,
                    'place' => $place,
                    'collector' => $record->collector?->user?->name,
                    'time' => $record->recorded_at?->timezone(config('app.timezone'))->format('H:i'),
                    'at' => $record->recorded_at?->timezone(config('app.timezone'))->toIso8601String(),
                    'line' => $line,
                ];
            });

        $collectorActivity = $this->collectorBoard();
        $paymentMix = $this->paymentMix();
        $neighborhoods = $this->neighborhoods($todayStops, $overdueInstallments);
        $promisesToday = CollectionRecord::with(['client', 'collector.user'])
            ->where('outcome', 'promise')
            ->whereDate('promise_date', today())
            ->latest('recorded_at')
            ->take(8)
            ->get()
            ->map(fn (CollectionRecord $record) => [
                'id' => $record->id,
                'client' => $record->client?->full_name,
                'place' => $record->client?->neighborhood ?: 'Estelí',
                'phone' => $record->client?->phone,
                'collector' => $record->collector?->user?->name,
                'note' => $record->notes,
                'url' => $record->client ? route('clients.show', $record->client) : route('collections.index'),
            ]);

        $links = [
            'newApplication' => route('applications.create'),
            'newClient' => route('clients.create'),
            'applications' => route('applications.index'),
            'clients' => route('clients.index'),
            'loans' => route('loans.index'),
            'routes' => route('routes.index'),
            'collections' => route('collections.index'),
            'delinquency' => route('delinquency.index'),
        ];

        $stats['expectedToday'] = $expectedToday;
        $stats['pendingStops'] = $pendingStops;
        $stats['visitedStops'] = $visitedStops;
        $stats['overdueCount'] = $overdueInstallments->count();
        $stats['overdueAmount'] = $overdueAmount;

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'briefing' => $this->briefing($now, $stats, $expectedToday, $pendingStops, $links),
            'till' => [
                'collected' => $stats['collectedToday'],
                'expected' => $expectedToday,
                'remaining' => $remainingToday,
                'percent' => $tillPercent,
                'due_count' => $dueToday->count(),
                'pending_stops' => $pendingStops,
                'visited_stops' => $visitedStops,
                'total_stops' => $todayStops->count(),
                'yesterday' => $stats['collectedYesterday'],
                'delta' => (float) $stats['collectedToday'] - (float) $stats['collectedYesterday'],
                'surpassed' => bccomp((string) $stats['collectedToday'], $expectedToday, 2) === 1 && bccomp($expectedToday, '0.00', 2) === 1,
            ],
            'aging' => $aging,
            'decisionQueue' => $decisionQueue,
            'overdueWatch' => $overdueWatch,
            'todayStops' => $todayStops,
            'upcomingInstallments' => $upcomingInstallments,
            'recentPayments' => $recentPayments,
            'fieldActivity' => $fieldActivity,
            'collectorActivity' => $collectorActivity,
            'paymentMix' => $paymentMix,
            'neighborhoods' => $neighborhoods,
            'promisesToday' => $promisesToday,
            'closing' => [
                'opens_at' => '08:00',
                'closes_at' => '17:00',
                'label' => 'Cierre de caja 17:00',
            ],
            'portfolioTrend' => $portfolioTrend,
            'collectionTrend' => $collectionTrend,
            'period' => $period,
            'googleMapsKey' => config('services.google_maps.key'),
            'googleMapsMapId' => config('services.google_maps.map_id'),
            'links' => $links,
            'monthName' => now()->translatedFormat('F'),
        ]);
    }

    private function briefing($now, array $stats, string $expectedToday, int $pendingStops, array $links): array
    {
        $hour = (int) $now->format('G');
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $firstName = strtok((string) (auth()->user()?->name ?: 'equipo'), ' ') ?: 'equipo';
        $collected = $this->cordoba($stats['collectedToday']);
        $expected = $this->cordoba($expectedToday);

        if ((float) $expectedToday > 0) {
            $percent = (float) $expectedToday > 0 ? round(((float) $stats['collectedToday'] / (float) $expectedToday) * 100) : 0;
            $situation = "Van {$collected} de {$expected} esperados hoy ({$percent}%). Quedan {$pendingStops} visitas en ruta.";
        } elseif ((float) $stats['collectedToday'] > 0) {
            $situation = "Ya hay {$collected} cobrados y no hay cuotas con vencimiento hoy.";
        } else {
            $situation = "Aún no hay recaudo. {$pendingStops} visitas en campo y {$stats['pendingApplications']} solicitudes por decidir.";
        }

        $actions = collect();
        if ($pendingStops > 0) {
            $actions->push(['label' => "{$pendingStops} visitas pendientes", 'hint' => 'Seguir la ruta de hoy', 'url' => $links['collections'], 'tone' => 'gold']);
        }
        if ($stats['overdueCount'] > 0) {
            $actions->push(['label' => "{$stats['overdueCount']} cuotas en mora", 'hint' => 'Priorizar lo que más duele', 'url' => $links['delinquency'], 'tone' => 'rose']);
        }
        if ($stats['pendingApplications'] > 0) {
            $actions->push(['label' => "{$stats['pendingApplications']} solicitudes por decidir", 'hint' => 'Cola de originación', 'url' => $links['applications'], 'tone' => 'blue']);
        }
        if ($actions->count() < 3) {
            $actions->push(['label' => 'Nueva solicitud', 'hint' => 'Abrir evaluación crediticia', 'url' => $links['newApplication'], 'tone' => 'navy']);
        }
        if ($actions->count() < 3) {
            $actions->push(['label' => 'Registrar cliente', 'hint' => 'Nuevo expediente', 'url' => $links['newClient'], 'tone' => 'navy']);
        }
        if ($actions->count() < 3) {
            $actions->push(['label' => 'Planificar rutas', 'hint' => 'Organizar el territorio', 'url' => $links['routes'], 'tone' => 'navy']);
        }

        return [
            'greeting' => $greeting,
            'first_name' => $firstName,
            'date_label' => $now->isoFormat('dddd D [de] MMMM'),
            'time_label' => $now->format('H:i'),
            'situation' => $situation,
            'actions' => $actions->take(3)->values(),
        ];
    }

    private function collectorBoard(): \Illuminate\Support\Collection
    {
        $todayRoutes = CollectionRoute::with(['stops', 'collector.user', 'collector.zone'])
            ->whereDate('scheduled_date', today())
            ->get();
        $collectorTotals = CollectionRecord::where('outcome', 'collected')
            ->whereDate('recorded_at', today())
            ->selectRaw('collector_id, COUNT(*) operations, COALESCE(SUM(amount),0) amount')
            ->groupBy('collector_id')
            ->get()
            ->keyBy('collector_id');
        $ids = $todayRoutes->pluck('collector_id')->merge($collectorTotals->keys())->filter()->unique();
        if ($ids->isEmpty()) {
            return collect();
        }

        return SellerProfile::with(['user', 'zone'])->whereIn('id', $ids)->get()->map(function (SellerProfile $seller) use ($todayRoutes, $collectorTotals) {
            $stops = $todayRoutes->where('collector_id', $seller->id)->flatMap->stops;
            $pending = $stops->where('status', 'pending')->count();
            $visited = $stops->where('status', 'visited')->count();
            $totals = $collectorTotals->get($seller->id);
            $amount = (float) ($totals?->amount ?? 0);
            $status = $amount > 0 ? 'en_campo' : ($pending > 0 ? 'en_ruta' : ($stops->isNotEmpty() ? 'sin_gestion' : 'oficina'));

            return [
                'id' => $seller->id,
                'name' => $seller->user?->name,
                'zone' => $seller->zone?->name,
                'amount' => $amount,
                'operations' => (int) ($totals?->operations ?? 0),
                'pending_stops' => $pending,
                'visited_stops' => $visited,
                'status' => $status,
                'status_label' => match ($status) {
                    'en_campo' => 'En campo',
                    'en_ruta' => 'En ruta',
                    'sin_gestion' => 'Sin gestiones',
                    default => 'Oficina',
                },
            ];
        })->sortByDesc('amount')->values();
    }

    private function paymentMix(): \Illuminate\Support\Collection
    {
        $raw = CollectionRecord::query()
            ->where('outcome', 'collected')
            ->whereDate('recorded_at', today())
            ->selectRaw('payment_method, COUNT(*) operations, COALESCE(SUM(amount),0) amount')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        return collect(['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'deposit' => 'Depósito'])
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'amount' => (float) ($raw->get($key)?->amount ?? 0),
                'operations' => (int) ($raw->get($key)?->operations ?? 0),
            ])
            ->values();
    }

    private function neighborhoods(\Illuminate\Support\Collection $todayStops, \Illuminate\Support\Collection $overdueInstallments): \Illuminate\Support\Collection
    {
        $places = collect();
        foreach ($todayStops as $stop) {
            $name = $stop['client']['neighborhood'] ?: 'Estelí';
            $row = $places->get($name, ['name' => $name, 'stops' => 0, 'pending' => 0, 'visited' => 0, 'overdue_count' => 0, 'overdue_amount' => '0.00']);
            $row['stops']++;
            if ($stop['status'] === 'pending') {
                $row['pending']++;
            }
            if ($stop['status'] === 'visited') {
                $row['visited']++;
            }
            $places->put($name, $row);
        }
        foreach ($overdueInstallments as $installment) {
            $name = $installment->loan->client?->neighborhood ?: 'Estelí';
            $row = $places->get($name, ['name' => $name, 'stops' => 0, 'pending' => 0, 'visited' => 0, 'overdue_count' => 0, 'overdue_amount' => '0.00']);
            $row['overdue_count']++;
            $row['overdue_amount'] = bcadd($row['overdue_amount'], $installment->outstandingAmount(), 2);
            $places->put($name, $row);
        }
        $max = max($places->max(fn ($row) => $row['stops'] + $row['overdue_count']) ?: 1, 1);

        return $places->map(function (array $row) use ($max) {
            $row['weight'] = round((($row['stops'] + $row['overdue_count']) / $max) * 100);
            $row['tone'] = $row['overdue_count'] >= 2 ? 'rose' : ($row['pending'] > 0 ? 'gold' : 'emerald');

            return $row;
        })->sortByDesc('weight')->values();
    }

    private function stopLabel(?string $status): string
    {
        return match ($status) {
            'visited' => 'Visitada',
            'pending' => 'Pendiente',
            'not_found' => 'No hallado',
            'rescheduled' => 'Reprogramada',
            default => $status ?: 'Pendiente',
        };
    }

    private function cordoba(mixed $value): string
    {
        return 'C$ '.number_format((float) $value, 0, '.', ',');
    }

    private function emptyPayload(): array
    {
        return [
            'stats' => [
                'activePortfolio' => 0, 'placed' => 0, 'placedLastMonth' => 0, 'collectedToday' => 0, 'collectedYesterday' => 0,
                'activeLoans' => 0, 'delinquentLoans' => 0, 'pendingApplications' => 0, 'clients' => 0, 'routesToday' => 0,
                'delinquencyRate' => 0, 'expectedToday' => 0, 'pendingStops' => 0, 'visitedStops' => 0, 'overdueCount' => 0, 'overdueAmount' => 0,
            ],
            'briefing' => [
                'greeting' => 'Buenos días', 'first_name' => 'equipo', 'date_label' => now()->isoFormat('dddd D [de] MMMM'),
                'time_label' => now()->format('H:i'), 'situation' => 'La operación aún no tiene datos para mostrar.', 'actions' => [],
            ],
            'till' => ['collected' => 0, 'expected' => 0, 'remaining' => 0, 'percent' => 0, 'due_count' => 0, 'pending_stops' => 0, 'visited_stops' => 0, 'total_stops' => 0, 'yesterday' => 0, 'delta' => 0, 'surpassed' => false],
            'aging' => [],
            'decisionQueue' => [],
            'overdueWatch' => [],
            'todayStops' => [],
            'upcomingInstallments' => [],
            'recentPayments' => [],
            'fieldActivity' => [],
            'collectorActivity' => [],
            'paymentMix' => [],
            'neighborhoods' => [],
            'promisesToday' => [],
            'closing' => ['opens_at' => '08:00', 'closes_at' => '17:00', 'label' => 'Cierre de caja 17:00'],
            'portfolioTrend' => [],
            'collectionTrend' => [],
            'period' => 6,
            'googleMapsKey' => config('services.google_maps.key'),
            'googleMapsMapId' => config('services.google_maps.map_id'),
            'links' => [
                'newApplication' => route('applications.create'),
                'newClient' => route('clients.create'),
                'applications' => route('applications.index'),
                'clients' => route('clients.index'),
                'loans' => route('loans.index'),
                'routes' => route('routes.index'),
                'collections' => route('collections.index'),
                'delinquency' => route('delinquency.index'),
            ],
            'monthName' => now()->translatedFormat('F'),
        ];
    }
}
