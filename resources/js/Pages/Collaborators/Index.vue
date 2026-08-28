<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../components/ui/DataTable.vue';
import PaginationLinks from '../../components/ui/PaginationLinks.vue';
import ResourceToolbar from '../../components/ui/ResourceToolbar.vue';
import { useResourceFilters } from '../../composables/useResourceFilters';

const props = defineProps({ collaborators: Object, filters: Object, endpoints: Object });
const { filters, clear } = useResourceFilters({ search: props.filters.search || '', status: props.filters.status || '' }, props.endpoints.index);
const columns = [{ key: 'code', label: 'Código' }, { key: 'display_name', label: 'Colaborador' }, { key: 'branch', label: 'Sucursal' }, { key: 'active_clients_count', label: 'Clientes activos' }, { key: 'status', label: 'Estado' }, { key: 'actions', label: '' }];
</script>

<template>
    <AppLayout title="Colaboradores" eyebrow="Equipo" description="Datos personales, sucursal y cartera asignada.">
        <template #header-actions><a :href="endpoints.create" class="btn-primary">Nuevo colaborador</a></template>
        <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{ value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }, { value: 'suspended', label: 'Suspendidos' }]" @clear="clear" />
        <DataTable class="mt-4" :columns="columns" :rows="collaborators.data">
            <template #cell-display_name="{ row }"><div><p class="font-semibold">{{ row.display_name }}</p><p class="text-[10px] text-slate-400">{{ row.display_email || 'Sin correo' }}</p></div></template>
            <template #cell-branch="{ row }">{{ row.branch?.name || 'Sin sucursal' }}</template>
            <template #cell-status="{ value }"><span class="badge" :class="value === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ value }}</span></template>
            <template #cell-actions="{ row }"><a :href="`/colaboradores/${row.id}`" class="font-semibold text-blue-700">Ver</a></template>
        </DataTable>
        <PaginationLinks :links="collaborators.links" />
    </AppLayout>
</template>
