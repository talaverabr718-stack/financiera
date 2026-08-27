<article class="card overflow-hidden">
    <div class="border-b border-slate-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold">Agenda de cobro</h2>
                <p class="mt-1 text-[11px] text-slate-400">Visitas del día · {{ $date->translatedFormat('l d \d\e F') }}</p>
            </div>
            @if($routes->isNotEmpty())
                <form method="GET" action="{{ route('collections.index') }}" class="min-w-56">
                    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                    @foreach(request()->only('client', 'collector') as $key => $value)
                        @if($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <select name="agenda_route" onchange="this.form.submit()" aria-label="Ruta del día" class="control mt-0 min-w-56 font-semibold text-slate-800">
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" @selected($selectedRoute?->id === $route->id)>{{ $route->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>
    @if($selectedRoute)
        @php
            $dayStops = $selectedRoute->stops;
            $dayPending = $dayStops->where('status', 'pending')->count();
            $dayDone = $dayStops->where('status', 'visited')->count();
            $stopLabels = ['pending' => 'Pendiente', 'visited' => 'Visitado', 'not_found' => 'No encontrado', 'rescheduled' => 'Reprogramado'];
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-3">
            <div>
                <p class="text-xs font-semibold text-slate-700">{{ $selectedRoute->collector->user->name }} · {{ $selectedRoute->code }}</p>
                <p class="mt-0.5 text-[10px] text-slate-400">Salida {{ $selectedRoute->starts_at ? substr($selectedRoute->starts_at, 0, 5) : 'sin hora' }} · {{ $dayDone }} gestionadas · {{ $dayPending }} pendientes</p>
            </div>
            <span class="rounded-full bg-[#eeebff] px-2.5 py-1 text-[10px] font-semibold text-indigo-600">{{ $dayStops->count() }} visitas del día</span>
        </div>
        <div class="table-wrap">
            <table class="w-full text-left">
                <thead class="text-[10px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="table-cell">#</th>
                        <th class="table-cell">Cliente</th>
                        <th class="table-cell">Contacto</th>
                        <th class="table-cell">Ubicación</th>
                        <th class="table-cell">Crédito</th>
                        <th class="table-cell">Cuotas pendientes</th>
                        <th class="table-cell text-right">Saldo</th>
                        <th class="table-cell">Estado</th>
                        <th class="table-cell text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($dayStops as $stop)
                    @php
                        $client = $stop->client;
                        $loans = $client->loans->whereIn('status', ['active', 'delinquent']);
                        $loan = $loans->first();
                        $pendingCuotas = $loans->flatMap->installments
                            ->filter(fn ($item) => ! $item->isExcludedFromCollection() && bccomp($item->outstandingAmount(), '0.00', 2) === 1)
                            ->sortBy(fn ($item) => [$item->due_date->toDateString(), $item->number])
                            ->values();
                    @endphp
                    <tr>
                        <td class="table-cell font-semibold text-slate-500">{{ $stop->position }}</td>
                        <td class="table-cell">
                            <p class="font-semibold text-slate-800">{{ $client->full_name }}</p>
                            <p class="text-[10px] text-slate-400">{{ $client->code }}{{ $client->identity_number ? ' · '.$client->identity_number : '' }}</p>
                        </td>
                        <td class="table-cell">
                            <p>{{ $client->phone ?: '—' }}</p>
                            <p class="text-[10px] text-slate-400">{{ $client->email ?: 'Sin correo' }}</p>
                        </td>
                        <td class="table-cell whitespace-normal min-w-40">
                            <p>{{ collect([$client->neighborhood, $client->municipality])->filter()->join(', ') ?: '—' }}</p>
                            <p class="text-[10px] text-slate-400">{{ $client->address ?: 'Sin dirección' }}</p>
                        </td>
                        <td class="table-cell">
                            @forelse($loans as $clientLoan)
                                <p class="font-semibold text-indigo-600">{{ $clientLoan->number }}</p>
                                <p class="text-[10px] text-slate-400">C$ {{ number_format((float) $clientLoan->principal, 2) }}</p>
                            @empty
                                <p class="text-amber-600">Sin crédito</p>
                            @endforelse
                        </td>
                        <td class="table-cell whitespace-normal min-w-52">
                            @if($pendingCuotas->isEmpty())
                                <p class="text-slate-400">Ninguna</p>
                            @else
                                <div class="space-y-1">
                                    @foreach($pendingCuotas->take(4) as $cuota)
                                        <p class="{{ $cuota->isOverdueOn($date) ? 'text-rose-600' : 'text-slate-700' }}">
                                            <span class="font-semibold">Cuota {{ $cuota->number }}</span>
                                            <span class="text-[10px]"> · {{ $cuota->due_date->format('d/m/Y') }} · C$ {{ number_format((float) $cuota->outstandingAmount(), 2) }}</span>
                                        </p>
                                    @endforeach
                                    @if($pendingCuotas->count() > 4)
                                        <p class="text-[10px] text-slate-400">+{{ $pendingCuotas->count() - 4 }} más</p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="table-cell text-right font-semibold tabular-nums">{{ $loan ? 'C$ '.number_format((float) $loan->outstanding_balance, 2) : '—' }}</td>
                        <td class="table-cell">
                            @php
                                $stopTone = match ($stop->status) {
                                    'pending' => 'text-amber-600',
                                    'visited' => 'text-emerald-600',
                                    'not_found' => 'text-rose-600',
                                    default => 'text-slate-600',
                                };
                            @endphp
                            <p class="text-[10px] font-semibold {{ $stopTone }}">{{ $stopLabels[$stop->status] ?? $stop->status }}</p>
                            @if($stop->visitedAtLabel())
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ $stop->visitedAtLabel() }}</p>
                            @endif
                        </td>
                        <td class="table-cell text-right">
                            <button type="button" class="btn-primary" data-open-pay="pay-{{ $stop->id }}">Pagar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-10 text-center text-sm text-slate-400">Esta ruta no tiene visitas para el día.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="p-16 text-center text-sm text-slate-400">No hay rutas para esta fecha.</div>
    @endif
</article>
