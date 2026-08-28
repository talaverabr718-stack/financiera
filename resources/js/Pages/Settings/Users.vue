<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import SettingsTabs from '../../components/ui/SettingsTabs.vue';

const props = defineProps({ users: Array, collaborators: Array, endpoints: Object, tabs: Array, currentUserId: Number });
const modalOpen = ref(false);
const editingUser = ref(null);
const search = ref('');
const showSecrets = ref(false);
const modalStep = ref(1);
const form = useForm({ name: '', email: '', collaborator_id: '', password: '', password_confirmation: '', pin: '', pin_confirmation: '', remove_pin: false });
const visibleUsers = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    return term ? props.users.filter(user => `${user.name} ${user.email} ${user.seller_profile?.code || ''}`.toLocaleLowerCase('es').includes(term)) : props.users;
});
const availableCollaborators = computed(() => props.collaborators.filter(item => !item.user_id || item.user_id === editingUser.value?.id));
const selectedCollaborator = computed(() => availableCollaborators.value.find(item => String(item.id) === String(form.collaborator_id)));
watch(selectedCollaborator, collaborator => {
    if (!collaborator || editingUser.value) return;
    form.name = collaborator.display_name;
    form.email = collaborator.display_email || '';
});
const resetForm = () => form.reset('name', 'email', 'collaborator_id', 'password', 'password_confirmation', 'pin', 'pin_confirmation', 'remove_pin');
const openCreate = () => { editingUser.value = null; resetForm(); form.clearErrors(); showSecrets.value = false; modalStep.value = 1; modalOpen.value = true; };
const openEdit = user => {
    editingUser.value = user;
    resetForm();
    form.name = user.name;
    form.email = user.email;
    form.collaborator_id = user.seller_profile?.id || '';
    form.clearErrors();
    showSecrets.value = false;
    modalStep.value = 1;
    modalOpen.value = true;
};
const closeModal = () => { if (!form.processing) modalOpen.value = false; };
const endpoint = (template, user) => template.replace('__USER__', user.id);
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; resetForm(); }, onError: errors => { modalStep.value = errors.name || errors.email || errors.collaborator_id ? 1 : 2; } };
    editingUser.value ? form.put(endpoint(props.endpoints.update, editingUser.value), options) : form.post(props.endpoints.store, options);
};
const canContinue = computed(() => form.name.trim() && form.email.trim());
const nextStep = () => { if (canContinue.value) modalStep.value = 2; };
const toggleStatus = user => {
    const action = user.is_active ? 'desactivar' : 'activar';
    if (!confirm(`¿Deseas ${action} a ${user.name}?`)) return;
    router.patch(endpoint(props.endpoints.status, user), { is_active: !user.is_active }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Usuarios" eyebrow="Configuración" description="Cuentas de acceso, PIN y vínculo con colaboradores.">
        <SettingsTabs :tabs="tabs" />
        <section class="grid gap-3 sm:grid-cols-3">
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Usuarios activos</p><p class="mt-1 text-2xl font-black">{{ users.filter(user => user.is_active).length }}</p></article>
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Con PIN</p><p class="mt-1 text-2xl font-black text-indigo-600">{{ users.filter(user => user.has_pin).length }}</p></article>
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Colaboradores disponibles</p><p class="mt-1 text-2xl font-black text-emerald-600">{{ collaborators.filter(item => !item.user_id).length }}</p></article>
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center"><input v-model="search" type="search" class="control m-0 min-w-0 flex-1" placeholder="Buscar usuario…"><button type="button" class="btn-primary" @click="openCreate">Agregar usuario</button></div>
            <div class="divide-y">
                <article v-for="user in visibleUsers" :key="user.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600">{{ user.name.charAt(0) }}</span>
                    <div class="min-w-0 flex-1"><div class="flex items-center gap-2"><p class="truncate text-sm font-semibold">{{ user.name }}</p><span class="h-2 w-2 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-300'"></span></div><p class="truncate text-[11px] text-slate-400">{{ user.email }}</p></div>
                    <div class="flex flex-wrap items-center gap-2"><span v-if="user.has_pin" class="badge bg-indigo-50 text-indigo-700">PIN</span><span v-if="user.seller_profile" class="badge bg-blue-50 text-blue-700">{{ user.seller_profile.code }}</span><span v-else class="badge bg-slate-100 text-slate-500">Administrativo</span><button type="button" class="btn-secondary" @click="openEdit(user)">Editar</button><button type="button" class="text-xs font-semibold" :class="user.is_active ? 'text-rose-600' : 'text-emerald-600'" :disabled="user.id === currentUserId" @click="toggleStatus(user)">{{ user.is_active ? 'Desactivar' : 'Activar' }}</button></div>
                </article>
                <p v-if="!visibleUsers.length" class="empty-state">No hay usuarios que coincidan con la búsqueda.</p>
            </div>
        </section>

        <BaseModal :open="modalOpen" :title="editingUser ? 'Editar usuario' : 'Nuevo usuario'" :description="modalStep === 1 ? 'Identidad de la cuenta' : 'Métodos de acceso'" size="user-access-modal" compact @close="closeModal">
            <div class="mb-3 grid grid-cols-2 gap-1.5 rounded-xl bg-slate-100 p-1">
                <button type="button" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[10px] font-bold transition" :class="modalStep === 1 ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400'" @click="modalStep = 1"><span class="grid h-5 w-5 place-items-center rounded-full" :class="modalStep === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200'">1</span><span><strong class="block">Perfil</strong><small class="font-normal">Datos básicos</small></span></button>
                <button type="button" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[10px] font-bold transition" :class="modalStep === 2 ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400'" :disabled="!canContinue" @click="nextStep"><span class="grid h-5 w-5 place-items-center rounded-full" :class="modalStep === 2 ? 'bg-indigo-600 text-white' : 'bg-slate-200'">2</span><span><strong class="block">Acceso</strong><small class="font-normal">Clave y PIN</small></span></button>
            </div>
            <form id="system-user-form" @submit.prevent="submit">
                <section v-show="modalStep === 1" class="space-y-2.5">
                    <label class="field-label block">Tipo de cuenta<select v-model="form.collaborator_id" class="control"><option value="">Administrativa</option><option v-for="collaborator in availableCollaborators" :key="collaborator.id" :value="collaborator.id">{{ collaborator.display_name }} · {{ collaborator.code }}</option></select></label>
                    <label class="field-label block">Nombre completo *<input v-model="form.name" class="control" autocomplete="name" placeholder="Nombre del usuario" required></label>
                    <label class="field-label block">Correo electrónico *<input v-model="form.email" type="email" autocomplete="username" class="control" placeholder="usuario@financiera.com" required></label>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-[10px] leading-4 text-indigo-700">La cuenta podrá recibir permisos por módulo después de guardarla.</div>
                </section>
                <section v-show="modalStep === 2" class="space-y-2.5">
                    <div class="rounded-xl border bg-slate-50 p-2.5"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-xs font-black text-white">{{ form.name.charAt(0) || 'U' }}</span><div class="min-w-0"><p class="truncate text-xs font-semibold">{{ form.name }}</p><p class="truncate text-[10px] text-slate-400">{{ form.email }}</p></div></div></div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="field-label">{{ editingUser ? 'Nueva contraseña' : 'Contraseña *' }}<input v-model="form.password" :type="showSecrets ? 'text' : 'password'" autocomplete="new-password" class="control" minlength="8" :required="!editingUser"></label>
                        <label class="field-label">Confirmar<input v-model="form.password_confirmation" :type="showSecrets ? 'text' : 'password'" autocomplete="new-password" class="control" minlength="8" :required="!editingUser"></label>
                        <label class="field-label">PIN de 4 dígitos<input v-model="form.pin" :type="showSecrets ? 'text' : 'password'" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" class="control text-center tracking-[.35em]" placeholder="••••"></label>
                        <label class="field-label">Confirmar PIN<input v-model="form.pin_confirmation" :type="showSecrets ? 'text' : 'password'" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" class="control text-center tracking-[.35em]" placeholder="••••"></label>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2"><label class="flex items-center gap-2 text-[10px] text-slate-500"><input v-model="showSecrets" type="checkbox"> Mostrar claves</label><label v-if="editingUser?.has_pin" class="flex items-center gap-2 text-[10px] text-rose-600"><input v-model="form.remove_pin" type="checkbox"> Quitar PIN</label></div>
                    <p v-if="editingUser" class="text-[10px] text-slate-400">Los campos vacíos conservan las credenciales actuales.</p>
                </section>
                <div v-if="Object.keys(form.errors).length" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-[10px] text-rose-700"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
            </form>
            <template #footer><div class="flex items-center justify-between gap-2"><button v-if="modalStep === 2" type="button" class="btn-secondary" @click="modalStep = 1">Atrás</button><button v-else type="button" class="text-[11px] font-semibold text-slate-500" @click="closeModal">Cancelar</button><button v-if="modalStep === 1" type="button" class="btn-primary ml-auto" :disabled="!canContinue" @click="nextStep">Continuar →</button><button v-else form="system-user-form" class="btn-primary ml-auto" :disabled="form.processing">{{ form.processing ? 'Guardando…' : (editingUser ? 'Guardar cambios' : 'Crear usuario') }}</button></div></template>
        </BaseModal>
    </AppLayout>
</template>
