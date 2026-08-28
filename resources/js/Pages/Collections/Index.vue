<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import BaseModal from '../../components/ui/BaseModal.vue';

const props = defineProps({
    date: String,
    routes: { type: Array, default: () => [] },
    collectedToday: [String, Number],
    paymentHistory: Object,
    upcomingVisits: Number,
    upcomingStops: Array,
    pendingStops: { type: Array, default: () => [] },
    lateCollections: Number,
    lateInstallments: Array,
    selectedRoute: Object,
    storeTemplate: String,
});

const money = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(Number(value || 0));
const forms = new Map();
const dateValue = computed(() => String(props.date || '').slice(0, 10));
const agendaStops = computed(() => [...(props.selectedRoute?.stops || [])].sort((a, b) => Number(a.position) - Number(b.position)));
const collectibleLoans = stop => (stop.client?.loans || []).filter(loan => ['active', 'delinquent'].includes(loan.status));
const pendingCount = route => (route.stops || []).filter(stop => stop.status === 'pending').length;
const duesFor = stop => stop.dues || { overdue: [], due_today: [], overdue_total: '0.00', due_today_total: '0.00', total: '0.00' };
const hasDues = stop => Number(duesFor(stop).total || 0) > 0;
const shortDate = value => {
    if (!value) return '';
    const [year, month, day] = String(value).slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
};
const statusLabel = status => ({
    pending: 'Pendiente',
    visited: 'Visitada',
    not_found: 'No encontrado',
    rescheduled: 'Promesa',
}[status] || status);
const statusClass = status => ({
    pending: 'bg-amber-50 text-amber-700',
    visited: 'bg-emerald-50 text-emerald-700',
    not_found: 'bg-rose-50 text-rose-700',
    rescheduled: 'bg-indigo-50 text-indigo-700',
}[status] || 'bg-slate-100 text-slate-600');

const managingStop = ref(null);

const formFor = stop => {
    if (!forms.has(stop.id)) {
        forms.set(stop.id, useForm({
            outcome: 'collected',
            loan_id: collectibleLoans(stop)[0]?.id || '',
            amount: '',
            payment_method: 'cash',
            reference: '',
            promise_date: '',
            notes: '',
        }));
    }
    return forms.get(stop.id);
};

const managementForm = computed(() => managingStop.value ? formFor(managingStop.value) : null);

const openManagement = stop => { managingStop.value = stop; };
const closeManagement = () => {
    if (managementForm.value?.processing) return;
    managingStop.value = null;
};
const submit = stop => formFor(stop).post(props.storeTemplate.replace('__STOP__', stop.id), {
    preserveScroll: true,
    onSuccess: () => { managingStop.value = null; },
});
const changeDate = event => router.get('/cobranza', { date: event.target.value });
const selectRoute = id => router.get('/cobranza', { date: dateValue.value, agenda_route: id }, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Cobranza" eyebrow="Operación viva" description="Agenda, recaudo y seguimiento de compromisos.">
        <template #header-actions>
            <input type="date" :value="dateValue" class="control m-0" @change="changeDate">
        </template>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="live-metric" data-tone="emerald"><p>Cobrado en la fecha</p><strong>{{ money(collectedToday) }}</strong></div>
            <div class="live-metric" data-tone="blue"><p>Visitas pendientes</p><strong>{{ pendingStops.length }}</strong></div>
            <div class="live-metric" data-tone="gold"><p>Próximas visitas</p><strong>{{ upcomingVisits }}</strong></div>
            <div class="live-metric" data-tone="rose"><p>Cuotas vencidas</p><strong>{{ lateCollections }}</strong></div>
        </section>

        <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="card overflow-hidden">
                <div class="section-heading">
                    <div>
                        <h2 class="font-semibold">Agenda de hoy</h2>
                        <p v-if="selectedRoute" class="mt-1 text-[11px] text-slate-400">{{ selectedRoute.name }} · {{ selectedRoute.collector?.user?.name || 'Sin gestor' }} · {{ agendaStops.length }} clientes</p>
                    </div>
                </div>

                <div v-if="routes.length" class="agenda-routes">
                    <button
                        v-for="route in routes"
                        :key="route.id"
                        type="button"
                        class="agenda-route"
                        :class="{ 'is-active': selectedRoute?.id === route.id }"
                        @click="selectRoute(route.id)"
                    >
                        <strong>{{ route.name }}</strong>
                        <small>{{ route.collector?.user?.name || 'Sin gestor' }} · {{ pendingCount(route) }} pendientes</small>
                    </button>
                </div>

                <div class="divide-y">
                    <article v-for="stop in agendaStops" :key="stop.id" class="p-5">
                        <div class="flex justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Parada {{ String(stop.position).padStart(2, '0') }} · {{ selectedRoute?.name }}</p>
                                <a :href="`/clientes/${stop.client.id}`" class="mt-1 block font-semibold hover:underline">{{ stop.client.full_name }}</a>
                                <p class="text-[10px] text-slate-400">{{ stop.client.address }}</p>
                                <p v-if="stop.client.phone" class="text-[10px] text-slate-400">{{ stop.client.phone }}</p>
                            </div>
                            <span class="badge h-fit" :class="statusClass(stop.status)">{{ statusLabel(stop.status) }}</span>
                        </div>

                        <div class="agenda-dues">
                            <p v-if="!hasDues(stop)" class="agenda-dues-empty">Sin cuotas vencidas ni cuota con vencimiento en esta fecha.</p>
                            <template v-else>
                                <div v-if="duesFor(stop).due_today.length" class="agenda-due is-today">
                                    <header>
                                        <span>Cuota de hoy</span>
                                        <b>{{ money(duesFor(stop).due_today_total) }}</b>
                                    </header>
                                    <p v-for="item in duesFor(stop).due_today" :key="item.id">Cuota {{ item.number }} · {{ item.loan_number }} · vence {{ shortDate(item.due_date) }} · {{ money(item.outstanding) }}</p>
                                </div>
                                <div v-if="duesFor(stop).overdue.length" class="agenda-due is-overdue">
                                    <header>
                                        <span>Cuotas vencidas</span>
                                        <b>{{ money(duesFor(stop).overdue_total) }}</b>
                                    </header>
                                    <p v-for="item in duesFor(stop).overdue" :key="item.id">Cuota {{ item.number }} · {{ item.loan_number }} · {{ item.days }} {{ item.days === 1 ? 'día' : 'días' }} · {{ money(item.outstanding) }}</p>
                                </div>
                                <p class="agenda-dues-total">A cobrar en esta visita: {{ money(duesFor(stop).total) }}</p>
                            </template>
                        </div>

                        <div v-if="stop.status === 'pending'" class="agenda-stop-actions">
                            <button type="button" class="btn-primary" @click="openManagement(stop)">Registrar gestión</button>
                        </div>
                    </article>
                    <p v-if="!routes.length" class="empty-state">No hay rutas programadas para esta fecha.</p>
                    <p v-else-if="!agendaStops.length" class="empty-state">Esta ruta no tiene clientes asignados.</p>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="analytics-panel p-5 text-white">
                    <p class="dark-kicker">Mora prioritaria</p>
                    <div class="mt-4 space-y-3">
                        <div v-for="installment in lateInstallments.slice(0, 6)" :key="installment.id" class="rounded-xl bg-white/5 p-3">
                            <p class="text-xs font-semibold">{{ installment.loan.client.full_name }}</p>
                            <p class="mt-1 text-[10px] text-slate-300">{{ installment.loan.number }} · cuota {{ installment.number }}</p>
                        </div>
                        <p v-if="!lateInstallments.length" class="text-xs text-slate-300">Sin cuotas vencidas.</p>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="section-heading"><h2 class="font-semibold">Historial reciente</h2></div>
                    <div class="divide-y">
                        <div v-for="record in paymentHistory.data.slice(0, 8)" :key="record.id" class="p-4">
                            <div class="flex justify-between">
                                <p class="text-xs font-semibold">{{ record.client.full_name }}</p>
                                <p class="text-xs font-semibold text-emerald-700">{{ record.amount ? money(record.amount) : record.outcome }}</p>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">{{ record.stop?.route?.name }}</p>
                        </div>
                    </div>
                    <PaginationLinks :links="paymentHistory.links"/>
                </section>
            </aside>
        </div>

        <BaseModal
            :open="Boolean(managingStop)"
            title="Registrar gestión"
            :description="managingStop ? `${managingStop.client.full_name} · ${selectedRoute?.name || ''}` : ''"
            size="collection-modal"
            @close="closeManagement"
        >
            <form v-if="managingStop && managementForm" id="collection-management-form" class="grid gap-3" @submit.prevent="submit(managingStop)">
                <p v-if="hasDues(managingStop)" class="agenda-dues-total">A cobrar en esta visita: {{ money(duesFor(managingStop).total) }}</p>
                <label class="field-label">Resultado
                    <select v-model="managementForm.outcome" class="control">
                        <option value="collected">Cobrado</option>
                        <option value="promise">Promesa</option>
                        <option value="no_payment">Sin pago</option>
                        <option value="not_found">No encontrado</option>
                    </select>
                </label>
                <template v-if="managementForm.outcome === 'collected'">
                    <label class="field-label">Crédito
                        <select v-model="managementForm.loan_id" class="control">
                            <option v-for="loan in collectibleLoans(managingStop)" :key="loan.id" :value="loan.id">{{ loan.number }}</option>
                        </select>
                    </label>
                    <label class="field-label">Monto<input v-model="managementForm.amount" type="number" min=".01" step=".01" class="control"></label>
                    <label class="field-label">Forma
                        <select v-model="managementForm.payment_method" class="control">
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                            <option value="deposit">Depósito</option>
                        </select>
                    </label>
                </template>
                <label v-if="managementForm.outcome === 'promise'" class="field-label">Fecha prometida<input v-model="managementForm.promise_date" type="date" class="control"></label>
                <label class="field-label">Notas<input v-model="managementForm.notes" class="control"></label>
                <p v-if="Object.keys(managementForm.errors).length" class="text-xs text-rose-400">{{ Object.values(managementForm.errors).join(' · ') }}</p>
            </form>
            <template #footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary w-full sm:w-auto" :disabled="managementForm?.processing" @click="closeManagement">Cancelar</button>
                    <button type="submit" form="collection-management-form" class="btn-primary w-full sm:w-auto" :disabled="managementForm?.processing">{{ managementForm?.processing ? 'Registrando…' : 'Registrar gestión' }}</button>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
