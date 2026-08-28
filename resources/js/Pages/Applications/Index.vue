<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';
const props = defineProps({ applications: Object, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '' }, props.endpoints.index);
const columns = [{key:'number',label:'Solicitud'},{key:'client',label:'Cliente'},{key:'requested_amount',label:'Monto'},{key:'status',label:'Estado'},{key:'created_at',label:'Fecha'},{key:'actions',label:''}];
const money = value => new Intl.NumberFormat('es-NI',{minimumFractionDigits:2}).format(Number(value));
const statusTone = value => ({ draft: 'bg-slate-100 text-slate-700', submitted: 'bg-sky-50 text-sky-700', review: 'bg-amber-50 text-amber-800', approved: 'bg-emerald-50 text-emerald-700', rejected: 'bg-rose-50 text-rose-700', cancelled: 'bg-slate-100 text-slate-500', disbursed: 'bg-indigo-50 text-indigo-700' }[value] ?? 'bg-indigo-50 text-indigo-700');
const statusLabels = { draft: 'Borrador', submitted: 'Enviada', review: 'En revisión', approved: 'Aprobada', rejected: 'Rechazada', cancelled: 'Cancelada', disbursed: 'Desembolsada' };
</script>
<template>
    <AppLayout title="Solicitudes" eyebrow="Crédito" description="Seguimiento instantáneo del proceso de originación.">
        <template #header-actions><a :href="endpoints.create" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Nueva solicitud</a></template>
        <div class="space-y-4">
            <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="['draft','submitted','review','approved','rejected','cancelled','disbursed'].map(value=>({value,label:statusLabels[value]}))" placeholder="Número o cliente…" @clear="clear" />
            <DataTable :columns="columns" :rows="applications.data">
                <template #cell-client="{ row }"><span class="font-semibold">{{ row.client?.full_name }}</span></template>
                <template #cell-requested_amount="{ row }"><span class="font-mono font-bold text-sky-700">{{ row.currency }} {{ money(row.requested_amount) }}</span></template>
                <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="statusTone(value)">{{ statusLabels[value] ?? value }}</span></template>
                <template #cell-created_at="{ value }">{{ new Date(value).toLocaleDateString('es-NI') }}</template>
                <template #cell-actions="{ row }"><a :href="`/solicitudes/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
            </DataTable>
            <PaginationLinks :links="applications.links" />
        </div>
    </AppLayout>
</template>
