<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ collaborator: Object, branches: Array, editing: Boolean, endpoints: Object });
const form = useForm({
    name: props.collaborator.full_name || props.collaborator.user?.name || '',
    email: props.collaborator.email || props.collaborator.user?.email || '',
    identity_number: props.collaborator.identity_number || '',
    phone: props.collaborator.phone || '',
    branch_id: props.collaborator.branch_id || '',
});
const submit = () => form.transform(data => props.editing ? { ...data, _method: 'put' } : data).post(props.endpoints.save);
</script>

<template>
    <AppLayout :title="editing ? 'Editar colaborador' : 'Nuevo colaborador'" eyebrow="Equipo" description="Registra los datos personales y la sucursal. El acceso al sistema se administra desde Configuración.">
        <template #header-actions><a :href="endpoints.index" class="btn-secondary">Volver</a></template>
        <form class="mx-auto max-w-4xl space-y-4" @submit.prevent="submit">
            <section class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="font-semibold">Datos personales</h2><p class="mt-1 text-[11px] text-slate-400">Información de identificación y contacto del colaborador.</p></div>
                    <span v-if="editing" class="badge bg-slate-100 text-slate-600">{{ collaborator.code }}</span>
                    <span v-else class="badge bg-blue-50 text-blue-700">Código automático</span>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="field-label md:col-span-2">Nombre completo *<input v-model="form.name" class="control" autocomplete="name" required></label>
                    <label class="field-label">Cédula<input v-model="form.identity_number" class="control" autocomplete="off"></label>
                    <label class="field-label">Teléfono<input v-model="form.phone" class="control" autocomplete="tel"></label>
                    <label class="field-label md:col-span-2">Correo electrónico<input v-model="form.email" type="email" class="control" autocomplete="email"></label>
                </div>
            </section>
            <section class="card p-5 sm:p-6">
                <h2 class="font-semibold">Sucursal</h2>
                <p class="mt-1 text-[11px] text-slate-400">Selecciona dónde estará asignado el colaborador.</p>
                <label class="field-label mt-5 block">Sucursal *
                    <select v-model="form.branch_id" class="control" required>
                        <option value="">Seleccionar sucursal</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                </label>
            </section>
            <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700">{{ Object.values(form.errors).join(' · ') }}</div>
            <div class="flex justify-end gap-2"><a :href="endpoints.index" class="btn-secondary">Cancelar</a><button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar colaborador' }}</button></div>
        </form>
    </AppLayout>
</template>
