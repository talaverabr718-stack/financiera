<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import SettingsTabs from '../../components/ui/SettingsTabs.vue';
import { usePermissions } from '../../composables/usePermissions.js';

const props = defineProps({ branches: Array, tabs: Array, endpoints: Object });
const { canFull } = usePermissions();
const mayManage = computed(() => canFull('settings'));
const search = ref('');
const branchModal = ref(false);
const zoneModal = ref(false);
const editingBranch = ref(null);
const editingZone = ref(null);
const selectedBranch = ref(null);
const branchForm = useForm({ code: '', name: '', address: '', is_active: true });
const zoneForm = useForm({ code: '', name: '', is_active: true });

const visibleBranches = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    return term ? props.branches.filter(branch => `${branch.code} ${branch.name} ${branch.address || ''}`.toLocaleLowerCase('es').includes(term)) : props.branches;
});
const activeCount = computed(() => props.branches.filter(branch => branch.is_active).length);
const zoneCount = computed(() => props.branches.reduce((total, branch) => total + branch.zones.length, 0));
const endpoint = (template, branch, zone = null) => template.replace('__BRANCH__', branch.id).replace('__ZONE__', zone?.id || '');

const openBranch = (branch = null) => {
    editingBranch.value = branch;
    branchForm.clearErrors();
    branchForm.code = branch?.code || '';
    branchForm.name = branch?.name || '';
    branchForm.address = branch?.address || '';
    branchForm.is_active = branch?.is_active ?? true;
    branchModal.value = true;
};
const submitBranch = () => {
    const options = { preserveScroll: true, onSuccess: () => { branchModal.value = false; branchForm.reset(); } };
    editingBranch.value
        ? branchForm.put(endpoint(props.endpoints.update, editingBranch.value), options)
        : branchForm.post(props.endpoints.store, options);
};
const toggleBranch = branch => {
    const action = branch.is_active ? 'desactivar' : 'activar';
    if (!confirm(`¿Deseas ${action} la sucursal ${branch.name}? Sus registros históricos se conservarán.`)) return;
    router.patch(endpoint(props.endpoints.status, branch), { is_active: !branch.is_active }, { preserveScroll: true });
};
const openZone = (branch, zone = null) => {
    selectedBranch.value = branch;
    editingZone.value = zone;
    zoneForm.clearErrors();
    zoneForm.code = zone?.code || '';
    zoneForm.name = zone?.name || '';
    zoneForm.is_active = zone?.is_active ?? true;
    zoneModal.value = true;
};
const submitZone = () => {
    const options = { preserveScroll: true, onSuccess: () => { zoneModal.value = false; zoneForm.reset(); } };
    editingZone.value
        ? zoneForm.put(endpoint(props.endpoints.zoneUpdate, selectedBranch.value, editingZone.value), options)
        : zoneForm.post(endpoint(props.endpoints.zoneStore, selectedBranch.value), options);
};
const toggleZone = (branch, zone) => {
    router.patch(endpoint(props.endpoints.zoneStatus, branch, zone), { is_active: !zone.is_active }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Sucursales" eyebrow="Configuración" description="Sedes, zonas operativas y disponibilidad para nuevas asignaciones.">
        <template v-if="mayManage" #header-actions><button type="button" class="btn-primary" @click="openBranch()">+ Nueva sucursal</button></template>
        <SettingsTabs :tabs="tabs" />

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Sucursales activas</p><p class="mt-2 text-3xl font-black text-slate-900">{{ activeCount }}</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Zonas configuradas</p><p class="mt-2 text-3xl font-black text-indigo-600">{{ zoneCount }}</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Colaboradores asignados</p><p class="mt-2 text-3xl font-black text-emerald-600">{{ branches.reduce((total, branch) => total + branch.collaborators_count, 0) }}</p></article>
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center">
                <input v-model="search" type="search" class="control m-0 min-w-0 flex-1" placeholder="Buscar por código, nombre o dirección…">
                <button v-if="mayManage" type="button" class="btn-primary sm:hidden" @click="openBranch()">+ Nueva sucursal</button>
            </div>
            <div class="grid gap-4 p-4 xl:grid-cols-2">
                <article v-for="branch in visibleBranches" :key="branch.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-xl">🏢</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-black text-slate-900">{{ branch.name }}</h2><span class="badge" :class="branch.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ branch.is_active ? 'Activa' : 'Inactiva' }}</span></div>
                            <p class="mt-1 text-xs font-bold uppercase tracking-wide text-indigo-600">{{ branch.code }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ branch.address || 'Dirección pendiente' }}</p>
                        </div>
                        <div v-if="mayManage" class="flex gap-2"><button type="button" class="btn-secondary" @click="openBranch(branch)">Editar</button><button type="button" class="text-xs font-bold" :class="branch.is_active ? 'text-rose-600' : 'text-emerald-600'" @click="toggleBranch(branch)">{{ branch.is_active ? 'Desactivar' : 'Activar' }}</button></div>
                    </header>
                    <div class="p-5">
                        <div class="mb-3 flex items-center justify-between"><div><h3 class="font-bold text-slate-800">Zonas operativas</h3><p class="text-xs text-slate-400">{{ branch.zones_count }} zonas · {{ branch.collaborators_count }} colaboradores</p></div><button v-if="mayManage" type="button" class="btn-secondary" @click="openZone(branch)">+ Zona</button></div>
                        <div class="space-y-2">
                            <div v-for="zone in branch.zones" :key="zone.id" class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-3">
                                <span class="h-2.5 w-2.5 rounded-full" :class="zone.is_active ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-700">{{ zone.name }}</p><p class="text-[11px] font-semibold text-slate-400">{{ zone.code }}</p></div>
                                <button v-if="mayManage" type="button" class="text-xs font-bold text-indigo-600" @click="openZone(branch, zone)">Editar</button>
                                <button v-if="mayManage" type="button" class="text-xs font-bold" :class="zone.is_active ? 'text-rose-500' : 'text-emerald-600'" @click="toggleZone(branch, zone)">{{ zone.is_active ? 'Desactivar' : 'Activar' }}</button>
                            </div>
                            <p v-if="!branch.zones.length" class="rounded-xl border border-dashed border-slate-200 p-5 text-center text-sm text-slate-400">Esta sucursal todavía no tiene zonas.</p>
                        </div>
                    </div>
                </article>
                <p v-if="!visibleBranches.length" class="empty-state xl:col-span-2">No hay sucursales que coincidan con la búsqueda.</p>
            </div>
        </section>

        <BaseModal :open="branchModal" :title="editingBranch ? 'Editar sucursal' : 'Nueva sucursal'" description="Información institucional de la sede" @close="branchModal = false">
            <form id="branch-form" class="space-y-4" @submit.prevent="submitBranch">
                <div class="grid gap-4 sm:grid-cols-2"><label class="field-label">Código *<input v-model.trim="branchForm.code" class="control" placeholder="EST-01" maxlength="30" required></label><label class="field-label">Nombre *<input v-model.trim="branchForm.name" class="control" placeholder="Sucursal Estelí Centro" maxlength="150" required></label></div>
                <label class="field-label block">Dirección completa<textarea v-model.trim="branchForm.address" class="control min-h-24" maxlength="500" placeholder="Departamento, municipio, barrio y referencia"></textarea></label>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold"><input v-model="branchForm.is_active" type="checkbox"> Disponible para nuevas asignaciones</label>
                <div v-if="Object.keys(branchForm.errors).length" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"><p v-for="(error, key) in branchForm.errors" :key="key">{{ error }}</p></div>
            </form>
            <template #footer><button type="button" class="btn-secondary" @click="branchModal = false">Cancelar</button><button form="branch-form" class="btn-primary" :disabled="branchForm.processing">{{ branchForm.processing ? 'Guardando…' : 'Guardar sucursal' }}</button></template>
        </BaseModal>

        <BaseModal :open="zoneModal" :title="editingZone ? 'Editar zona' : 'Nueva zona operativa'" :description="selectedBranch?.name" @close="zoneModal = false">
            <form id="zone-form" class="space-y-4" @submit.prevent="submitZone">
                <div class="grid gap-4 sm:grid-cols-2"><label class="field-label">Código *<input v-model.trim="zoneForm.code" class="control" placeholder="EST-CEN" maxlength="30" required></label><label class="field-label">Nombre *<input v-model.trim="zoneForm.name" class="control" placeholder="Estelí Centro" maxlength="150" required></label></div>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold"><input v-model="zoneForm.is_active" type="checkbox"> Zona activa</label>
                <div v-if="Object.keys(zoneForm.errors).length" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"><p v-for="(error, key) in zoneForm.errors" :key="key">{{ error }}</p></div>
            </form>
            <template #footer><button type="button" class="btn-secondary" @click="zoneModal = false">Cancelar</button><button form="zone-form" class="btn-primary" :disabled="zoneForm.processing">{{ zoneForm.processing ? 'Guardando…' : 'Guardar zona' }}</button></template>
        </BaseModal>
    </AppLayout>
</template>
