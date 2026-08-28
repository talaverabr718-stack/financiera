<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({ loans: Object, summary: Object, sellers: Array, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '', seller: props.filters.seller ?? '' }, props.endpoints.index);
const columns = [{key:'number',label:'Crédito'},{key:'client',label:'Cliente'},{key:'principal',label:'Principal'},{key:'balance',label:'Saldo'},{key:'status',label:'Estado'},{key:'actions',label:''}];
const money = value => new Intl.NumberFormat('es-NI',{minimumFractionDigits:2}).format(Number(value ?? 0));
const statusLabels = { active: 'Al día', delinquent: 'En mora', paid: 'Pagado' };
const statusTone = value => ({ active: 'bg-emerald-50 text-emerald-700', delinquent: 'bg-rose-50 text-rose-700', paid: 'bg-slate-100 text-slate-500' }[value] ?? 'bg-slate-100 text-slate-600');
</script>
<template>
    <AppLayout title="Cartera" eyebrow="Créditos" description="Saldos vivos, mora y colocación de la sucursal.">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="live-metric" data-tone="blue"><p>Colocado</p><strong>C$ {{ money(summary.total) }}</strong></article>
            <article class="live-metric" data-tone="emerald"><p>Saldo pendiente</p><strong>C$ {{ money(summary.outstanding) }}</strong></article>
            <article class="live-metric" data-tone="gold"><p>Créditos activos</p><strong>{{ summary.active }}</strong></article>
            <article class="live-metric" data-tone="rose"><p>En mora</p><strong>{{ summary.delinquent }}</strong></article>
        </div>
        <div class="mt-4 space-y-4">
            <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="Object.entries(statusLabels).map(([value,label])=>({value,label}))" placeholder="Crédito, cliente o identificación…" @clear="clear">
                <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.user?.name }}</option></select>
            </ResourceToolbar>
            <DataTable :columns="columns" :rows="loans.data" empty="No hay créditos con esos filtros." @row="row => router.visit(`/cartera/${row.id}`)">
                <template #cell-client="{ row }"><span class="font-semibold">{{ row.client?.full_name }}</span></template>
                <template #cell-principal="{ row }"><span class="font-mono">{{ row.currency }} {{ money(row.principal) }}</span></template>
                <template #cell-balance="{ row }"><span class="font-mono font-bold">{{ row.currency }} {{ money(Number(row.principal_balance)+Number(row.interest_balance)+Number(row.fee_balance)) }}</span></template>
                <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="statusTone(value)">{{ statusLabels[value] ?? value }}</span></template>
                <template #cell-actions="{ row }"><a :href="`/cartera/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
            </DataTable>
            <PaginationLinks :links="loans.links" />
        </div>
    </AppLayout>
</template>
