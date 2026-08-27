<section class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <div>
            <h2 class="text-sm font-semibold">Cuotas</h2>
            <p class="mt-0.5 text-[11px] text-slate-400">Pagadas a tiempo, pendientes y en mora. El historial de cada cuota se recorre con scroll.</p>
        </div>
        <div class="flex gap-3 text-[10px] text-slate-500">
            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>A tiempo</span>
            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span>En mora</span>
            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Tarde</span>
        </div>
    </div>
    <div class="table-wrap max-h-[min(28rem,62vh)] overflow-auto">
        <table class="w-full text-left">
            <thead class="text-[10px] uppercase tracking-wide text-slate-400">
                <tr>
                    @if(!empty($showLoan))<th class="table-cell">Crédito</th>@endif
                    <th class="table-cell">#</th>
                    <th class="table-cell">Vence</th>
                    <th class="table-cell text-right">Cuota</th>
                    <th class="table-cell text-right">Pagado</th>
                    <th class="table-cell text-right">Saldo</th>
                    <th class="table-cell">Situación</th>
                    <th class="table-cell text-right">Mora</th>
                    <th class="table-cell">Historial</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($summary['ledger'] as $row)
                @php
                    $tone = [
                        'on_time' => 'bg-emerald-50 text-emerald-700',
                        'late' => 'bg-amber-50 text-amber-800',
                        'overdue' => 'bg-rose-50 text-rose-700',
                        'partial' => 'bg-amber-50 text-amber-800',
                        'pending' => 'bg-slate-100 text-slate-600',
                        'excluded' => 'bg-slate-100 text-slate-500',
                    ][$row['settlement']] ?? 'bg-slate-100 text-slate-600';
                    $currency = $row['currency'] ?? 'NIO';
                @endphp
                <tr class="{{ $row['settlement'] === 'overdue' ? 'bg-rose-50/50' : ($row['settlement'] === 'on_time' ? 'bg-emerald-50/30' : '') }}">
                    @if(!empty($showLoan))
                        <td class="table-cell font-mono text-[11px]"><a href="{{ route('loans.show', $row['loan_id']) }}" class="font-semibold text-indigo-600">{{ $row['loan_number'] }}</a></td>
                    @endif
                    <td class="table-cell font-semibold">{{ $row['number'] }}</td>
                    <td class="table-cell">{{ $row['due_date']->format('d/m/Y') }}</td>
                    <td class="table-cell text-right tabular-nums">{{ number_format((float) $row['amount_due'], 2) }}</td>
                    <td class="table-cell text-right tabular-nums">{{ number_format((float) $row['amount_paid'], 2) }}</td>
                    <td class="table-cell text-right tabular-nums">{{ number_format((float) $row['outstanding_amount'], 2) }}</td>
                    <td class="table-cell"><span class="badge {{ $tone }}">{{ $row['settlement_label'] }}</span></td>
                    <td class="table-cell text-right {{ $row['days_overdue'] ? 'text-rose-700' : 'text-slate-400' }}">
                        @if($row['days_overdue'])
                            <span class="font-semibold tabular-nums">{{ number_format((float) $row['mora_amount'], 2) }}</span>
                            <span class="mt-0.5 block text-[10px] font-medium">{{ $row['mora_label'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="table-cell whitespace-normal min-w-52">
                        @if($row['history']->isEmpty())
                            <span class="text-[11px] text-slate-400">Sin pagos</span>
                        @else
                            <div class="max-h-20 space-y-1 overflow-y-auto pr-1 text-[11px] leading-4 text-slate-600">
                                @foreach($row['history'] as $entry)
                                    <p>
                                        {{ $entry['date'] instanceof \Carbon\CarbonInterface ? $entry['date']->format('d/m/Y') : '—' }}
                                        · {{ $entry['title'] }}
                                        · {{ $currency }} {{ number_format((float) $entry['amount'], 2) }}
                                        · <span class="{{ $entry['on_time'] === true ? 'text-emerald-700' : ($entry['on_time'] === false ? 'text-amber-700' : 'text-slate-400') }}">{{ $entry['timing_label'] }}</span>
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ !empty($showLoan) ? 9 : 8 }}"><x-empty-state title="Sin plan de cuotas" description="No se ha generado una tabla de amortización para este crédito." icon="calendar-days" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
