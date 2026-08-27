<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';
const props = defineProps({ loans: Object, summary: Object, sellers: Array, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '', seller: props.filters.seller ?? '' }, props.endpoints.index);
const columns = [{key:'number',label:'Crédito'},{key:'client',label:'Cliente'},{key:'principal',label:'Principal'},{key:'balance',label:'Saldo'},{key:'status',label:'Estado'},{key:'actions',label:''}];
const money = value => new Intl.NumberFormat('es-NI',{minimumFractionDigits:2}).format(Number(value ?? 0));
</script>
<template>
    <AppLayout title="Cartera" eyebrow="Créditos" description="Saldos y estado operativo de los préstamos.">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in [{l:'Colocado',v:summary.total},{l:'Saldo pendiente',v:summary.outstanding},{l:'Créditos activos',v:summary.active,n:true},{l:'En mora',v:summary.delinquent,n:true}]" :key="card.l" class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">{{ card.l }}</p><p class="mt-2 text-xl font-black text-slate-900">{{ card.n ? card.v : `C$ ${money(card.v)}` }}</p></article>
        </div>
        <div class="mt-4 space-y-4">
            <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="['active','delinquent','paid'].map(value=>({value,label:value}))" placeholder="Crédito, cliente o identificación…" @clear="clear">
                <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.user?.name }}</option></select>
            </ResourceToolbar>
            <DataTable :columns="columns" :rows="loans.data">
                <template #cell-client="{ row }"><span class="font-semibold">{{ row.client?.full_name }}</span></template>
                <template #cell-principal="{ row }"><span class="font-mono">{{ row.currency }} {{ money(row.principal) }}</span></template>
                <template #cell-balance="{ row }"><span class="font-mono font-bold">{{ row.currency }} {{ money(Number(row.principal_balance)+Number(row.interest_balance)+Number(row.fee_balance)) }}</span></template>
                <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="value === 'delinquent' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'">{{ value }}</span></template>
                <template #cell-actions="{ row }"><a :href="`/cartera/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
            </DataTable>
            <PaginationLinks :links="loans.links" />
        </div>
    </AppLayout>
</template>
