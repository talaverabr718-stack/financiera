@php
    $client = $stop->client;
    $loans = $client->loans->whereIn('status', ['active', 'delinquent'])->values();
    $pendingCuotas = $loans->flatMap->installments
        ->filter(fn ($item) => ! $item->isExcludedFromCollection() && bccomp($item->outstandingAmount(), '0.00', 2) === 1)
        ->sortBy(fn ($item) => [$item->due_date->toDateString(), $item->number])
        ->values();
    $hasLoan = $loans->isNotEmpty();
    $defaultLoan = $loans->first();
@endphp
<div id="pay-{{ $stop->id }}" class="app-modal pay-modal fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="pay-title-{{ $stop->id }}">
    <div class="modal-backdrop absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative z-10 flex h-full min-h-0 items-center justify-center p-3 sm:p-5">
        <article class="pay-modal-panel modal-panel flex min-h-0 w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="shrink-0 bg-slate-900 px-5 py-3.5 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-white/60">Registrar cobro</p>
                        <h2 id="pay-title-{{ $stop->id }}" class="mt-0.5 truncate text-base font-semibold">{{ $client->full_name }}</h2>
                        <p class="mt-0.5 truncate text-xs text-white/70">{{ $client->phone ?: 'Sin teléfono' }} · {{ $client->neighborhood ?: $client->address }}</p>
                    </div>
                    <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/10 hover:bg-white/20" data-close-modal aria-label="Cerrar"><i data-lucide="x" class="icon"></i></button>
                </div>
                @if($defaultLoan)
                    <p class="mt-2.5 text-xl font-semibold tabular-nums">C$ {{ number_format((float) $defaultLoan->outstanding_balance, 2) }}</p>
                    <p class="mt-0.5 text-[11px] text-white/60">Saldo del crédito {{ $defaultLoan->number }}</p>
                @endif
            </header>
            <form method="POST" action="{{ route('collections.store', $stop) }}" class="pay-form flex min-h-0 flex-1 flex-col" data-has-loan="{{ $hasLoan ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="pay_stop" value="{{ $stop->id }}">
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5">
                    @if($pendingCuotas->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($pendingCuotas->take(6) as $cuota)
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $cuota->isOverdueOn($date) ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                                    Cuota {{ $cuota->number }} · {{ $cuota->due_date->format('d/m') }} · C$ {{ number_format((float) $cuota->outstandingAmount(), 2) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    @if($hasLoan)
                        <input type="hidden" name="loan_id" value="{{ $defaultLoan->id }}">
                        @if($loans->count() > 1)
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($loans as $clientLoan)
                                    <button type="button" class="loan-chip rounded-xl border px-3 py-2.5 text-left {{ $clientLoan->id === $defaultLoan->id ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 bg-white' }}" data-loan="{{ $clientLoan->id }}">
                                        <span class="block text-sm font-semibold text-slate-800">{{ $clientLoan->number }}</span>
                                        <span class="mt-1 block text-[11px] text-slate-500">C$ {{ number_format((float) $clientLoan->outstanding_balance, 2) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-800">Este cliente no tiene un crédito activo. Puedes registrar la visita, pero no un abono.</p>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="outcome-chip rounded-xl px-3 py-2.5 text-sm font-semibold {{ $hasLoan ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400' }}" data-outcome="collected" @disabled(! $hasLoan)>Cobrar</button>
                        <button type="button" class="outcome-chip rounded-xl bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-600" data-outcome="promise">Promesa</button>
                        <button type="button" class="outcome-chip rounded-xl bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-600" data-outcome="no_payment">Sin pago</button>
                        <button type="button" class="outcome-chip rounded-xl bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-600" data-outcome="not_found">No encontrado</button>
                    </div>
                    <input type="hidden" name="outcome" class="outcome" value="{{ $hasLoan ? 'collected' : 'no_payment' }}">
                    <div class="amount-field relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">C$</span>
                        <input type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" placeholder="0.00" aria-label="Cantidad cobrada" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 text-xl font-semibold tabular-nums text-slate-900 outline-none transition placeholder:text-slate-300 focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-50">
                    </div>
                    <div class="method-field grid grid-cols-3 gap-2">
                        <button type="button" class="method-chip rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-semibold text-white" data-method="cash">Efectivo</button>
                        <button type="button" class="method-chip rounded-xl bg-slate-100 px-3 py-2.5 text-xs font-semibold text-slate-600" data-method="transfer">Transferencia</button>
                        <button type="button" class="method-chip rounded-xl bg-slate-100 px-3 py-2.5 text-xs font-semibold text-slate-600" data-method="deposit">Depósito</button>
                    </div>
                    <input type="hidden" name="payment_method" value="cash">
                    <div class="promise-field hidden">
                        <input type="date" name="promise_date" min="{{ today()->addDay()->format('Y-m-d') }}" aria-label="Fecha prometida" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-800 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-50">
                    </div>
                    <textarea name="notes" rows="2" class="pay-notes w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm outline-none placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-50" placeholder="Nota u observación (opcional)"></textarea>
                </div>
                <div class="shrink-0 border-t border-slate-100 p-4">
                    <button class="btn-primary h-11 w-full text-sm">Confirmar</button>
                </div>
            </form>
        </article>
    </div>
</div>
