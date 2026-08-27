<div id="late-collections" class="app-modal late-modal fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="late-title">
    <div class="modal-backdrop absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative z-10 flex h-full min-h-0 items-center justify-center p-3 sm:p-5">
        <article class="late-modal-panel modal-panel flex min-h-0 w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="shrink-0 border-b border-rose-100 bg-rose-50 px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-rose-400">Cartera vencida</p>
                        <h2 id="late-title" class="mt-0.5 text-base font-semibold text-rose-800">Cobros retrasados</h2>
                        <p class="mt-0.5 text-xs text-rose-500">{{ $lateInstallments->count() }} {{ $lateInstallments->count() === 1 ? 'cuota vencida' : 'cuotas vencidas' }} al {{ $date->format('d/m/Y') }}</p>
                    </div>
                    <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-rose-500 hover:bg-rose-100" data-close-modal aria-label="Cerrar"><i data-lucide="x" class="icon"></i></button>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="table-cell">Cliente</th>
                            <th class="table-cell">Crédito</th>
                            <th class="table-cell">Cuota</th>
                            <th class="table-cell">Vencimiento</th>
                            <th class="table-cell text-right">Días de atraso</th>
                            <th class="table-cell text-right">Saldo de la cuota</th>
                            <th class="table-cell text-right">Saldo del crédito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($lateInstallments as $cuota)
                        <tr>
                            <td class="table-cell">
                                <p class="font-semibold text-slate-800">{{ $cuota->loan->client->full_name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $cuota->loan->client->phone ?: $cuota->loan->client->code }}</p>
                            </td>
                            <td class="table-cell">
                                <a href="{{ route('loans.show', $cuota->loan) }}" class="font-semibold text-indigo-600">{{ $cuota->loan->number }}</a>
                                <p class="text-[10px] text-slate-400">{{ $cuota->loan->seller?->user?->name }}</p>
                            </td>
                            <td class="table-cell font-semibold">Cuota {{ $cuota->number }}</td>
                            <td class="table-cell">{{ $cuota->due_date->format('d/m/Y') }}</td>
                            <td class="table-cell text-right font-semibold text-rose-600">{{ $cuota->daysOverdueOn($date) }}</td>
                            <td class="table-cell text-right font-semibold tabular-nums text-rose-600">C$ {{ number_format((float) $cuota->outstandingAmount(), 2) }}</td>
                            <td class="table-cell text-right tabular-nums">C$ {{ number_format((float) $cuota->loan->outstanding_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-sm text-slate-400">No hay cuotas vencidas para esta fecha.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</div>
