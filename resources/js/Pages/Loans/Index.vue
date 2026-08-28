<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import MesaModuleBoard from '../../components/mesa/MesaModuleBoard.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({ loans: Object, summary: Object, board: { type: Object, default: () => ({}) }, sellers: Array, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '', seller: props.filters.seller ?? '' }, props.endpoints.index);
const columns = [{key:'number',label:'Crédito'},{key:'client',label:'Cliente'},{key:'principal',label:'Principal'},{key:'balance',label:'Saldo'},{key:'mora',label:'Mora'},{key:'status',label:'Estado'},{key:'actions',label:''}];
const money = value => new Intl.NumberFormat('es-NI',{minimumFractionDigits:2}).format(Number(value ?? 0));
const statusLabels = { active: 'Al día', delinquent: 'En mora', paid: 'Pagado' };
const statusTone = value => ({ active: 'bg-emerald-50 text-emerald-700', delinquent: 'bg-rose-50 text-rose-700', paid: 'bg-slate-100 text-slate-500' }[value] ?? 'bg-slate-100 text-slate-600');
</script>
<template>
    <AppLayout hide-header title="Cartera">
        <div class="mesa module-mesa">
            <MesaModuleBoard
                :board="board"
                kicker="Colocación"
                pie-label="Estado"
                pie-center="Total"
                trade-label="Desembolsos"
                :trade-caption="`Acumulado ${board.stats?.total || 0} · ${board.growth?.added || 0} este mes`"
                fill-id="loans-trade-fill"
            />
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="live-metric" data-tone="blue"><p>Colocado</p><strong>C$ {{ money(summary.total) }}</strong></article>
                <article class="live-metric" data-tone="emerald"><p>Saldo pendiente</p><strong>C$ {{ money(summary.outstanding) }}</strong></article>
                <article class="live-metric" data-tone="gold"><p>Créditos activos</p><strong>{{ summary.active }}</strong></article>
                <article class="live-metric" data-tone="rose"><p>En mora</p><strong>{{ summary.delinquent }}</strong></article>
            </div>
            <div class="space-y-4">
                <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="Object.entries(statusLabels).map(([value,label])=>({value,label}))" placeholder="Crédito, cliente o identificación…" @clear="clear">
                    <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.display_name }}</option></select>
                </ResourceToolbar>
                <DataTable :columns="columns" :rows="loans.data" empty="No hay créditos con esos filtros." @row="row => router.visit(`/cartera/${row.id}`)">
                    <template #cell-client="{ row }"><span class="font-semibold">{{ row.client?.full_name }}</span></template>
                    <template #cell-principal="{ row }"><span class="font-mono">{{ row.currency }} {{ money(row.principal) }}</span></template>
                    <template #cell-balance="{ row }"><span class="font-mono font-bold">{{ row.currency }} {{ money(Number(row.principal_balance)+Number(row.interest_balance)+Number(row.fee_balance)+Number(row.delinquency_balance || 0)) }}</span></template>
                    <template #cell-mora="{ row }"><span class="font-mono" :class="Number(row.delinquency_balance) > 0 ? 'font-semibold text-rose-700' : ''">{{ row.currency }} {{ money(row.delinquency_balance) }}</span></template>
                    <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="statusTone(value)">{{ statusLabels[value] ?? value }}</span></template>
                    <template #cell-actions="{ row }"><a :href="`/cartera/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
                </DataTable>
                <PaginationLinks :links="loans.links" />
            </div>
        </div>
    </AppLayout>
</template>
