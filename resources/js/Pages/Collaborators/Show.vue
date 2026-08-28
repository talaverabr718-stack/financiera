<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ collaborator: Object, endpoints: Object });
const date = value => value ? new Intl.DateTimeFormat('es-NI').format(new Date(value)) : '—';
const inactivate = () => { if (confirm('¿Inactivar este colaborador? Su historial se conservará.')) router.delete(props.endpoints.destroy); };
</script>

<template>
    <AppLayout :title="collaborator.display_name" eyebrow="Colaboradores" :description="`${collaborator.code} · ${collaborator.status}`">
        <template #header-actions><div class="flex gap-2"><button class="btn-danger" @click="inactivate">Inactivar</button><a :href="endpoints.edit" class="btn-primary">Editar</a></div></template>
        <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <section class="card p-5"><h2 class="font-semibold">Cartera asignada</h2><div class="mt-4 divide-y"><a v-for="assignment in collaborator.portfolio_assignments" :key="assignment.id" :href="`/clientes/${assignment.client.id}`" class="flex justify-between py-3 text-xs"><span class="font-semibold">{{ assignment.client.full_name }}</span><span class="text-slate-400">{{ assignment.ended_at ? 'Finalizada' : 'Activa' }}</span></a><p v-if="!collaborator.portfolio_assignments.length" class="empty-state">Sin clientes asignados.</p></div></section>
                <section class="card p-5"><h2 class="font-semibold">Rutas recientes</h2><div class="mt-4 divide-y"><div v-for="route in collaborator.collection_routes" :key="route.id" class="flex justify-between py-3 text-xs"><span>{{ route.name }}</span><span class="text-slate-400">{{ date(route.scheduled_date) }}</span></div><p v-if="!collaborator.collection_routes.length" class="empty-state">Sin rutas registradas.</p></div></section>
            </div>
            <aside class="space-y-4">
                <section class="analytics-panel p-5 text-white"><p class="dark-kicker">Sucursal asignada</p><p class="mt-3 text-sm">{{ collaborator.branch?.name || 'Sin sucursal' }}</p></section>
                <section class="card p-5"><p class="text-xs text-slate-400">Datos personales</p><p class="mt-2 font-semibold">{{ collaborator.display_email || 'Sin correo' }}</p><p class="mt-1 text-xs">{{ collaborator.phone || 'Sin teléfono' }}</p><p class="mt-1 text-xs">{{ collaborator.identity_number || 'Sin cédula' }}</p></section>
                <section class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-[11px] text-blue-800">El acceso, usuario y contraseña se administran por separado desde Configuración.</section>
            </aside>
        </div>
    </AppLayout>
</template>
