<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({ clients: Object, selectedClient: Object, sellers: Array, filters: Object, endpoints: Object });
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
</script>
<template>
    <AppLayout title="Clientes" eyebrow="Directorio" description="Expedientes de Estelí, cartera asignada y situación del crédito.">
        <template #header-actions><a :href="endpoints.create" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Nuevo cliente</a></template>
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-4">
                <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{value:'active',label:'Activos'},{value:'inactive',label:'Inactivos'}]" placeholder="Nombre, código, cédula o teléfono…" @clear="clear">
                    <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.user?.name }}</option></select>
                </ResourceToolbar>
                <DataTable :columns="columns" :rows="clients.data" empty="No se encontraron clientes." @row="openClient">
                    <template #cell-full_name="{ row }">
                        <div>
                            <p class="font-bold text-slate-800">{{ row.full_name }}</p>
                            <p class="text-[10px] text-slate-400">{{ row.neighborhood || row.municipality || 'Estelí' }} · {{ row.email || 'Sin correo' }}</p>
                        </div>
                    </template>
                    <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="value === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ value === 'active' ? 'Activo' : 'Inactivo' }}</span></template>
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
                        <p class="mt-1 text-[11px] text-slate-500">Saldo C$ {{ money(Number(loan.principal_balance)+Number(loan.interest_balance)+Number(loan.fee_balance)) }}</p>
                    </div>
                    <a :href="`/clientes/${selectedClient.id}`" class="btn-primary flex w-full justify-center">Abrir expediente</a>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
