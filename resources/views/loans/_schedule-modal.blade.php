@php
    $frequencyLabels = ['daily' => 'Diaria', 'weekly' => 'Semanal', 'biweekly' => 'Quincenal', 'monthly' => 'Mensual'];
    $frequency = $frequencyLabels[data_get($loan->approved_terms, 'frequency')] ?? data_get($loan->approved_terms, 'frequency');
    $methodLabels = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'deposit' => 'Depósito'];
    $paidHistory = $delinquency['paid_history'] ?? collect();
    $upcoming = $loan->installments
        ->filter(fn ($item) => ! $item->isExcludedFromCollection() && bccomp($item->outstandingAmount(), '0.00', 2) === 1)
        ->sortBy(fn ($item) => [$item->due_date->toDateString(), $item->number])
        ->values();
@endphp
<div id="loan-schedule" class="app-modal list-modal fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="loan-schedule-title">
    <div class="modal-backdrop absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative z-10 flex h-full min-h-0 items-center justify-center p-3 sm:p-5">
        <article class="list-modal-panel modal-panel flex min-h-0 w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="shrink-0 border-b border-indigo-100 bg-indigo-50 px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-indigo-400">Calendario del crédito</p>
                        <h2 id="loan-schedule-title" class="mt-0.5 text-base font-semibold text-indigo-800">Pagos y próximas cuotas</h2>
                        <p class="mt-0.5 text-xs text-indigo-500">{{ data_get($loan->approved_terms, 'term') }} cuotas · {{ $frequency ?: 'sin frecuencia' }} · {{ $loan->number }}</p>
                    </div>
                    <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-indigo-500 hover:bg-indigo-100" data-close-modal aria-label="Cerrar"><i data-lucide="x" class="icon"></i></button>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-auto p-5">
                <section>
                    <h3 class="text-sm font-semibold text-slate-800">Historial de pagos</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">Recibos aplicados al crédito, con fecha y monto.</p>
                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-100">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="table-cell">Fecha</th>
                                    <th class="table-cell">Recibo</th>
                                    <th class="table-cell">Forma</th>
                                    <th class="table-cell text-right">Monto</th>
                                    <th class="table-cell">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($paidHistory as $entry)
                                <tr>
                                    <td class="table-cell">{{ $entry['date'] instanceof \Carbon\CarbonInterface ? $entry['date']->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="table-cell font-semibold text-indigo-600">{{ $entry['title'] }}</td>
                                    <td class="table-cell">{{ $methodLabels[$entry['method']] ?? $entry['method'] }}</td>
                                    <td class="table-cell text-right font-semibold tabular-nums {{ ($entry['status'] ?? '') === 'reversed' ? 'text-slate-400 line-through' : 'text-emerald-600' }}">{{ $loan->currency }} {{ number_format((float) $entry['amount'], 2) }}</td>
                                    <td class="table-cell whitespace-normal min-w-48">
                                        @if(($entry['status'] ?? '') === 'reversed')
                                            <p class="text-[10px] font-semibold text-rose-600">Anulado</p>
                                        @endif
                                        @foreach($entry['allocations'] as $allocation)
                                            <p class="text-[10px] text-slate-500">{{ $allocation['installment'] ? 'Cuota '.$allocation['installment'].' · ' : '' }}{{ $allocation['component_label'] ?? $allocation['component'] }} · {{ number_format((float) $allocation['amount'], 2) }}</p>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-sm text-slate-400">Todavía no hay pagos registrados en este crédito.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-800">Próximos pagos</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">Fechas de las cuotas que aún tienen saldo pendiente.</p>
                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-100">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="table-cell">Cuota</th>
                                    <th class="table-cell">Fecha de pago</th>
                                    <th class="table-cell">Situación</th>
                                    <th class="table-cell text-right">Saldo de la cuota</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($upcoming as $cuota)
                                <tr>
                                    <td class="table-cell font-semibold">Cuota {{ $cuota->number }}</td>
                                    <td class="table-cell">{{ $cuota->due_date->format('d/m/Y') }}</td>
                                    <td class="table-cell">
                                        @if($cuota->isOverdueOn(today()))
                                            <span class="text-[10px] font-semibold text-rose-600">Vencida</span>
                                        @else
                                            <span class="text-[10px] font-semibold text-indigo-600">Por vencer</span>
                                        @endif
                                    </td>
                                    <td class="table-cell text-right font-semibold tabular-nums">{{ $loan->currency }} {{ number_format((float) $cuota->outstandingAmount(), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-8 text-center text-sm text-slate-400">No hay cuotas pendientes por cobrar.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </article>
    </div>
</div>
