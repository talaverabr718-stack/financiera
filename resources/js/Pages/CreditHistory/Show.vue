<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ client: Object, loans: Array, endpoints: Object });
const selectedId = ref(props.loans[0]?.id ?? null);
const selected = computed(() => props.loans.find(loan => loan.id === selectedId.value) ?? null);
const money = (value, currency = 'NIO') => new Intl.NumberFormat('es-NI', { style: 'currency', currency }).format(Number(value ?? 0));
const statusLabel = value => ({ active: 'Vigente', delinquent: 'En mora', paid: 'Cancelado' }[value] ?? value);
const methodLabel = value => ({ cash: 'Efectivo', transfer: 'Transferencia', check: 'Cheque' }[value] ?? value);
watch(() => props.loans, loans => {
    if (!loans.some(loan => loan.id === selectedId.value)) {
        selectedId.value = loans[0]?.id ?? null;
    }
});
</script>
<template>
    <AppLayout :title="client.full_name" eyebrow="Historial crediticio" :description="`${client.code} · ${client.identity_number || 'Sin cédula'} · ${client.credits_count} crédito${client.credits_count === 1 ? '' : 's'}`">
        <template #header-actions>
            <div class="flex flex-col items-end gap-2">
                <a v-if="client.can_originate_new_credit" :href="endpoints.new_credit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Nuevo crédito</a>
                <button v-else type="button" disabled class="cursor-not-allowed rounded-xl bg-slate-200 px-4 py-2 text-xs font-bold text-slate-500">Nuevo crédito</button>
                <p class="max-w-xs text-right text-[10px] text-slate-400">{{ client.can_originate_new_credit ? 'El crédito vigente ya fue cancelado. Puede originar uno nuevo.' : 'Cancela el crédito vigente para desbloquear uno nuevo.' }}</p>
            </div>
        </template>
        <div class="space-y-4">
            <Link :href="endpoints.index" class="text-xs font-semibold text-indigo-600">← Historial crediticio</Link>
            <div class="grid gap-3 sm:grid-cols-3">
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Créditos</p><p class="mt-2 text-xl font-black">{{ client.credits_count }}</p></article>
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Cancelados</p><p class="mt-2 text-xl font-black">{{ client.paid_credits_count }}</p></article>
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Nuevo crédito</p><p class="mt-2 text-sm font-black" :class="client.can_originate_new_credit ? 'text-emerald-700' : 'text-slate-500'">{{ client.can_originate_new_credit ? 'Desbloqueado' : 'Bloqueado' }}</p></article>
            </div>
            <div class="grid gap-4 lg:grid-cols-[.9fr_1.1fr]">
                <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="border-b px-5 py-4">
                        <h2 class="text-sm font-semibold">Créditos del cliente</h2>
                        <p class="mt-1 text-[11px] text-slate-400">Selecciona un crédito para ver su historial de pago.</p>
                    </div>
                    <div v-if="loans.length" class="divide-y">
                        <button v-for="loan in loans" :key="loan.id" type="button" class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50" :class="selectedId === loan.id && 'bg-indigo-50/70'" @click="selectedId = loan.id">
                            <div>
                                <p class="font-bold text-slate-800">{{ loan.number }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">{{ loan.product || 'Sin producto' }} · {{ loan.disbursed_at || 'Sin desembolso' }}</p>
                                <p class="mt-1 font-mono text-xs">{{ money(loan.principal, loan.currency) }}<template v-if="Number(loan.mora) > 0"> · mora {{ money(loan.mora, loan.currency) }}</template></p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="loan.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : loan.status === 'delinquent' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700'">{{ statusLabel(loan.status) }}</span>
                        </button>
                    </div>
                    <p v-else class="p-8 text-center text-xs text-slate-400">Este cliente todavía no tiene créditos desembolsados.</p>
                </section>
                <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold">Historial de pago</h2>
                            <p class="mt-1 text-[11px] text-slate-400">{{ selected ? `${selected.number} · saldo ${money(selected.outstanding, selected.currency)}${Number(selected.mora) > 0 ? ` · mora ${money(selected.mora, selected.currency)}` : ''}` : 'Elige un crédito.' }}</p>
                        </div>
                        <a v-if="selected" :href="selected.show_url" class="text-xs font-semibold text-indigo-600">Ver en cartera</a>
                    </div>
                    <div v-if="selected?.payments?.length" class="divide-y">
                        <article v-for="payment in selected.payments" :key="payment.id" class="flex items-start justify-between gap-3 px-5 py-4">
                            <div>
                                <p class="font-semibold text-slate-800">{{ payment.receipt_number }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">{{ payment.received_at }} · {{ methodLabel(payment.method) }}{{ payment.reference ? ` · ${payment.reference}` : '' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono font-bold">{{ money(payment.amount, payment.currency) }}</p>
                                <p class="mt-1 text-[10px] uppercase text-slate-400">{{ payment.status }}</p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="p-8 text-center text-xs text-slate-400">{{ selected ? 'Este crédito no tiene pagos registrados.' : 'Selecciona un crédito para ver los pagos.' }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
