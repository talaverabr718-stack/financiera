<section class="card {{ $summary['in_arrears'] ? 'border-rose-200' : '' }}">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge :status="$summary['in_arrears'] ? 'delinquent' : 'current'" />
            @if($summary['in_arrears'])
                <p class="text-sm text-slate-700">
                    <span class="font-semibold">{{ $summary['code'] ?: 'Sin código' }}</span>
                    · {{ $summary['current_days'] }} {{ $summary['current_days'] === 1 ? 'día' : 'días' }}
                    · {{ $summary['overdue_count'] }} {{ $summary['overdue_count'] === 1 ? 'cuota' : 'cuotas' }}
                    · {{ $currency }} {{ number_format((float) $summary['overdue_balance'], 2) }}
                </p>
            @else
                <p class="text-sm text-slate-500">Sin cuotas vencidas pendientes.</p>
            @endif
        </div>
    </div>
    @isset($loan)
        @php
            $defaultRate = old('daily_rate', $loan->delinquency_daily_rate ?? $loan->application?->product?->delinquency_rate);
        @endphp
        <form method="POST" action="{{ route('loans.delinquency.recalculate', $loan) }}" class="border-t border-slate-100 px-4 py-3">
            @csrf
            <p class="text-xs font-semibold text-slate-700">Recalcular mora</p>
            <p class="mt-1 text-[11px] text-slate-400">Monto = saldo de la cuota × (% diario / 100) × días de retraso. El porcentaje lo defines aquí; no está fijo en el sistema.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <label class="min-w-44 text-xs font-medium text-slate-600">
                    % mora por día
                    <input type="number" name="daily_rate" value="{{ $defaultRate }}" min="0" max="100" step="0.000001" required class="control" placeholder="Ej. 1">
                </label>
                <button class="btn-primary text-xs">Recalcular</button>
            </div>
            @error('daily_rate')<p class="mt-2 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </form>
    @endisset
</section>
