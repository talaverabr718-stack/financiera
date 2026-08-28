<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BaseModal from '../ui/BaseModal.vue';

const props = defineProps({
    open: Boolean,
    report: { type: Object, default: () => ({ visits: 0, payments: 0, collected: '0.00', routes: [], other_payments: [] }) },
    links: { type: Object, default: () => ({}) },
});
defineEmits(['close']);

const query = ref('');
const routeFilter = ref('all');
const view = ref('all');
const selectedId = ref(null);

const moneyExact = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO', minimumFractionDigits: 2 }).format(Number(value || 0));
const methodLabel = value => ({ cash: 'Efectivo', transfer: 'Transferencia', deposit: 'Depósito' }[value] || value || 'Pago');
const initials = name => (name || 'C').split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase();
const page = usePage();
const report = computed(() => props.report && typeof props.report === 'object' ? props.report : {});
const asList = value => Array.isArray(value) ? value : [];
const visitPaid = visit => asList(visit?.payments).reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
const matchesQuery = (name, extra = '') => `${name || ''} ${extra}`.toLowerCase().includes(query.value.trim().toLowerCase());
const routeCollected = route => asList(route.visits).reduce((sum, visit) => sum + visitPaid(visit), 0);
const routePaymentCount = route => asList(route.visits).reduce((sum, visit) => sum + asList(visit.payments).length, 0);
const printDate = ref('');
const printTime = ref('');
const stampPrint = () => {
    printDate.value = new Intl.DateTimeFormat('es-NI', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        timeZone: 'America/Managua',
    }).format(new Date());
    printTime.value = new Intl.DateTimeFormat('es-NI', {
        hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'America/Managua',
    }).format(new Date());
};

const officeRoute = computed(() => {
    const visits = asList(report.value.other_payments).map(payment => ({
        id: `pay-${payment.id}`,
        client: payment.client,
        client_url: payment.client_url,
        place: 'Fuera de agenda',
        status: 'visited',
        status_label: 'Cobrado',
        visited_at: payment.time,
        visitor: payment.visitor,
        outcome: 'collected',
        outcome_label: 'Cobrado',
        payments: [payment],
    }));

    return {
        id: 'office',
        name: 'Pagos fuera de ruta',
        code: 'OFICINA',
        collector: 'Caja / operación',
        visits,
    };
});

const routes = computed(() => {
    const items = [...asList(report.value.routes)];
    if (officeRoute.value.visits.length) items.push(officeRoute.value);
    return items;
});

const visibleRoutes = computed(() => {
    const selected = routeFilter.value === 'all' ? routes.value : routes.value.filter(route => String(route.id) === String(routeFilter.value));
    return selected.map(route => ({
        ...route,
        visits: asList(route.visits).filter(visit => {
            if (!matchesQuery(visit.client, `${visit.visitor || ''} ${visit.place || ''}`)) return false;
            if (view.value === 'collected') return visitPaid(visit) > 0;
            if (view.value === 'empty') return visitPaid(visit) <= 0;
            return true;
        }),
    })).filter(route => routeFilter.value !== 'all' || route.visits.length || view.value === 'all');
});

const visitRows = computed(() => visibleRoutes.value.flatMap(route => route.visits.map(visit => ({
    ...visit,
    routeName: route.name,
    routeCode: route.code,
    collector: route.collector,
}))));

const selected = computed(() => visitRows.value.find(visit => String(visit.id) === String(selectedId.value)) || null);

const resetFilters = () => {
    query.value = '';
    routeFilter.value = 'all';
    view.value = 'all';
    selectedId.value = null;
};

const syncSelection = () => {
    if (!props.open) return;
    if (visitRows.value.some(visit => String(visit.id) === String(selectedId.value))) return;
    selectedId.value = visitRows.value[0]?.id ?? null;
};

watch(() => props.open, open => {
    if (open) {
        stampPrint();
        syncSelection();
        return;
    }
    resetFilters();
}, { immediate: true });
watch(visitRows, syncSelection);

onMounted(() => {
    document.documentElement.classList.remove('is-printing-daily-report');
    stampPrint();
    syncSelection();
});

const setView = value => { view.value = value; };
const setRoute = value => { routeFilter.value = value; };

let pageStyle;
const stopPrinting = () => {
    document.documentElement.classList.remove('is-printing-daily-report');
    pageStyle?.remove();
    pageStyle = null;
};
const printReport = () => {
    stampPrint();
    stopPrinting();
    document.documentElement.classList.add('is-printing-daily-report');
    pageStyle = document.createElement('style');
    pageStyle.textContent = '@page { size: letter portrait; margin: 12mm 14mm; }';
    document.head.appendChild(pageStyle);
    const done = () => {
        stopPrinting();
        window.removeEventListener('afterprint', done);
    };
    window.addEventListener('afterprint', done);
    nextTick(() => window.setTimeout(() => window.print(), 40));
};
onUnmounted(stopPrinting);
</script>

<template>
    <BaseModal
        :open="open"
        title="Reportes del día"
        :description="`${report.visits || 0} visitas · ${report.payments || 0} pagos · ${moneyExact(report.collected)}`"
        size="daily-report-modal"
        @close="$emit('close')"
    >
        <div class="daily-report">
            <div class="daily-report-metrics">
                <button type="button" class="daily-report-metric" :class="{ 'is-on': view === 'all' && routeFilter === 'all' }" @click="setView('all'); setRoute('all')">
                    <small>Rutas</small>
                    <strong>{{ report.routes?.length || 0 }}</strong>
                </button>
                <button type="button" class="daily-report-metric" :class="{ 'is-on': view === 'all' }" @click="setView('all')">
                    <small>Visitas</small>
                    <strong>{{ report.visits || 0 }}</strong>
                </button>
                <button type="button" class="daily-report-metric" :class="{ 'is-on': view === 'collected' }" @click="setView('collected')">
                    <small>Pagos</small>
                    <strong>{{ report.payments || 0 }}</strong>
                </button>
                <button type="button" class="daily-report-metric is-money" :class="{ 'is-on': view === 'collected' }" @click="setView('collected')">
                    <small>Cobrado</small>
                    <strong>{{ moneyExact(report.collected) }}</strong>
                </button>
            </div>

            <div class="daily-report-toolbar">
                <input v-model="query" class="control m-0" type="search" placeholder="Buscar cliente, barrio o gestor…">
                <div class="daily-report-pills">
                    <button type="button" :class="{ 'is-on': view === 'all' }" @click="setView('all')">Todos</button>
                    <button type="button" :class="{ 'is-on': view === 'collected' }" @click="setView('collected')">Con pago</button>
                    <button type="button" :class="{ 'is-on': view === 'empty' }" @click="setView('empty')">Sin recaudo</button>
                </div>
            </div>

            <div v-if="routes.length" class="daily-report-routes">
                <button type="button" :class="{ 'is-on': routeFilter === 'all' }" @click="setRoute('all')">Todas las rutas</button>
                <button v-for="route in routes" :key="route.id" type="button" :class="{ 'is-on': String(routeFilter) === String(route.id) }" @click="setRoute(route.id)">
                    {{ route.name }}
                    <small>{{ route.visits.length }}</small>
                </button>
            </div>

            <div class="daily-report-split">
                <nav class="daily-report-nav" aria-label="Visitas del día">
                    <section v-for="route in visibleRoutes" :key="route.id">
                        <header>
                            <strong>{{ route.name }}</strong>
                            <small>{{ route.code }} · {{ route.collector || 'Sin gestor' }}</small>
                        </header>
                        <button
                            v-for="visit in route.visits"
                            :key="visit.id"
                            type="button"
                            class="daily-report-item"
                            :class="{ 'is-on': String(selectedId) === String(visit.id) }"
                            @click="selectedId = visit.id"
                        >
                            <span class="daily-report-avatar">{{ initials(visit.client) }}</span>
                            <span class="daily-report-copy">
                                <strong>{{ visit.client || 'Cliente' }}</strong>
                                <small>{{ visit.place || 'Estelí' }}{{ visit.visited_at ? ` · ${visit.visited_at}` : '' }}</small>
                            </span>
                            <em v-if="visitPaid(visit)" class="daily-report-amount">{{ moneyExact(visitPaid(visit)) }}</em>
                            <em v-else class="daily-report-amount is-muted">—</em>
                        </button>
                        <p v-if="!route.visits.length" class="daily-report-empty">{{ query || view !== 'all' ? 'Nada con este filtro.' : 'Sin visitas aún.' }}</p>
                    </section>
                    <p v-if="!visibleRoutes.length" class="daily-report-empty">No hay visitas ni pagos para mostrar.</p>
                </nav>

                <article v-if="selected" class="daily-report-stage">
                    <header class="daily-report-stage-head">
                        <span class="daily-report-avatar">{{ initials(selected.client) }}</span>
                        <div>
                            <strong>{{ selected.client || 'Cliente' }}</strong>
                            <small>{{ selected.routeName }} · {{ selected.place || 'Estelí' }} · {{ selected.visitor || selected.collector || 'Operación' }}{{ selected.visited_at ? ` · ${selected.visited_at}` : '' }}</small>
                        </div>
                        <b :data-status="selected.status">{{ selected.outcome_label || selected.status_label }}</b>
                    </header>

                    <div class="daily-report-facts">
                        <span>Recaudo<strong>{{ visitPaid(selected) ? moneyExact(visitPaid(selected)) : 'Sin recaudo' }}</strong></span>
                        <span>Pagos<strong>{{ selected.payments?.length || 0 }}</strong></span>
                        <span>Ruta<strong>{{ selected.routeCode || '—' }}</strong></span>
                    </div>

                    <template v-if="selected.payments?.length">
                        <article v-for="payment in selected.payments" :key="payment.id" class="daily-report-pay">
                            <div class="daily-report-pay-head">
                                <strong>{{ moneyExact(payment.amount) }}</strong>
                                <small>{{ methodLabel(payment.method) }} · {{ payment.receipt_number }} · {{ payment.loan_number }}</small>
                            </div>
                            <ul>
                                <li v-for="item in payment.installments" :key="item.id || item.number" :data-settled="item.settled ? 'yes' : 'no'">
                                    <span>{{ item.settled ? `Cuota ${item.number} cancelada` : `Cuota ${item.number} · abono ${moneyExact(item.applied)}` }}</span>
                                    <b>{{ item.settled ? 'Saldada' : `Queda ${moneyExact(item.remaining)}` }}</b>
                                </li>
                                <li v-if="!payment.installments?.length">Pago aplicado sin desglose de cuota.</li>
                            </ul>
                            <p :data-balance="payment.has_balance ? 'open' : 'settled'">
                                {{ payment.has_balance ? `El crédito queda ${moneyExact(payment.loan_balance)}` : 'El crédito quedó saldado' }}
                            </p>
                            <div class="daily-report-links">
                                <Link v-if="selected.client_url" :href="selected.client_url">Ver cliente</Link>
                                <Link v-if="payment.loan_url" :href="payment.loan_url">Ver crédito</Link>
                            </div>
                        </article>
                    </template>
                    <div v-else class="daily-report-empty">
                        <p>Esta visita no registró recaudo.</p>
                        <Link v-if="selected.client_url" :href="selected.client_url">Abrir expediente</Link>
                    </div>
                </article>
                <p v-else class="daily-report-stage daily-report-empty">Elige una visita a la izquierda para ver el detalle.</p>
            </div>
        </div>
        <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="btn-secondary" @click="printReport">Imprimir reporte</button>
                <Link :href="links.collections" class="btn-primary">Ir a cobranza</Link>
            </div>
        </template>
    </BaseModal>

    <Teleport to="body">
        <div v-if="open" class="daily-report-print" aria-hidden="true">
            <header class="daily-report-print-brand">
                <div class="print-brand-identity">
                    <img v-if="page.props.brand?.logo_url" :src="page.props.brand.logo_url" alt="">
                    <span v-else class="print-brand-fallback">F</span>
                    <div>
                        <strong>{{ page.props.brand?.system_name || 'Financiera' }}</strong>
                        <span>{{ page.props.brand?.system_tagline || 'Estelí' }}</span>
                    </div>
                </div>
                <div class="print-brand-meta">
                    <strong>Reportes del día</strong>
                    <span>{{ printDate }}</span>
                    <span>Impreso {{ printTime }}{{ page.props.auth?.user?.name ? ` · ${page.props.auth.user.name}` : '' }}</span>
                </div>
            </header>

            <section class="daily-report-print-summary">
                <div><small>Rutas</small><strong>{{ report.routes?.length || 0 }}</strong></div>
                <div><small>Visitas</small><strong>{{ report.visits || 0 }}</strong></div>
                <div><small>Pagos</small><strong>{{ report.payments || 0 }}</strong></div>
                <div><small>Cobrado</small><strong>{{ moneyExact(report.collected) }}</strong></div>
            </section>

            <section v-for="route in routes" :key="`print-${route.id}`" class="daily-report-print-route">
                <header>
                    <div>
                        <strong>{{ route.name }}</strong>
                        <small>{{ route.code }} · {{ route.collector || 'Sin gestor' }}</small>
                    </div>
                    <em>{{ route.visits.length }} {{ route.visits.length === 1 ? 'visita' : 'visitas' }} · {{ routePaymentCount(route) }} {{ routePaymentCount(route) === 1 ? 'pago' : 'pagos' }} · {{ moneyExact(routeCollected(route)) }}</em>
                </header>

                <article v-for="visit in route.visits" :key="visit.id" class="daily-report-print-visit">
                    <div class="daily-report-print-who">
                        <div>
                            <strong>{{ visit.client || 'Cliente' }}</strong>
                            <small>{{ visit.place || 'Estelí' }} · {{ visit.visitor || route.collector || 'Operación' }}{{ visit.visited_at ? ` · ${visit.visited_at}` : '' }}</small>
                        </div>
                        <b>{{ visit.outcome_label || visit.status_label }}</b>
                        <span>{{ visitPaid(visit) ? moneyExact(visitPaid(visit)) : 'Sin recaudo' }}</span>
                    </div>
                    <div v-if="visit.payments?.length" class="daily-report-print-pays">
                        <div v-for="payment in visit.payments" :key="payment.id">
                            <p><strong>{{ moneyExact(payment.amount) }}</strong> · {{ methodLabel(payment.method) }} · {{ payment.receipt_number }} · {{ payment.loan_number }}</p>
                            <ul>
                                <li v-for="item in payment.installments" :key="item.id || item.number">
                                    {{ item.settled ? `Cuota ${item.number} cancelada (saldada)` : `Cuota ${item.number} · abono ${moneyExact(item.applied)} · queda ${moneyExact(item.remaining)}` }}
                                </li>
                                <li v-if="!payment.installments?.length">Pago aplicado sin desglose de cuota.</li>
                            </ul>
                            <p>{{ payment.has_balance ? `El crédito queda ${moneyExact(payment.loan_balance)}` : 'El crédito quedó saldado' }}</p>
                        </div>
                    </div>
                    <p v-else class="daily-report-print-muted">Esta visita no registró recaudo.</p>
                </article>
                <p v-if="!route.visits.length" class="daily-report-print-muted">Esta ruta aún no tiene visitas registradas.</p>
            </section>
            <p v-if="!routes.length" class="daily-report-print-muted">No hay visitas ni pagos para mostrar.</p>
        </div>
    </Teleport>
</template>
