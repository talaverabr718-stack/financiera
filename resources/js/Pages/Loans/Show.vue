<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import InstallmentLedger from '../../components/loans/InstallmentLedger.vue';
import { usePermissions } from '../../composables/usePermissions.js';
const { canManage } = usePermissions();
const props = defineProps({ loan: Object, timeline: Array, delinquency: Object, endpoints: Object });
const status = useForm({ status: props.loan.status });
const mora = useForm({
    method: props.delinquency?.method || 'daily_percentage',
    daily_rate: props.delinquency?.daily_rate ?? '',
    fixed_amount: props.delinquency?.fixed_amount ?? '',
});
const money = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: props.loan.currency || 'NIO' }).format(Number(value || 0));
const date = value => value ? new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(new Date(value)) : '—';
const saveStatus = () => status.patch(props.endpoints.status, { preserveScroll: true });
const recalculate = () => mora.post(props.endpoints.recalculate, { preserveScroll: true });
</script>
<template>
    <AppLayout :title="loan.number" eyebrow="Cartera" :description="`${loan.client.full_name} · ${loan.status}`">
        <template #header-actions>
            <div class="flex gap-2">
                <a :href="endpoints.client" class="btn-secondary">Ver cliente</a>
                <a :href="endpoints.application" class="btn-secondary">Solicitud</a>
            </div>
        </template>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="item in [['Principal', loan.principal], ['Saldo principal', loan.principal_balance], ['Saldo interés', loan.interest_balance], ['Saldo total', loan.outstanding_balance]]" :key="item[0]" class="live-metric" data-tone="blue">
                <p>{{ item[0] }}</p>
                <strong>{{ money(item[1]) }}</strong>
            </div>
        </section>

        <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <form v-if="canManage('delinquency')" class="card p-5" @submit.prevent="recalculate">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Mora</p>
                    <h2 class="mt-1 font-semibold">Recalcular mora</h2>
                    <p class="mt-1 text-[11px] text-slate-400">Elige un porcentaje diario o un único cargo fijo por cada cuota vencida.</p>
                    <div class="mt-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                        <label class="field-label">Tipo de mora
                            <select v-model="mora.method" class="control">
                                <option value="daily_percentage">Porcentaje diario</option>
                                <option value="fixed">Cargo fijo por cuota vencida</option>
                            </select>
                        </label>
                        <label v-if="mora.method === 'daily_percentage'" class="field-label">% mora por día
                            <input v-model="mora.daily_rate" type="number" min="0" max="100" step="0.000001" required class="control" placeholder="Ej. 1">
                        </label>
                        <label v-else class="field-label">Cargo fijo
                            <input v-model="mora.fixed_amount" type="number" min="0" step="0.01" required class="control" placeholder="Ej. 15">
                        </label>
                        <button class="btn-primary" :disabled="mora.processing">{{ mora.processing ? 'Recalculando…' : 'Recalcular' }}</button>
                    </div>
                    <p class="mt-2 text-[11px] text-slate-400">{{ mora.method === 'daily_percentage' ? 'Fórmula: saldo pendiente × porcentaje diario × días vencidos.' : 'El importe se aplica una sola vez a cada cuota vencida.' }}</p>
                    <p v-if="Object.keys(mora.errors).length" class="mt-2 text-xs text-rose-400">{{ Object.values(mora.errors).join(' · ') }}</p>
                </form>

                <InstallmentLedger :rows="delinquency.ledger || []"/>

                <section class="card p-5">
                    <h2 class="font-semibold">Actividad del crédito</h2>
                    <div class="mt-4 space-y-3">
                        <article v-for="event in timeline" :key="`${event.type}-${event.date}`" class="border-l-2 border-blue-200 pl-4">
                            <p class="text-[10px] uppercase text-slate-400">{{ event.type }} · {{ date(event.date) }}</p>
                            <p class="mt-1 text-sm font-semibold">{{ event.title }}</p>
                            <p class="text-xs text-slate-500">{{ event.description }}</p>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="analytics-panel p-5 text-white">
                    <p class="dark-kicker">Estado financiero</p>
                    <h2 class="mt-2 text-lg font-semibold">{{ delinquency.in_arrears ? 'En mora' : 'Al día' }}</h2>
                    <p class="mt-2 text-xs text-slate-300">{{ delinquency.current_days || 0 }} días · {{ money(loan.delinquency_balance || delinquency.total_mora || 0) }} en mora</p>
                </section>
                <form v-if="canManage('loans')" class="card p-5" @submit.prevent="saveStatus">
                    <label class="field-label">Estado
                        <select v-model="status.status" class="control">
                            <option value="active">Activo</option>
                            <option value="delinquent">En mora</option>
                            <option value="paid">Pagado</option>
                        </select>
                    </label>
                    <button class="btn-primary mt-3 w-full" :disabled="status.processing">Guardar estado</button>
                    <p v-if="status.errors.status" class="mt-2 text-xs text-rose-600">{{ status.errors.status }}</p>
                </form>
                <section class="card p-5">
                    <p class="text-[10px] uppercase text-slate-400">Producto</p>
                    <p class="mt-2 font-semibold">{{ loan.application.product.name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Gestor: {{ loan.seller?.display_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Desembolso: {{ date(loan.disbursed_at) }}</p>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
