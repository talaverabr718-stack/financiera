@extends('clients.layout')
@section('title',$loan->number)
@section('content')
<x-page-header :title="$loan->number" :description="$loan->client->full_name.' · '.$loan->application->product->name.' · '.$loan->application->number" eyebrow="Detalle de cartera"><x-slot:actions><a href="{{route('clients.show',$loan->client)}}" class="btn-secondary">Ver cliente</a><a href="{{route('collections.index')}}" class="btn-secondary"><i data-lucide="hand-coins" class="icon"></i>Cobranza</a></x-slot:actions></x-page-header>
<section class="mb-4 grid gap-3 sm:grid-cols-3">
    <article class="metric"><p class="text-[11px] text-slate-500">Saldo</p><p class="mt-2 text-xl font-semibold tabular-nums">{{$loan->currency}} {{number_format((float)$loan->outstanding_balance,2)}}</p></article>
    <article class="metric"><p class="text-[11px] text-slate-500">Principal original</p><p class="mt-2 text-xl font-semibold tabular-nums">{{$loan->currency}} {{number_format((float)$loan->principal,2)}}</p></article>
    <button type="button" class="metric w-full text-left" data-open-modal="loan-schedule" aria-label="Ver historial de pagos y próximas cuotas">
        <div class="flex items-center justify-between"><p class="text-[11px] text-slate-500">Estado</p><x-status-badge :status="$loan->status" /></div>
        <p class="mt-2 text-sm font-medium text-slate-600">{{ data_get($loan->approved_terms,'term') }} cuotas · {{ ['daily'=>'Diaria','weekly'=>'Semanal','biweekly'=>'Quincenal','monthly'=>'Mensual'][data_get($loan->approved_terms,'frequency')] ?? data_get($loan->approved_terms,'frequency') }}</p>
        <p class="mt-1 text-[10px] font-semibold text-indigo-500">Ver pagos y próximas fechas</p>
    </button>
</section>
<div class="mb-4">@include('delinquency._status-card', ['summary' => $delinquency, 'currency' => $loan->currency, 'loan' => $loan])</div>
@include('loans._installment-ledger', ['summary' => $delinquency])
@include('loans._schedule-modal')
<script>
const scheduleModal = document.getElementById('loan-schedule');
const openSchedule = () => {
    if (!scheduleModal) return;
    document.body.appendChild(scheduleModal);
    scheduleModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
const closeSchedule = () => {
    if (!scheduleModal) return;
    scheduleModal.classList.add('hidden');
    document.body.style.overflow = '';
};
document.querySelectorAll('[data-open-modal="loan-schedule"]').forEach((button) => button.addEventListener('click', openSchedule));
scheduleModal?.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', closeSchedule));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && scheduleModal && !scheduleModal.classList.contains('hidden')) closeSchedule();
});
</script>
@endsection
