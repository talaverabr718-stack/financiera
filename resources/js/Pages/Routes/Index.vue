<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import GoogleServiceMap from '../../components/dashboard/GoogleServiceMap.vue';
import MesaModuleBoard from '../../components/mesa/MesaModuleBoard.vue';

const props = defineProps({
    routes: Array,
    openRoutes: Array,
    completedRoutes: Array,
    selectedRoute: Object,
    date: String,
    googleMapsKey: String,
    board: { type: Object, default: () => ({}) },
    endpoints: Object,
});
const status = useForm({ status: props.selectedRoute?.status || 'planned' });
const visit = stop => router.patch(props.endpoints.visitTemplate.replace('__STOP__', stop.id), { notes: '' }, { preserveScroll: true });
const saveStatus = () => status.patch(`/rutas/${props.selectedRoute.id}/estado`, { preserveScroll: true });
const done = route => (route.stops || []).filter(stop => stop.status === 'visited').length;
const money = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(Number(value || 0));
const duesFor = stop => stop.dues || { overdue: [], due_today: [], overdue_total: '0.00', due_today_total: '0.00', total: '0.00' };
const hasDues = stop => Number(duesFor(stop).total || 0) > 0;
const moraSuffix = item => Number(item?.mora || 0) > 0 ? ` · mora ${money(item.mora)}` : '';
const shortDate = value => {
    if (!value) return '';
    const [year, month, day] = String(value).slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
};
const statusLabel = status => ({
    pending: 'Pendiente',
    visited: 'Visitado',
    not_found: 'No encontrado',
    rescheduled: 'Promesa',
}[status] || status);
const visitStamp = stop => stop.paid_at_label || stop.visited_at_label;
const mapPins = computed(() => (props.selectedRoute?.stops || [])
    .filter(stop => Number.isFinite(Number(stop.client?.latitude)) && Number.isFinite(Number(stop.client?.longitude)))
    .map(stop => ({
        id: stop.id,
        name: stop.client?.full_name,
        route: props.selectedRoute?.name,
        status: stop.status,
        statusLabel: statusLabel(stop.status),
        neighborhood: stop.client?.neighborhood,
        lat: Number(stop.client.latitude),
        lng: Number(stop.client.longitude),
    })));
</script>
<template>
    <AppLayout hide-header title="Rutas de cobranza">
        <div class="mesa module-mesa is-bars">
            <MesaModuleBoard
                :board="board"
                chart="bars"
                kicker="Campo"
                trade-label="Visitas"
                :trade-caption="`${board.stats?.total || 0} visitas marcadas en el día`"
            >
                <template #actions>
                    <label class="mesa-action" data-tone="blue">
                        <strong>Fecha</strong>
                        <small>
                            <input type="date" :value="String(date).slice(0, 10)" class="mesa-date" @change="router.get(endpoints.index, { date: $event.target.value })">
                        </small>
                    </label>
                    <Link :href="endpoints.create" class="mesa-action" data-tone="emerald">
                        <strong>Nueva ruta</strong>
                        <small>Programar jornada</small>
                    </Link>
                </template>
            </MesaModuleBoard>
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="live-metric" data-tone="blue"><p>Rutas del día</p><strong>{{ routes.length }}</strong></div>
                <div class="live-metric" data-tone="emerald"><p>Visitas realizadas</p><strong>{{ routes.reduce((n, route) => n + done(route), 0) }}</strong></div>
                <div class="live-metric" data-tone="gold"><p>Pendientes</p><strong>{{ routes.reduce((n, route) => n + route.stops.filter(stop => stop.status === 'pending').length, 0) }}</strong></div>
            </section>
            <div class="grid gap-4 xl:grid-cols-[320px_1fr]">
                <aside class="card overflow-hidden">
                    <div class="section-heading"><h2 class="font-semibold">Rutas</h2></div>
                    <div class="divide-y">
                        <a v-for="route in routes" :key="route.id" :href="`${endpoints.index}?date=${String(date).slice(0, 10)}&route=${route.id}`" class="block p-4" :class="selectedRoute?.id === route.id ? 'bg-blue-50' : 'hover:bg-slate-50'">
                            <div class="flex justify-between"><p class="font-semibold">{{ route.name }}</p><span class="badge bg-slate-100 text-slate-600">{{ route.status }}</span></div>
                            <p class="mt-1 text-[10px] text-slate-400">{{ route.collector.display_name }} · {{ done(route) }}/{{ route.stops.length }}</p>
                        </a>
                    </div>
                </aside>
                <div v-if="selectedRoute" class="space-y-4">
                    <section class="analytics-panel p-5 text-white">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <p class="dark-kicker">Ruta seleccionada</p>
                                <h2 class="mt-2 text-lg font-semibold">{{ selectedRoute.name }}</h2>
                                <p class="text-xs text-slate-300">{{ selectedRoute.code }} · {{ selectedRoute.collector.display_name }}</p>
                            </div>
                            <form class="flex gap-2" @submit.prevent="saveStatus">
                                <select v-model="status.status" class="rounded-lg bg-white px-3 text-xs text-slate-800">
                                    <option value="planned">Programada</option>
                                    <option value="active">En recorrido</option>
                                    <option value="completed">Finalizada</option>
                                    <option value="cancelled">Cancelada</option>
                                </select>
                                <button class="btn-primary">Cambiar estado</button>
                            </form>
                        </div>
                    </section>
                    <section class="card overflow-hidden">
                        <div class="section-heading">
                            <h2 class="font-semibold">Mapa de la ruta</h2>
                        </div>
                        <div class="routes-map">
                            <GoogleServiceMap :pins="mapPins" :routes-url="endpoints.index"/>
                        </div>
                    </section>
                    <section class="card overflow-hidden">
                        <div class="section-heading flex justify-between">
                            <h2 class="font-semibold">Clientes</h2>
                            <a :href="`/rutas/${selectedRoute.id}/editar`" class="text-xs font-semibold text-blue-700">Editar ruta</a>
                        </div>
                        <div class="divide-y">
                            <div v-for="stop in selectedRoute.stops" :key="stop.id" class="p-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-blue-50 text-xs font-bold text-blue-700">{{ stop.position }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold">{{ stop.client.full_name }}</p>
                                        <p class="truncate text-[10px] text-slate-400">{{ stop.client.address }}</p>
                                    </div>
                                    <span class="badge shrink-0 whitespace-nowrap" :class="stop.status === 'visited' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ statusLabel(stop.status) }}<template v-if="visitStamp(stop)"> · {{ visitStamp(stop) }}</template></span>
                                    <button v-if="stop.status === 'pending'" type="button" class="btn-soft" @click="visit(stop)">Marcar visita</button>
                                </div>
                                <div class="agenda-dues">
                                    <p v-if="!hasDues(stop)" class="agenda-dues-empty">Sin cuotas vencidas ni cuota con vencimiento en esta fecha.</p>
                                    <template v-else>
                                        <div v-if="duesFor(stop).due_today.length" class="agenda-due is-today">
                                            <header><span>Cuota de hoy</span><b>{{ money(duesFor(stop).due_today_total) }}</b></header>
                                            <p v-for="item in duesFor(stop).due_today" :key="item.id">Cuota {{ item.number }} · {{ item.loan_number }} · vence {{ shortDate(item.due_date) }} · {{ money(item.outstanding) }}{{ moraSuffix(item) }}</p>
                                        </div>
                                        <div v-if="duesFor(stop).overdue.length" class="agenda-due is-overdue">
                                            <header><span>Cuotas vencidas</span><b>{{ money(duesFor(stop).overdue_total) }}</b></header>
                                            <p v-for="item in duesFor(stop).overdue" :key="item.id">Cuota {{ item.number }} · {{ item.loan_number }} · {{ item.days }} {{ item.days === 1 ? 'día' : 'días' }} · {{ money(item.outstanding) }}{{ moraSuffix(item) }}</p>
                                        </div>
                                        <p class="agenda-dues-total">A cobrar en esta visita: {{ money(duesFor(stop).total) }}</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <section v-else class="card grid min-h-80 place-items-center text-slate-400">No hay rutas para la fecha.</section>
            </div>
        </div>
    </AppLayout>
</template>
