<script setup>
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
</script>
<template>
    <AppLayout title="Clientes" eyebrow="Directorio" description="Expedientes, cartera asignada y actividad del cliente.">
        <template #header-actions><a :href="endpoints.create" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Nuevo cliente</a></template>
        <div class="space-y-4">
            <ResourceToolbar v-model="filters.search" v-model:status="filters.status" :statuses="[{value:'active',label:'Activos'},{value:'inactive',label:'Inactivos'}]" placeholder="Nombre, código, cédula o teléfono…" @clear="clear">
                <select v-model="filters.seller" class="control sm:w-48"><option value="">Todos los vendedores</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.user?.name }}</option></select>
            </ResourceToolbar>
            <DataTable :columns="columns" :rows="clients.data" empty="No se encontraron clientes.">
                <template #cell-full_name="{ row }"><div><p class="font-bold text-slate-800">{{ row.full_name }}</p><p class="text-[10px] text-slate-400">{{ row.email || 'Sin correo' }}</p></div></template>
                <template #cell-status="{ value }"><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="value === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ value === 'active' ? 'Activo' : 'Inactivo' }}</span></template>
                <template #cell-actions="{ row }"><a :href="`/clientes/${row.id}`" class="font-bold text-indigo-600">Ver</a></template>
            </DataTable>
            <PaginationLinks :links="clients.links" />
        </div>
    </AppLayout>
</template>
