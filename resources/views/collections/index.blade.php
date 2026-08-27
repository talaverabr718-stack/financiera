@extends('clients.layout')
@section('title','Cobranza')
@section('content')
@php
    $stops=$routes->flatMap->stops;
    $outcomeLabels=['collected'=>'Cobro recibido','promise'=>'Promesa de pago','no_payment'=>'Sin pago','not_found'=>'No encontrado'];
    $outcomeColors=['collected'=>'text-emerald-600','promise'=>'text-indigo-600','no_payment'=>'text-amber-600','not_found'=>'text-slate-500'];
    $methodLabels=['cash'=>'Efectivo','transfer'=>'Transferencia','deposit'=>'Depósito'];
@endphp
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-[11px] font-semibold uppercase tracking-[.18em] text-indigo-500">Gestión diaria</p><h1 class="mt-2 text-[28px] font-semibold">Cobranza</h1><p class="mt-1 text-sm text-slate-500">Cobros vinculados al cliente, préstamo, ruta y cobrador.</p></div><div class="flex items-center gap-2"><form><input type="date" name="date" value="{{$date->format('Y-m-d')}}" onchange="this.form.submit()" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs"></form><a href="{{route('collections.index',['date'=>today()->format('Y-m-d')])}}" class="grid h-10 place-items-center rounded-xl bg-[#eeebff] px-4 text-xs font-semibold text-indigo-600">Hoy</a><a href="{{route('routes.index',['date'=>$date->format('Y-m-d')])}}" class="flex h-10 items-center gap-2 rounded-xl border border-indigo-200 px-4 text-xs font-semibold text-indigo-600"><i data-lucide="map" class="icon"></i>Ver rutas</a></div></div>
@if($errors->any())<div class="mb-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <article class="card p-4"><p class="text-[11px] text-slate-400">Cobrado hoy</p><p class="mt-2 text-xl font-semibold text-emerald-600">C$ {{number_format($collectedToday,2)}}</p></article>
    <button type="button" class="card w-full p-4 text-left transition hover:border-indigo-200 hover:bg-indigo-50/50" data-open-modal="upcoming-visits" aria-label="Ver próximas visitas">
        <p class="text-[11px] text-slate-400">Próximas visitas</p>
        <p class="mt-2 text-xl font-semibold text-indigo-600">{{$upcomingVisits}}</p>
        <p class="mt-1 text-[10px] font-semibold text-indigo-400">Ver próximas visitas</p>
    </button>
    <button type="button" class="card w-full p-4 text-left transition hover:border-amber-200 hover:bg-amber-50/50" data-open-modal="pending-visits" aria-label="Ver visitas pendientes">
        <p class="text-[11px] text-slate-400">Visitas pendientes</p>
        <p class="mt-2 text-xl font-semibold text-amber-600">{{$pendingStops->count()}}</p>
        <p class="mt-1 text-[10px] font-semibold text-amber-500">Ver visitas pendientes</p>
    </button>
    <button type="button" class="card w-full p-4 text-left transition hover:border-rose-200 hover:bg-rose-50/50" data-open-modal="late-collections" aria-label="Ver cobros retrasados">
        <p class="text-[11px] text-slate-400">Cobros retrasados</p>
        <p class="mt-2 text-xl font-semibold text-rose-600">{{$lateCollections}}</p>
        <p class="mt-1 text-[10px] font-semibold text-rose-400">Ver pagos atrasados</p>
    </button>
</section>
<section>@include('collections._agenda')</section>
<section class="card mt-5 overflow-hidden">
    <div class="border-b border-slate-100 p-5">
        <h2 class="text-sm font-semibold">Historial de pagos</h2>
        <p class="mt-1 text-[11px] text-slate-400">Todas las actividades registradas por el cobrador: cobros, promesas, visitas sin pago y no encontrados.</p>
        <form class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <input type="hidden" name="date" value="{{$date->format('Y-m-d')}}">
            @if($selectedRoute)<input type="hidden" name="agenda_route" value="{{$selectedRoute->id}}">@endif
            <select name="client" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" aria-label="Cliente">
                <option value="">Todos los clientes</option>
                @foreach($stops->pluck('client')->unique('id')->sortBy('full_name') as $client)
                    <option value="{{$client->id}}" @selected(request('client')==$client->id)>{{$client->full_name}}</option>
                @endforeach
            </select>
            <select name="route" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" aria-label="Ruta">
                <option value="">Todas las rutas</option>
                @foreach($routes as $route)
                    <option value="{{$route->id}}" @selected(request('route')==$route->id)>{{$route->name}}</option>
                @endforeach
            </select>
            <select name="collector" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" aria-label="Cobrador">
                <option value="">Todos los cobradores</option>
                @foreach($routes->pluck('collector')->unique('id') as $collector)
                    <option value="{{$collector->id}}" @selected(request('collector')==$collector->id)>{{$collector->user->name}}</option>
                @endforeach
            </select>
            <select name="outcome" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" aria-label="Actividad">
                <option value="">Todas las actividades</option>
                @foreach($outcomeLabels as $value => $label)
                    <option value="{{$value}}" @selected(request('outcome')==$value)>{{$label}}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-[#eeebff] px-4 py-2 text-xs font-semibold text-indigo-600">Filtrar historial</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-[10px] uppercase text-slate-400">
                <tr>
                    <th class="table-cell">Fecha</th>
                    <th class="table-cell">Cliente / préstamo</th>
                    <th class="table-cell">Actividad</th>
                    <th class="table-cell">Préstamo total</th>
                    <th class="table-cell">Saldo pendiente</th>
                    <th class="table-cell">Pago</th>
                    <th class="table-cell">Detalle</th>
                    <th class="table-cell">Ruta</th>
                    <th class="table-cell">Cobrador / registró</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($paymentHistory as $payment)
                <tr>
                    <td class="table-cell text-xs">{{$payment->recorded_at->format('d/m/Y H:i')}}</td>
                    <td class="table-cell">
                        <p class="text-xs font-semibold">{{$payment->client->full_name}}</p>
                        <p class="text-[10px] text-indigo-600">{{$payment->loan?->number?:'Sin préstamo'}}</p>
                    </td>
                    <td class="table-cell">
                        <span class="text-[10px] font-semibold {{$outcomeColors[$payment->outcome] ?? 'text-slate-600'}}">{{$outcomeLabels[$payment->outcome] ?? $payment->outcome}}</span>
                    </td>
                    <td class="table-cell text-xs">{{$payment->loan ? 'C$ '.number_format((float)$payment->loan->principal,2) : '—'}}</td>
                    <td class="table-cell text-xs font-semibold {{$payment->loan ? 'text-amber-600' : 'text-slate-400'}}">{{$payment->loan ? 'C$ '.number_format((float)$payment->loan->outstanding_balance,2) : '—'}}</td>
                    <td class="table-cell text-xs font-semibold {{$payment->outcome === 'collected' && $payment->amount ? 'text-emerald-600' : 'text-slate-400'}}">
                        {{$payment->outcome === 'collected' && $payment->amount ? 'C$ '.number_format((float)$payment->amount,2) : '—'}}
                    </td>
                    <td class="table-cell whitespace-normal min-w-40">
                        @if($payment->outcome === 'collected' && $payment->payment_method)
                            <p class="text-xs">{{$methodLabels[$payment->payment_method] ?? $payment->payment_method}}</p>
                        @elseif($payment->outcome === 'promise' && $payment->promise_date)
                            <p class="text-xs">Promete {{$payment->promise_date->format('d/m/Y')}}</p>
                        @endif
                        @if($payment->notes)
                            <p class="text-[10px] text-slate-400">{{$payment->notes}}</p>
                        @elseif(! ($payment->outcome === 'collected' && $payment->payment_method) && ! ($payment->outcome === 'promise' && $payment->promise_date))
                            <p class="text-xs text-slate-400">—</p>
                        @endif
                    </td>
                    <td class="table-cell text-xs">{{$payment->stop->route->name}}</td>
                    <td class="table-cell">
                        <p class="text-xs">{{$payment->collector->user->name}}</p>
                        <p class="text-[10px] text-slate-400">{{$payment->recordedBy?->name}}</p>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-10 text-center text-xs text-slate-400">No hay actividades con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{$paymentHistory->links()}}</div>
</section>
@if($selectedRoute)
    @foreach($selectedRoute->stops as $stop)
        @include('collections._pay-modal', ['stop' => $stop, 'date' => $date])
    @endforeach
@endif
@include('collections._late-modal')
@include('collections._visits-modal', [
    'modalId' => 'pending-visits',
    'titleId' => 'pending-title',
    'eyebrow' => 'Agenda de hoy',
    'title' => 'Visitas pendientes',
    'subtitle' => $pendingStops->count().' '.($pendingStops->count() === 1 ? 'visita pendiente' : 'visitas pendientes').' · '.$date->format('d/m/Y'),
    'empty' => 'No hay visitas pendientes para esta fecha.',
    'stops' => $pendingStops,
    'showDate' => false,
    'tone' => 'amber',
])
@include('collections._visits-modal', [
    'modalId' => 'upcoming-visits',
    'titleId' => 'upcoming-title',
    'eyebrow' => 'Rutas siguientes',
    'title' => 'Próximas visitas',
    'subtitle' => $upcomingStops->count().' '.($upcomingStops->count() === 1 ? 'visita programada' : 'visitas programadas').' después del '.$date->format('d/m/Y'),
    'empty' => 'No hay visitas programadas después de esta fecha.',
    'stops' => $upcomingStops,
    'showDate' => true,
    'tone' => 'indigo',
])
<script>
const payModals = [...document.querySelectorAll('.app-modal')];
const openPay = (id) => {
    const modal = document.getElementById(id);
    if (!modal) return;
    document.body.appendChild(modal);
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
const closePay = (modal) => {
    modal.classList.add('hidden');
    if (!payModals.some((item) => !item.classList.contains('hidden'))) document.body.style.overflow = '';
};
document.querySelectorAll('[data-open-pay]').forEach((button) => button.addEventListener('click', () => openPay(button.dataset.openPay)));
document.querySelectorAll('[data-open-modal]').forEach((button) => button.addEventListener('click', () => openPay(button.dataset.openModal)));
document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closePay(button.closest('.app-modal'))));
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const open = payModals.find((item) => !item.classList.contains('hidden'));
    if (open) closePay(open);
});
document.querySelectorAll('.pay-form').forEach((form) => {
    const hasLoan = form.dataset.hasLoan === '1';
    const outcomeInput = form.querySelector('[name="outcome"]');
    const methodInput = form.querySelector('[name="payment_method"]');
    const loanInput = form.querySelector('[name="loan_id"]');
    const amountInput = form.querySelector('[name="amount"]');
    const promiseField = form.querySelector('.promise-field');
    const amountField = form.querySelector('.amount-field');
    const methodField = form.querySelector('.method-field');
    const paintChips = (selector, key, value, size) => {
        form.querySelectorAll(selector).forEach((node) => {
            const active = node.dataset[key] === value;
            const dimmed = node.disabled && !active;
            const sizeClass = size === 'sm' ? 'rounded-xl px-3 py-2.5 text-xs font-semibold' : 'rounded-xl px-3 py-2.5 text-sm font-semibold';
            const colorClass = active ? 'bg-indigo-600 text-white' : (dimmed ? 'bg-slate-100 text-slate-400' : 'bg-slate-100 text-slate-600');
            node.className = `${node.classList.contains('outcome-chip') ? 'outcome-chip' : 'method-chip'} ${sizeClass} ${colorClass}`;
        });
    };
    const sync = () => {
        const paid = outcomeInput.value === 'collected';
        const promise = outcomeInput.value === 'promise';
        amountField.classList.toggle('hidden', !paid);
        methodField.classList.toggle('hidden', !paid);
        promiseField.classList.toggle('hidden', !promise);
        if (loanInput) loanInput.required = paid;
        amountInput.required = paid;
        amountInput.disabled = !paid;
        methodInput.required = paid;
        form.querySelector('[name="promise_date"]').required = promise;
        if (!paid) amountInput.value = '';
        paintChips('.outcome-chip', 'outcome', outcomeInput.value, 'md');
        paintChips('.method-chip', 'method', methodInput.value, 'sm');
    };
    form.querySelectorAll('.outcome-chip').forEach((chip) => chip.addEventListener('click', () => {
        if (chip.disabled) return;
        outcomeInput.value = chip.dataset.outcome;
        sync();
    }));
    form.querySelectorAll('.method-chip').forEach((chip) => chip.addEventListener('click', () => {
        methodInput.value = chip.dataset.method;
        sync();
    }));
    form.querySelectorAll('.loan-chip').forEach((chip) => chip.addEventListener('click', () => {
        if (!loanInput) return;
        loanInput.value = chip.dataset.loan;
        form.querySelectorAll('.loan-chip').forEach((node) => {
            const selected = node === chip;
            node.classList.toggle('border-indigo-500', selected);
            node.classList.toggle('bg-indigo-50', selected);
            node.classList.toggle('border-slate-200', !selected);
            node.classList.toggle('bg-white', !selected);
        });
    }));
    if (!hasLoan) outcomeInput.value = 'no_payment';
    sync();
});
@if($errors->any() && old('pay_stop'))
openPay('pay-{{ old('pay_stop') }}');
@endif
</script>
@endsection
