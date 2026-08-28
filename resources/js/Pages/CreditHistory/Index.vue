<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import MesaModuleBoard from '../../components/mesa/MesaModuleBoard.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({ clients: Object, board: { type: Object, default: () => ({}) }, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search ?? '', status: props.filters.status ?? '' }, props.endpoints.index);
const columns = [
    { key: 'code', label: 'Código' },
    { key: 'full_name', label: 'Cliente' },
    { key: 'loans_count', label: 'Créditos' },
    { key: 'latest_loan', label: 'Último crédito' },
    { key: 'can_originate_new_credit', label: 'Nuevo crédito' },
    { key: 'actions', label: '' },
];
const statusLabel = value => ({ active: 'Vigente', delinquent: 'En mora', paid: 'Cancelado' }[value] ?? value);
</script>
<template>
    <AppLayout hide-header title="Historial crediticio">
        <div class="mesa module-mesa">
            <MesaModuleBoard
                :board="board"
                kicker="Trayectoria"
                pie-label="Disponibilidad"
                pie-center="Clientes"
                trade-label="Colocaciones"
                :trade-caption="`${board.growth?.added || 0} desembolsos este mes`"
                fill-id="history-trade-fill"
            />
            <div class="space-y-4">
                <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{value:'open',label:'Con crédito vigente'},{value:'unlocked',label:'Nuevo crédito desbloqueado'}]" placeholder="Nombre, código, cédula o teléfono…" @clear="clear" />
                <DataTable :columns="columns" :rows="clients.data" empty="No hay clientes para mostrar.">
                    <template #cell-full_name="{ row }">
                        <div>
                            <p class="font-bold text-slate-800">{{ row.full_name }}</p>
                            <p class="text-[10px] text-slate-400">{{ row.identity_number || 'Sin cédula' }}</p>
                        </div>
                    </template>
                    <template #cell-loans_count="{ value }"><span class="font-black">{{ value }}</span></template>
                    <template #cell-latest_loan="{ row }">
                        <span v-if="row.latest_loan" class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="row.latest_loan.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : row.latest_loan.status === 'delinquent' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700'">{{ row.latest_loan.number }} · {{ statusLabel(row.latest_loan.status) }}</span>
                        <span v-else class="text-xs text-slate-400">Sin créditos</span>
                    </template>
                    <template #cell-can_originate_new_credit="{ value }">
                        <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ value ? 'Desbloqueado' : 'Bloqueado' }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <Link :href="`/historial-crediticio/${row.id}`" class="font-bold text-indigo-600">Ver historial</Link>
                    </template>
                </DataTable>
                <PaginationLinks :links="clients.links" />
            </div>
        </div>
    </AppLayout>
</template>
