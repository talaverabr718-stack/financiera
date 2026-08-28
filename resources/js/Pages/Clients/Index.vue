<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({
    clients: Object,
    selectedClient: Object,
    board: { type: Object, default: () => ({}) },
    sellers: Array,
    filters: Object,
    endpoints: Object,
});
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '', seller: props.filters.seller ?? '' }, props.endpoints.index);
const columns = [
    { key: 'code', label: 'Código' }, { key: 'full_name', label: 'Cliente' },
    { key: 'identity_number', label: 'Identificación' }, { key: 'phone', label: 'Teléfono' },
    { key: 'status', label: 'Estado' }, { key: 'actions', label: '' },
];
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2 }).format(Number(value || 0));
const openLoans = computed(() => (props.selectedClient?.loans || []).filter(loan => ['active', 'delinquent'].includes(loan.status)));
const inArrears = computed(() => openLoans.value.some(loan => loan.status === 'delinquent'));
const openClient = row => router.get(props.endpoints.index, { ...filters, client: row.id }, { preserveState: true, preserveScroll: true, replace: true });
const briefing = computed(() => props.board?.briefing || {});
const stats = computed(() => props.board?.stats || {});
const ring = 226;
const mixTotal = computed(() => (props.board?.mix || []).reduce((sum, item) => sum + Number(item.value || 0), 0));
const pieSlices = computed(() => {
    let offset = 0;
    return (props.board?.mix || []).map(item => {
        const value = Number(item.value || 0);
        const length = mixTotal.value > 0 ? (value / mixTotal.value) * ring : 0;
        const slice = { ...item, value, length, offset, percent: mixTotal.value > 0 ? Math.round((value / mixTotal.value) * 100) : 0 };
        offset += length;
        return slice;
    });
});
const growth = computed(() => props.board?.growth || { points: [], added: 0, delta: 0 });
const growthChart = computed(() => {
    const points = growth.value.points || [];
    const values = points.map(item => Number(item.total || 0));
    const width = 240;
    const height = 88;
    const max = Math.max(...values, 1);
    const coords = values.map((value, index) => {
        const x = values.length <= 1 ? width / 2 : (index / (values.length - 1)) * width;
        const y = 8 + (1 - value / max) * 58;
        return { x, y, value, label: points[index]?.label, added: Number(points[index]?.added || 0) };
    });
    const line = coords.map((point, index) => `${index ? 'L' : 'M'}${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');
    const last = coords[coords.length - 1];
    const area = coords.length ? `${line} L${last.x.toFixed(2)},${height} L${coords[0].x.toFixed(2)},${height} Z` : '';
    const volumeMax = Math.max(...coords.map(point => point.added), 1);
    return { coords, line, area, width, height, volumeMax, last };
});
const loanBalance = loan => Number(loan.principal_balance || 0) + Number(loan.interest_balance || 0) + Number(loan.fee_balance || 0) + Number(loan.delinquency_balance || 0);
</script>
<template>
    <AppLayout hide-header title="Clientes">
        <div class="mesa clients-mesa">
            <section class="mesa-briefing">
                <div class="mesa-briefing-copy">
                    <p class="mesa-kicker"><i></i> Directorio</p>
                    <div class="mesa-briefing-meta">
                        <span>{{ briefing.date_label }}</span>
                    </div>
                    <h1>{{ briefing.title || 'Clientes' }}</h1>
                    <p class="mesa-situation">{{ briefing.situation }}</p>
                    <div class="mesa-actions">
                        <Link :href="endpoints.create" class="mesa-action" data-tone="emerald">
                            <strong>Nuevo cliente</strong>
                            <small>Registrar expediente</small>
                        </Link>
                    </div>
                </div>
                <aside class="mesa-pulse mesa-pie" aria-label="Estado de clientes">
                    <div class="mesa-pulse-head">
                        <span>Estado</span>
                        <strong>{{ stats.total || 0 }}</strong>
                    </div>
                    <div class="mesa-pie-chart">
                        <svg viewBox="0 0 80 80" aria-hidden="true">
                            <circle class="mesa-pie-track" cx="40" cy="40" r="36" pathLength="226"></circle>
                            <circle
                                v-for="slice in pieSlices"
                                :key="slice.key"
                                class="mesa-pie-slice"
                                :data-key="slice.key"
                                cx="40" cy="40" r="36"
                                pathLength="226"
                                :stroke-dasharray="`${slice.length} ${ring - slice.length}`"
                                :stroke-dashoffset="`${-slice.offset}`"
                            ></circle>
                        </svg>
                        <div class="mesa-pie-center">
                            <small>Total</small>
                            <b>{{ stats.total || 0 }}</b>
                        </div>
                    </div>
                    <ul class="mesa-pie-legend">
                        <li v-for="slice in pieSlices" :key="slice.key" :data-key="slice.key">
                            <i></i>
                            <span>{{ slice.label }}</span>
                            <b>{{ slice.value }}</b>
                            <em>{{ slice.percent }}%</em>
                        </li>
                    </ul>
                </aside>
                <aside class="mesa-pulse mesa-trade" aria-label="Altas de clientes">
                    <div class="mesa-pulse-head">
                        <span>Altas</span>
                        <strong :data-dir="Number(growth.delta) >= 0 ? 'up' : 'down'">{{ Number(growth.delta) >= 0 ? '+' : '' }}{{ growth.delta || 0 }}</strong>
                    </div>
                    <p class="mesa-trade-caption">Acumulado {{ stats.total || 0 }} · {{ growth.added || 0 }} este mes</p>
                    <svg class="mesa-trade-svg" :viewBox="`0 0 ${growthChart.width} ${growthChart.height}`" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="client-trade-fill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#34d399" stop-opacity=".42"></stop>
                                <stop offset="100%" stop-color="#34d399" stop-opacity="0"></stop>
                            </linearGradient>
                        </defs>
                        <line v-for="guide in [22, 40, 58]" :key="guide" x1="0" :y1="guide" :x2="growthChart.width" :y2="guide" class="mesa-trade-grid"></line>
                        <path v-if="growthChart.area" :d="growthChart.area" fill="url(#client-trade-fill)"></path>
                        <path v-if="growthChart.line" :d="growthChart.line" class="mesa-trade-line"></path>
                        <g class="mesa-trade-volume">
                            <rect
                                v-for="(point, index) in growthChart.coords"
                                :key="`vol-${index}`"
                                :x="point.x - 3.2"
                                :y="point.added ? growthChart.height - Math.max(3, (point.added / growthChart.volumeMax) * 14) : growthChart.height"
                                width="6.4"
                                :height="point.added ? Math.max(3, (point.added / growthChart.volumeMax) * 14) : 0"
                            ></rect>
                        </g>
                        <circle v-if="growthChart.last" class="mesa-trade-tip" :cx="growthChart.last.x" :cy="growthChart.last.y" r="3.2"></circle>
                    </svg>
                    <div class="mesa-trade-axis">
                        <span>{{ growthChart.coords[0]?.label }}</span>
                        <span>{{ growthChart.coords[Math.floor((growthChart.coords.length - 1) / 2)]?.label }}</span>
                        <span>{{ growthChart.last?.label }}</span>
                    </div>
                </aside>
                <div class="mesa-skyline" aria-hidden="true">
                    <svg viewBox="0 0 760 170" fill="none" stroke="currentColor" stroke-width="1.15">
                        <path d="M0 164h760M55 164V95h67v69M66 95V42l22-27 22 27v53M75 59h27M88 25V2M78 12h20M122 164V82h127v82M249 164V95h67v69M260 95V42l22-27 22 27v53M269 59h27M282 25V2M272 12h20M152 164v-55h67v55M173 164v-36h25v36M134 82l52-40 51 40M151 94h69M349 164c61-29 111-24 161 0s105 21 155-2 70-20 95-8"/>
                    </svg>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-4">
                    <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{value:'active',label:'Activos'},{value:'inactive',label:'Inactivos'},{value:'blocked',label:'Bloqueados'}]" placeholder="Nombre, código, cédula o teléfono…" @clear="clear">
                        <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.display_name }}</option></select>
                    </ResourceToolbar>
                    <DataTable :columns="columns" :rows="clients.data" empty="No se encontraron clientes." @row="openClient">
                        <template #cell-full_name="{ row }">
                            <div>
                                <p class="font-bold text-slate-800">{{ row.full_name }}</p>
                                <p class="text-[10px] text-slate-400">{{ row.neighborhood || row.municipality || 'Estelí' }} · {{ row.email || 'Sin correo' }}</p>
                            </div>
                        </template>
                        <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="value === 'active' ? 'bg-emerald-50 text-emerald-700' : value === 'blocked' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500'">{{ { active: 'Activo', inactive: 'Inactivo', blocked: 'Bloqueado' }[value] || value }}</span></template>
                        <template #cell-actions="{ row }"><a :href="`/clientes/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
                    </DataTable>
                    <PaginationLinks :links="clients.links" />
                </div>
                <aside v-if="selectedClient" class="card overflow-hidden xl:sticky xl:top-24">
                    <div class="border-b bg-slate-50 px-5 py-4">
                        <p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-blue-700">Ficha rápida</p>
                        <h2 class="mt-1 text-base font-bold text-slate-900">{{ selectedClient.full_name }}</h2>
                        <p class="mt-1 text-[11px] text-slate-500">{{ selectedClient.code }} · {{ selectedClient.identity_number || 'Sin cédula' }}</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <p class="text-xs"><span class="text-slate-400">Barrio</span><br><strong>{{ selectedClient.neighborhood || selectedClient.municipality || 'Estelí' }}</strong></p>
                        <p class="text-xs"><span class="text-slate-400">Teléfono</span><br><strong>{{ selectedClient.phone || 'No registrado' }}</strong></p>
                        <p class="text-xs"><span class="text-slate-400">Actividad</span><br><strong>{{ selectedClient.economic_activity || 'No registrada' }}</strong></p>
                        <div class="rounded-xl px-3 py-2 text-xs font-semibold" :class="inArrears ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'">
                            {{ inArrears ? 'Tiene crédito en mora' : (openLoans.length ? 'Crédito al día' : 'Sin crédito abierto') }}
                        </div>
                        <div v-for="loan in openLoans.slice(0,3)" :key="loan.id" class="rounded-xl border border-slate-100 p-3">
                            <div class="flex justify-between gap-2 text-xs font-bold"><span>{{ loan.number }}</span><span>{{ loan.status === 'delinquent' ? 'En mora' : 'Al día' }}</span></div>
                            <p class="mt-1 text-[11px] text-slate-500">Saldo C$ {{ money(loanBalance(loan)) }}</p>
                        </div>
                        <a :href="`/clientes/${selectedClient.id}`" class="btn-primary flex w-full justify-center">Abrir expediente</a>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
