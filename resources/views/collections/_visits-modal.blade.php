@php
    $tones = [
        'amber' => ['bar' => 'border-amber-100 bg-amber-50', 'eyebrow' => 'text-amber-400', 'title' => 'text-amber-800', 'sub' => 'text-amber-500', 'close' => 'text-amber-500 hover:bg-amber-100'],
        'indigo' => ['bar' => 'border-indigo-100 bg-indigo-50', 'eyebrow' => 'text-indigo-400', 'title' => 'text-indigo-800', 'sub' => 'text-indigo-500', 'close' => 'text-indigo-500 hover:bg-indigo-100'],
    ];
    $ui = $tones[$tone];
    $colspan = $showDate ? 8 : 7;
@endphp
<div id="{{ $modalId }}" class="app-modal list-modal fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}">
    <div class="modal-backdrop absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative z-10 flex h-full min-h-0 items-center justify-center p-3 sm:p-5">
        <article class="list-modal-panel modal-panel flex min-h-0 w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="shrink-0 border-b {{ $ui['bar'] }} px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] {{ $ui['eyebrow'] }}">{{ $eyebrow }}</p>
                        <h2 id="{{ $titleId }}" class="mt-0.5 text-base font-semibold {{ $ui['title'] }}">{{ $title }}</h2>
                        <p class="mt-0.5 text-xs {{ $ui['sub'] }}">{{ $subtitle }}</p>
                    </div>
                    <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white {{ $ui['close'] }}" data-close-modal aria-label="Cerrar"><i data-lucide="x" class="icon"></i></button>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                        <tr>
                            @if($showDate)<th class="table-cell">Fecha</th>@endif
                            <th class="table-cell">Cliente</th>
                            <th class="table-cell">Ubicación</th>
                            <th class="table-cell">Crédito</th>
                            <th class="table-cell">Próxima cuota</th>
                            <th class="table-cell text-right">Saldo</th>
                            <th class="table-cell">Ruta</th>
                            <th class="table-cell">Cobrador</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($stops as $stop)
                        @php
                            $client = $stop->client;
                            $loan = $client->loans->first(fn ($item) => $item->isCollectible());
                            $nextCuota = $client->loans
                                ->filter(fn ($item) => $item->isCollectible())
                                ->flatMap->installments
                                ->filter(fn ($item) => ! $item->isExcludedFromCollection() && bccomp($item->outstandingAmount(), '0.00', 2) === 1)
                                ->sortBy(fn ($item) => [$item->due_date->toDateString(), $item->number])
                                ->first();
                        @endphp
                        <tr>
                            @if($showDate)
                                <td class="table-cell">
                                    <p class="font-semibold">{{ $stop->route->scheduled_date->format('d/m/Y') }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $stop->route->starts_at ? substr($stop->route->starts_at, 0, 5) : 'Sin hora' }}</p>
                                </td>
                            @endif
                            <td class="table-cell">
                                <p class="font-semibold text-slate-800">{{ $client->full_name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $client->phone ?: $client->code }}</p>
                            </td>
                            <td class="table-cell whitespace-normal min-w-40">
                                <p>{{ collect([$client->neighborhood, $client->municipality])->filter()->join(', ') ?: '—' }}</p>
                                <p class="text-[10px] text-slate-400">{{ $client->address ?: 'Sin dirección' }}</p>
                            </td>
                            <td class="table-cell">
                                @if($loan)
                                    <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-indigo-600">{{ $loan->number }}</a>
                                @else
                                    <span class="text-amber-600">Sin crédito</span>
                                @endif
                            </td>
                            <td class="table-cell">
                                @if($nextCuota)
                                    <p class="font-semibold {{ $nextCuota->isOverdueOn($date) ? 'text-rose-600' : 'text-slate-700' }}">Cuota {{ $nextCuota->number }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $nextCuota->due_date->format('d/m/Y') }} · C$ {{ number_format((float) $nextCuota->outstandingAmount(), 2) }}</p>
                                @else
                                    <p class="text-slate-400">Ninguna</p>
                                @endif
                            </td>
                            <td class="table-cell text-right font-semibold tabular-nums">{{ $loan ? 'C$ '.number_format((float) $loan->outstanding_balance, 2) : '—' }}</td>
                            <td class="table-cell">
                                <p class="font-semibold">{{ $stop->route->name }}</p>
                                @unless($showDate)
                                    <p class="text-[10px] text-slate-400">Salida {{ $stop->route->starts_at ? substr($stop->route->starts_at, 0, 5) : 'sin hora' }}</p>
                                @endunless
                            </td>
                            <td class="table-cell">{{ $stop->route->collector->user->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $colspan }}" class="p-10 text-center text-sm text-slate-400">{{ $empty }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</div>
