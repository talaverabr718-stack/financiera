<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import GoogleServiceMap from '../../components/dashboard/GoogleServiceMap.vue';
import DailyReportModal from '../../components/dashboard/DailyReportModal.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    briefing: { type: Object, default: () => ({}) },
    till: { type: Object, default: () => ({}) },
    aging: { type: Array, default: () => [] },
    decisionQueue: { type: Array, default: () => [] },
    overdueWatch: { type: Array, default: () => [] },
    todayStops: { type: Array, default: () => [] },
    upcomingInstallments: { type: Array, default: () => [] },
    recentPayments: { type: Array, default: () => [] },
    fieldActivity: { type: Array, default: () => [] },
    collectorActivity: { type: Array, default: () => [] },
    paymentMix: { type: Array, default: () => [] },
    neighborhoods: { type: Array, default: () => [] },
    promisesToday: { type: Array, default: () => [] },
    dailyReport: { type: Object, default: () => ({ visits: 0, payments: 0, collected: '0.00', routes: [], other_payments: [] }) },
    closing: { type: Object, default: () => ({ opens_at: '08:00', closes_at: '17:00' }) },
    portfolioTrend: { type: Array, default: () => [] },
    collectionTrend: { type: Array, default: () => [] },
    period: { type: Number, default: 6 },
    links: { type: Object, default: () => ({}) },
    monthName: String,
});

const clock = ref(props.briefing.time_label || '');
const closeState = ref({ percent: 0, remaining: '', phase: 'open' });
const reportOpen = ref(false);
const ring = 226;
let timer;
let poll;

onMounted(() => {
    document.documentElement.classList.remove('is-printing-daily-report');
    if (window.location.hash === '#reportes-del-dia') reportOpen.value = true;
    const tick = () => {
        clock.value = new Intl.DateTimeFormat('es-NI', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'America/Managua',
        }).format(new Date());
        const parts = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'America/Managua' }).formatToParts(new Date());
        const hour = Number(parts.find(part => part.type === 'hour')?.value || 0);
        const minute = Number(parts.find(part => part.type === 'minute')?.value || 0);
        const nowMin = hour * 60 + minute;
        const [openH, openM] = String(props.closing.opens_at || '08:00').split(':').map(Number);
        const [closeH, closeM] = String(props.closing.closes_at || '17:00').split(':').map(Number);
        const open = openH * 60 + openM;
        const close = closeH * 60 + closeM;
        if (nowMin < open) {
            closeState.value = { percent: 0, remaining: `Abre a las ${props.closing.opens_at}`, phase: 'before' };
        } else if (nowMin >= close) {
            closeState.value = { percent: 100, remaining: 'Caja cerrada', phase: 'closed' };
        } else {
            const left = close - nowMin;
            closeState.value = {
                percent: ((nowMin - open) / (close - open)) * 100,
                remaining: `Faltan ${Math.floor(left / 60)} h ${String(left % 60).padStart(2, '0')} min`,
                phase: 'open',
            };
        }
    };
    tick();
    timer = setInterval(tick, 1000);
    poll = setInterval(() => {
        router.reload({
            only: ['fieldActivity', 'till', 'stats', 'paymentMix', 'todayStops', 'promisesToday', 'collectorActivity', 'dailyReport', 'briefing'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 45000);
});
onUnmounted(() => {
    clearInterval(timer);
    clearInterval(poll);
});

const money = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO', maximumFractionDigits: 0 }).format(Number(value || 0));
const moneyExact = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO', minimumFractionDigits: 2 }).format(Number(value || 0));
const shortDate = value => value ? new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`)) : '—';
const signed = value => {
    const amount = Number(value || 0);
    const prefix = amount > 0 ? '+' : '';
    return `${prefix}${money(amount)}`;
};
const deltaTone = value => Number(value) > 0 ? 'up' : (Number(value) < 0 ? 'down' : 'flat');
const ago = value => {
    if (!value) return '';
    const minutes = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 60000));
    if (minutes < 1) return 'Ahora';
    if (minutes < 60) return `Hace ${minutes} min`;
    return `Hace ${Math.floor(minutes / 60)} h`;
};

const collectionMax = computed(() => Math.max(...props.collectionTrend.map(item => Number(item.value)), 1));
const chartPoints = computed(() => {
    const values = props.portfolioTrend.map(item => Number(item.value));
    const max = Math.max(...values, 1);
    const step = values.length > 1 ? 100 / (values.length - 1) : 100;
    return values.map((value, index) => `${index * step},${34 - (value / max) * 26}`).join(' ');
});
const mapPins = computed(() => props.todayStops
    .filter(stop => Number.isFinite(Number(stop.lat)) && Number.isFinite(Number(stop.lng)) && Number(stop.lat) && Number(stop.lng))
    .map(stop => ({
        id: stop.id,
        name: stop.client?.full_name,
        route: stop.route,
        status: stop.status,
        statusLabel: stop.status_label,
        neighborhood: stop.client?.neighborhood,
        lat: Number(stop.lat),
        lng: Number(stop.lng),
    })));
const placedDelta = computed(() => Number(props.stats.placed || 0) - Number(props.stats.placedLastMonth || 0));
const healthy = computed(() => Math.max(0, 100 - Number(props.stats.delinquencyRate || 0)));
const featured = computed(() => props.overdueWatch[0] || null);
const watchRest = computed(() => props.overdueWatch.slice(1));
const featuredInitials = computed(() => (featured.value?.client || 'C').split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase());
const mixTotal = computed(() => props.paymentMix.reduce((sum, item) => sum + Number(item.amount || 0), 0));
const mixMax = computed(() => Math.max(...props.paymentMix.map(item => Number(item.amount || 0)), 1));
const routeGroups = computed(() => {
    const groups = [];
    const index = {};
    props.todayStops.forEach(stop => {
        const name = stop.route || 'Ruta';
        if (index[name] === undefined) {
            index[name] = groups.length;
            groups.push({ name, collector: stop.collector, stops: [] });
        }
        groups[index[name]].stops.push(stop);
    });
    return groups;
});
const tickerItems = computed(() => {
    const rows = props.fieldActivity.filter(item => item.line);
    return rows.length ? [...rows, ...rows] : [];
});
const changePeriod = value => router.get('/', { period: value }, { preserveState: true, preserveScroll: true, replace: true });
const methodLabel = value => ({ cash: 'Efectivo', transfer: 'Transferencia', deposit: 'Depósito' }[value] || value || 'Pago');
const isDailyReport = action => {
    const url = String(action?.url || '');
    const label = String(action?.label || '').toLowerCase();
    return action?.opens === 'daily-report'
        || url.includes('reportes-del-dia')
        || label.includes('reportes del día')
        || label.includes('reportes del dia');
};
const openDailyReport = event => {
    event?.preventDefault?.();
    event?.stopPropagation?.();
    reportOpen.value = true;
};
const closeDailyReport = () => { reportOpen.value = false; };
</script>

<template>
    <Head title="Mesa de operaciones" />
    <AppLayout hide-header title="Mesa de operaciones">
        <div class="mesa" :data-close="closeState.phase">
            <section class="mesa-briefing">
                <div class="mesa-briefing-copy">
                    <p class="mesa-kicker"><i></i> Mesa de operaciones · Estelí</p>
                    <div class="mesa-briefing-meta">
                        <span>{{ briefing.date_label }}</span>
                        <time :datetime="clock">{{ clock }}</time>
                    </div>
                    <h1>{{ briefing.greeting }}, {{ briefing.first_name }}.</h1>
                    <p class="mesa-situation">{{ briefing.situation }}</p>
                    <div class="mesa-actions">
                        <template v-for="action in briefing.actions || []" :key="action.label">
                            <button
                                v-if="isDailyReport(action)"
                                type="button"
                                class="mesa-action"
                                :data-tone="action.tone"
                                @click.stop.prevent="openDailyReport"
                            >
                                <strong>{{ action.label }}</strong>
                                <small>{{ action.hint }}</small>
                            </button>
                            <Link
                                v-else
                                :href="action.url"
                                class="mesa-action"
                                :data-tone="action.tone"
                            >
                                <strong>{{ action.label }}</strong>
                                <small>{{ action.hint }}</small>
                            </Link>
                        </template>
                    </div>
                </div>
                <aside class="mesa-pulse">
                    <div class="mesa-close" :data-phase="closeState.phase">
                        <svg viewBox="0 0 80 80" aria-hidden="true">
                            <circle cx="40" cy="40" r="36" pathLength="226"></circle>
                            <circle cx="40" cy="40" r="36" pathLength="226" :stroke-dasharray="ring" :stroke-dashoffset="ring - (ring * closeState.percent / 100)"></circle>
                        </svg>
                        <div>
                            <span>{{ closing.label || 'Cierre de caja' }}</span>
                            <strong>{{ closeState.remaining }}</strong>
                        </div>
                    </div>
                    <div class="mesa-pulse-head">
                        <span>Pulso de la semana</span>
                        <strong>{{ money(collectionTrend.reduce((sum, item) => sum + Number(item.value || 0), 0)) }}</strong>
                    </div>
                    <div class="mesa-pulse-bars">
                        <Link
                            v-for="item in collectionTrend"
                            :key="item.label"
                            :href="links.collections"
                            class="mesa-pulse-col"
                            :title="`${item.label}: ${money(item.value)}`"
                        >
                            <i :style="{ '--mesa-h': `${Math.max(8, Number(item.value) / collectionMax * 100)}%` }"></i>
                            <small>{{ item.label.split(' ')[0] }}</small>
                        </Link>
                    </div>
                </aside>
                <div class="mesa-skyline" aria-hidden="true">
                    <svg viewBox="0 0 760 170" fill="none" stroke="currentColor" stroke-width="1.15">
                        <path d="M0 164h760M55 164V95h67v69M66 95V42l22-27 22 27v53M75 59h27M88 25V2M78 12h20M122 164V82h127v82M249 164V95h67v69M260 95V42l22-27 22 27v53M269 59h27M282 25V2M272 12h20M152 164v-55h67v55M173 164v-36h25v36M134 82l52-40 51 40M151 94h69M349 164c61-29 111-24 161 0s105 21 155-2 70-20 95-8"/>
                        <path d="M345 145l32-35 36 35 41-59 49 59 39-28 43 28M586 164v-43h38v43m8 0v-65h47v65"/>
                    </svg>
                </div>
            </section>

            <div v-if="tickerItems.length" class="mesa-ticker" aria-label="Gestiones recientes">
                <b>En vivo</b>
                <div class="mesa-ticker-track">
                    <p v-for="(item, index) in tickerItems" :key="`${item.id}-${index}`">
                        <em>{{ ago(item.at) }}</em> {{ item.line }}
                    </p>
                </div>
            </div>

            <section class="mesa-kpis">
                <Link :href="links.loans" class="mesa-kpi" data-tone="navy">
                    <p>Cartera viva</p>
                    <strong>{{ money(stats.activePortfolio) }}</strong>
                    <small>{{ stats.activeLoans }} créditos · {{ healthy }}% al día</small>
                </Link>
                <Link :href="links.collections" class="mesa-kpi mesa-kpi-live" data-tone="emerald">
                    <p>Cobrado hoy</p>
                    <strong>{{ money(stats.collectedToday) }}</strong>
                    <small :data-dir="deltaTone(till.delta)">{{ signed(till.delta) }} vs ayer</small>
                </Link>
                <Link :href="links.delinquency" class="mesa-kpi" data-tone="rose">
                    <p>Mora que duele</p>
                    <strong>{{ money(stats.overdueAmount) }}</strong>
                    <span class="mesa-aging-inline">
                        <em v-for="bucket in aging" :key="bucket.key" :data-bucket="bucket.key">{{ bucket.count }}</em>
                    </span>
                </Link>
                <article class="mesa-kpi mesa-kpi-chart" data-tone="gold">
                    <div class="mesa-kpi-chart-copy">
                        <p>Colocado en {{ monthName }}</p>
                        <strong>{{ money(stats.placed) }}</strong>
                        <small :data-dir="deltaTone(placedDelta)">{{ signed(placedDelta) }} vs mes pasado</small>
                    </div>
                    <div>
                        <select :value="period" class="mesa-period" @change="changePeriod(Number($event.target.value))">
                            <option :value="6">6 meses</option>
                            <option :value="12">12 meses</option>
                        </select>
                        <svg viewBox="0 0 100 36" preserveAspectRatio="none" class="mesa-spark" aria-hidden="true">
                            <polyline :points="chartPoints" fill="none" stroke="currentColor" stroke-width="1.8" vector-effect="non-scaling-stroke"/>
                        </svg>
                    </div>
                </article>
            </section>

            <section class="mesa-stage">
                <article class="mesa-till">
                    <header>
                        <div>
                            <p class="mesa-section-kicker">Caja del día</p>
                            <h2>Cobrado frente a lo esperado</h2>
                        </div>
                        <Link :href="links.collections">Abrir cobranza →</Link>
                    </header>
                    <div class="mesa-till-figures">
                        <div>
                            <span>Cobrado</span>
                            <b>{{ moneyExact(till.collected) }}</b>
                        </div>
                        <div>
                            <span>{{ till.surpassed ? 'Meta superada' : 'Pendiente de cuota' }}</span>
                            <b>{{ moneyExact(till.remaining) }}</b>
                        </div>
                    </div>
                    <div class="mesa-thermometer" role="img" :aria-label="`Avance ${till.percent}%`">
                        <i :style="{ '--mesa-fill': `${till.percent}%` }"></i>
                    </div>
                    <p class="mesa-till-caption">
                        <template v-if="Number(till.expected) > 0">Esperado hoy: {{ moneyExact(till.expected) }} · {{ till.due_count }} cuotas con vencimiento.</template>
                        <template v-else>No hay cuotas con vencimiento hoy. El recaudo que entre es extraordinario o de mora.</template>
                    </p>
                    <div class="mesa-fajos">
                        <div v-for="item in paymentMix" :key="item.key" class="mesa-fajo" :data-method="item.key">
                            <span>{{ item.label }}</span>
                            <b>{{ money(item.amount) }}</b>
                            <i :style="{ '--mesa-h': `${Math.max(8, Number(item.amount) / mixMax * 100)}%` }"></i>
                            <small>{{ item.operations }} {{ item.operations === 1 ? 'pago' : 'pagos' }}</small>
                        </div>
                    </div>
                    <div class="mesa-till-chips">
                        <span>{{ till.visited_stops }} visitadas</span>
                        <span>{{ till.pending_stops }} pendientes</span>
                        <span>{{ mixTotal ? money(mixTotal) + ' por forma de pago' : 'Sin mix aún' }}</span>
                    </div>
                </article>

                <article class="mesa-map">
                    <header>
                        <div>
                            <p class="mesa-section-kicker">Territorio de hoy</p>
                            <h2>Ruta en Estelí</h2>
                        </div>
                        <Link :href="links.routes">Gestionar rutas →</Link>
                    </header>
                    <GoogleServiceMap :pins="mapPins" :routes-url="links.routes"/>
                    <div v-for="group in routeGroups" :key="group.name" class="mesa-path">
                        <p>{{ group.name }} · {{ group.collector || 'Sin gestor' }}</p>
                        <ol>
                            <li v-for="stop in group.stops" :key="stop.id">
                                <Link :href="stop.client?.url || links.collections" class="mesa-bead" :data-status="stop.status" :title="stop.note || stop.status_label">
                                    <em>{{ String(stop.position).padStart(2, '0') }}</em>
                                    <strong>{{ stop.client?.neighborhood || stop.client?.full_name }}</strong>
                                    <small>{{ stop.status_label }}</small>
                                    <span v-if="stop.note">{{ stop.note }}</span>
                                </Link>
                            </li>
                        </ol>
                    </div>
                    <p v-if="!todayStops.length" class="empty-state">No hay paradas programadas para hoy.</p>
                </article>

                <article class="mesa-queue">
                    <header>
                        <div>
                            <p class="mesa-section-kicker">Cola de decisiones</p>
                            <h2>Solicitudes que esperan</h2>
                        </div>
                        <Link :href="links.applications">Ver todas →</Link>
                    </header>
                    <Link v-for="item in decisionQueue" :key="item.id" :href="item.url" class="mesa-ticket">
                        <time>{{ item.days_waiting === 0 ? 'Hoy' : `Hace ${item.days_waiting} d` }}</time>
                        <span>
                            <strong>{{ item.client }}</strong>
                            <small>{{ item.number }} · {{ item.product || 'Producto' }}</small>
                        </span>
                        <b>{{ money(item.requested_amount) }}</b>
                        <em :data-status="item.status">{{ item.status_label }}</em>
                    </Link>
                    <p v-if="!decisionQueue.length" class="empty-state">No hay solicitudes en espera. El flujo de originación está al día.</p>
                </article>

                <article class="mesa-watch">
                    <header>
                        <div>
                            <p class="mesa-section-kicker">Mora que duele</p>
                            <h2>La que más pesa hoy</h2>
                        </div>
                        <Link :href="links.delinquency">Abrir mora →</Link>
                    </header>
                    <article v-if="featured" class="mesa-featured" :data-bucket="featured.bucket">
                        <Link :href="featured.loan_url" class="mesa-featured-main">
                            <span class="mesa-featured-avatar">{{ featuredInitials }}</span>
                            <div>
                                <p>Prioridad de recaudo</p>
                                <h3>{{ featured.client }}</h3>
                                <small>{{ featured.place }} · {{ featured.days }} días · {{ featured.loan_number }}</small>
                            </div>
                            <b>{{ moneyExact(featured.outstanding) }}</b>
                        </Link>
                        <a v-if="featured.phone" :href="`tel:${featured.phone}`" class="mesa-call">{{ featured.phone }}</a>
                    </article>
                    <div class="mesa-aging">
                        <Link v-for="bucket in aging" :key="bucket.key" :href="links.delinquency" class="mesa-aging-card" :data-bucket="bucket.key">
                            <small>{{ bucket.hint }}</small>
                            <strong>{{ bucket.count }}</strong>
                            <span>{{ bucket.label }}</span>
                            <b>{{ money(bucket.amount) }}</b>
                        </Link>
                    </div>
                    <Link v-for="item in watchRest" :key="item.id" :href="item.loan_url" class="mesa-watch-row">
                        <span>
                            <strong>{{ item.client }}</strong>
                            <small>{{ item.place || 'Estelí' }} · {{ item.loan_number }} · cuota {{ item.installment }}</small>
                        </span>
                        <em :data-bucket="item.bucket">{{ item.days }} d</em>
                        <b>{{ moneyExact(item.outstanding) }}</b>
                    </Link>
                    <p v-if="!overdueWatch.length" class="empty-state">No hay cuotas vencidas con saldo pendiente.</p>
                </article>
            </section>

            <section v-if="neighborhoods.length" class="mesa-barrios">
                <header>
                    <div>
                        <p class="mesa-section-kicker">Estelí de un vistazo</p>
                        <h2>Mosaico por barrio</h2>
                    </div>
                </header>
                <div class="mesa-barrio-grid">
                    <Link
                        v-for="place in neighborhoods"
                        :key="place.name"
                        :href="links.collections"
                        class="mesa-barrio"
                        :data-tone="place.tone"
                        :style="{ '--mesa-weight': Math.max(place.weight, 28) }"
                    >
                        <strong>{{ place.name }}</strong>
                        <small>{{ place.stops }} visitas · {{ place.overdue_count }} en mora</small>
                        <b v-if="Number(place.overdue_amount)">{{ money(place.overdue_amount) }}</b>
                    </Link>
                </div>
            </section>

            <section v-if="promisesToday.length" class="mesa-promises">
                <header>
                    <div>
                        <p class="mesa-section-kicker">Compromisos</p>
                        <h2>Promesas que caen hoy</h2>
                    </div>
                    <Link :href="links.collections">Cobrar →</Link>
                </header>
                <div class="mesa-notes">
                    <Link v-for="item in promisesToday" :key="item.id" :href="item.url" class="mesa-note">
                        <strong>{{ item.client }}</strong>
                        <small>{{ item.place }} · {{ item.collector || 'Gestión' }}</small>
                        <p>{{ item.note || 'Promesa de pago para hoy.' }}</p>
                        <em v-if="item.phone">{{ item.phone }}</em>
                    </Link>
                </div>
            </section>

            <section class="mesa-strip">
                <article>
                    <header>
                        <h2>Gestores en campo</h2>
                        <Link :href="links.collections">Actividad →</Link>
                    </header>
                    <div v-for="agent in collectorActivity" :key="agent.id" class="mesa-agent">
                        <span class="mesa-avatar">{{ agent.name?.charAt(0) || 'G' }}</span>
                        <span>
                            <strong>{{ agent.name || 'Gestión interna' }}</strong>
                            <small>{{ agent.zone || 'Estelí' }} · {{ agent.visited_stops }}/{{ agent.visited_stops + agent.pending_stops }} visitas</small>
                        </span>
                        <b>{{ money(agent.amount) }}</b>
                        <em :data-status="agent.status">{{ agent.status_label }}</em>
                    </div>
                    <p v-if="!collectorActivity.length" class="empty-state">Nadie tiene ruta ni recaudo registrado hoy.</p>
                </article>

                <article>
                    <header>
                        <h2>Gestión de campo</h2>
                        <Link :href="links.collections">Cobranza →</Link>
                    </header>
                    <div v-for="record in fieldActivity" :key="record.id" class="mesa-field">
                        <span>
                            <strong>{{ record.client }}</strong>
                            <small>{{ record.collector || 'Operación' }} · {{ record.place }} · {{ record.time }}</small>
                        </span>
                        <em :data-outcome="record.outcome">{{ record.outcome_label }}</em>
                        <b v-if="Number(record.amount)">{{ money(record.amount) }}</b>
                    </div>
                    <div v-if="!fieldActivity.length" class="mesa-field-empty">
                        <p class="empty-state">Aún no hay gestiones registradas hoy.</p>
                        <Link v-for="payment in recentPayments.slice(0, 4)" :key="payment.id" :href="payment.url" class="mesa-field">
                            <span>
                                <strong>{{ payment.client }}</strong>
                                <small>{{ methodLabel(payment.method) }} · {{ payment.receipt_number }} · {{ payment.received_at }}</small>
                            </span>
                            <b>{{ money(payment.amount) }}</b>
                        </Link>
                    </div>
                </article>

                <article>
                    <header>
                        <h2>Próximos vencimientos</h2>
                        <Link :href="links.loans">Cartera →</Link>
                    </header>
                    <Link v-for="installment in upcomingInstallments" :key="installment.id" :href="installment.loan.url" class="mesa-upcoming">
                        <time>{{ shortDate(installment.due_date) }}</time>
                        <span>
                            <strong>{{ installment.client?.full_name }}</strong>
                            <small>{{ installment.loan.number }} · cuota {{ installment.number }}</small>
                        </span>
                        <b>{{ moneyExact(installment.outstanding) }}</b>
                    </Link>
                    <p v-if="!upcomingInstallments.length" class="empty-state">No hay vencimientos próximos.</p>
                </article>
            </section>
        </div>
    </AppLayout>
    <DailyReportModal v-if="reportOpen" :open="true" :report="dailyReport || {}" :links="links" @close="closeDailyReport"/>
</template>
