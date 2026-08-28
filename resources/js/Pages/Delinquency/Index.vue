<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import MesaModuleBoard from '../../components/mesa/MesaModuleBoard.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({ cases: Object, board: { type: Object, default: () => ({}) }, filters: Object, endpoints: Object });
const page = usePage();
const { filters, clear } = useResourceFilters({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'active',
    sort: props.filters.sort ?? 'days',
    direction: props.filters.direction ?? 'desc',
}, props.endpoints.index);
const columns = [
    { key: 'code', label: 'Código' },
    { key: 'client', label: 'Cliente' },
    { key: 'loan', label: 'Crédito' },
    { key: 'current_days', label: 'Días' },
    { key: 'overdue_balance', label: 'Saldo vencido' },
    { key: 'mora', label: 'Mora' },
    { key: 'started_on', label: 'Inicio' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: '' },
];
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = value => value ? new Date(`${value}T00:00:00`).toLocaleDateString('es-NI') : '—';
const recalculate = () => router.post(props.endpoints.recalculate);
</script>
<template>
    <AppLayout hide-header title="Clientes en mora">
        <div class="mesa module-mesa">
            <MesaModuleBoard
                :board="board"
                kicker="Atraso"
                pie-label="Expedientes"
                pie-center="Total"
                trade-label="Antigüedad"
                trade-caption="Tramos de días en expedientes activos"
                fill-id="delinquency-trade-fill"
            >
                <template #actions>
                    <button type="button" class="mesa-action" data-tone="rose" @click="recalculate">
                        <strong>Recalcular mora</strong>
                        <small>Actualizar cuotas vencidas</small>
                    </button>
                </template>
            </MesaModuleBoard>
            <div class="space-y-4">
                <p v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">{{ page.props.flash.success }}</p>
                <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{value:'active',label:'Activos'},{value:'resolved',label:'Resueltos'},{value:'cancelled',label:'Cancelados'},{value:'all',label:'Todos'}]" placeholder="Código, cliente, cédula o crédito…" @clear="clear">
                    <select v-model="filters.sort" class="control sm:w-52">
                        <option value="days">Mayor cantidad de días</option>
                        <option value="balance">Mayor saldo vencido</option>
                        <option value="started_on">Fecha de inicio</option>
                        <option value="client">Cliente</option>
                        <option value="code">Código de mora</option>
                    </select>
                </ResourceToolbar>
                <DataTable :columns="columns" :rows="cases.data" empty="No hay expedientes de mora. El listado se arma con cuotas vencidas e impagas, no solo con el estado del crédito. Pulsa Recalcular mora.">
                    <template #cell-code="{ value }"><span class="font-bold text-slate-800">{{ value }}</span></template>
                    <template #cell-client="{ row }"><span class="font-semibold">{{ row.client?.full_name }}</span></template>
                    <template #cell-loan="{ row }"><span class="font-mono">{{ row.loan?.number }}</span></template>
                    <template #cell-current_days="{ value }"><span class="font-black text-rose-700">{{ value }}</span></template>
                    <template #cell-overdue_balance="{ row }"><span class="font-mono">C$ {{ money(row.overdue_balance) }}</span></template>
                    <template #cell-mora="{ row }"><span class="font-mono" :class="Number(row.loan?.delinquency_balance) > 0 ? 'font-semibold text-rose-700' : ''">C$ {{ money(row.loan?.delinquency_balance) }}</span></template>
                    <template #cell-started_on="{ value }">{{ formatDate(value) }}</template>
                    <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="value === 'active' ? 'bg-rose-50 text-rose-700' : value === 'resolved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ value === 'active' ? 'En mora' : value === 'resolved' ? 'Resuelto' : 'Cancelado' }}</span></template>
                    <template #cell-actions="{ row }"><a :href="`/cartera/${row.loan_id}`" class="font-bold text-indigo-600">Ver crédito</a></template>
                </DataTable>
                <PaginationLinks :links="cases.links" />
            </div>
        </div>
    </AppLayout>
</template>
