<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import SettingsTabs from '../../components/ui/SettingsTabs.vue';

const props = defineProps({ modules: Array, roles: Array, users: Array, endpoints: Object, tabs: Array, canManage: Boolean });
const mode = ref('roles');
const moduleSearch = ref('');
const selectedRoleId = ref(props.roles[0]?.id || '');
const selectedUserId = ref(props.users[0]?.id || '');
const roleModalOpen = ref(false);
const roleModalStep = ref(1);
const rolePermissionSearch = ref('');
const emptyPermissions = () => Object.fromEntries(props.modules.map(module => [
    String(module.id),
    { view: false, manage: false, full: false },
]));
const roleCreateForm = useForm({ name: '', description: '', permissions: emptyPermissions() });
const roleForm = useForm({ name: '', description: '', permissions: {} });
const userForm = useForm({ system_role_id: '', overrides: {} });

const selectedRole = computed(() => props.roles.find(role => String(role.id) === String(selectedRoleId.value)));
const selectedUser = computed(() => props.users.find(user => String(user.id) === String(selectedUserId.value)));
const filteredModules = computed(() => {
    const term = moduleSearch.value.trim().toLocaleLowerCase('es');
    return term ? props.modules.filter(module => `${module.name} ${module.description || ''}`.toLocaleLowerCase('es').includes(term)) : props.modules;
});
const activeRole = computed(() => props.roles.find(role => String(role.id) === String(userForm.system_role_id)));
const canContinueRole = computed(() => roleCreateForm.name.trim().length > 0);
const filteredRoleCreateModules = computed(() => {
    const term = rolePermissionSearch.value.trim().toLocaleLowerCase('es');
    return term ? props.modules.filter(module => `${module.name} ${module.description || ''}`.toLocaleLowerCase('es').includes(term)) : props.modules;
});
const roleCreateSummary = computed(() => {
    const values = Object.values(roleCreateForm.permissions);
    return {
        view: values.filter(permission => permission.view).length,
        manage: values.filter(permission => permission.manage).length,
        full: values.filter(permission => permission.full).length,
    };
});
const endpoint = (template, token, value) => template.replace(token, value);

watch(selectedRole, role => {
    if (!role) return;
    roleForm.name = role.name;
    roleForm.description = role.description || '';
    roleForm.permissions = JSON.parse(JSON.stringify(role.permissions));
    roleForm.clearErrors();
}, { immediate: true });

watch(selectedUser, user => {
    if (!user) return;
    userForm.system_role_id = user.system_role_id || props.roles[0]?.id || '';
    userForm.overrides = Object.fromEntries(props.modules.map(module => {
        const permission = user.permissions[String(module.id)] || {};
        return [String(module.id), {
            view: permission.view_override ?? null,
            manage: permission.manage_override ?? null,
            full: permission.full_override ?? null,
        }];
    }));
    userForm.clearErrors();
}, { immediate: true });

const setRolePermission = (module, ability) => {
    if (!props.canManage) return;
    const item = roleForm.permissions[String(module.id)];
    item[ability] = !item[ability];
    if (ability === 'full' && item.full) {
        item.view = true;
        item.manage = true;
    }
    if (ability === 'manage' && item.manage) item.view = true;
    if (ability === 'manage' && !item.manage) item.full = false;
    if (ability === 'view' && !item.view) {
        item.manage = false;
        item.full = false;
    }
};
const accessLevels = [
    { key: 'blocked', label: 'Bloqueado', hint: 'Sin acceso' },
    { key: 'view', label: 'Solo vista', hint: 'Puede consultar' },
    { key: 'edit', label: 'Editar', hint: 'Crea y modifica' },
    { key: 'full', label: 'Acceso total', hint: 'Acciones críticas' },
];
const setAccessLevel = (module, level) => {
    if (!props.canManage) return;
    userForm.overrides[String(module.id)] = {
        inherit: { view: null, manage: null, full: null },
        blocked: { view: false, manage: false, full: false },
        view: { view: true, manage: false, full: false },
        edit: { view: true, manage: true, full: false },
        full: { view: true, manage: true, full: true },
    }[level];
};
const rolePermission = (module, ability) => activeRole.value?.permissions?.[String(module.id)]?.[ability] === true;
const effective = (module, ability) => {
    const item = userForm.overrides[String(module.id)] || {};
    const view = item.view === null ? rolePermission(module, 'view') : item.view === true;
    const manage = item.manage === null ? rolePermission(module, 'manage') : item.manage === true;
    const full = item.full === null ? rolePermission(module, 'full') : item.full === true;
    if (ability === 'view') return view;
    if (ability === 'manage') return view && manage;
    return view && manage && full;
};
const accessLevel = (view, manage, full) => full && manage && view ? 'full' : manage && view ? 'edit' : view ? 'view' : 'blocked';
const inheritedLevel = module => accessLevel(
    rolePermission(module, 'view'),
    rolePermission(module, 'manage'),
    rolePermission(module, 'full'),
);
const effectiveLevel = module => accessLevel(
    effective(module, 'view'),
    effective(module, 'manage'),
    effective(module, 'full'),
);
const overrideLevel = module => {
    const item = userForm.overrides[String(module.id)] || {};
    return item.view === null && item.manage === null && item.full === null ? 'inherit' : effectiveLevel(module);
};
const accessLabel = level => accessLevels.find(item => item.key === level)?.label || 'Según rol';
const saveRole = () => roleForm.put(endpoint(props.endpoints.roleUpdate, '__ROLE__', selectedRoleId.value), { preserveScroll: true });
const saveUser = () => userForm.put(endpoint(props.endpoints.userUpdate, '__USER__', selectedUserId.value), { preserveScroll: true });
const openRoleModal = () => {
    roleCreateForm.reset();
    roleCreateForm.permissions = emptyPermissions();
    roleCreateForm.clearErrors();
    rolePermissionSearch.value = '';
    roleModalStep.value = 1;
    roleModalOpen.value = true;
};
const closeRoleModal = () => {
    if (!roleCreateForm.processing) roleModalOpen.value = false;
};
const nextRoleStep = () => {
    if (canContinueRole.value) roleModalStep.value = 2;
};
const setRoleCreatePermission = (module, ability) => {
    const item = roleCreateForm.permissions[String(module.id)];
    item[ability] = !item[ability];
    if (ability === 'full' && item.full) {
        item.view = true;
        item.manage = true;
    }
    if (ability === 'manage' && item.manage) item.view = true;
    if (ability === 'manage' && !item.manage) item.full = false;
    if (ability === 'view' && !item.view) {
        item.manage = false;
        item.full = false;
    }
};
const createRole = () => roleCreateForm.post(props.endpoints.roleStore, {
    preserveScroll: true,
    onSuccess: () => {
        roleModalOpen.value = false;
        roleCreateForm.reset();
        roleCreateForm.permissions = emptyPermissions();
        roleModalStep.value = 1;
    },
    onError: errors => {
        if (errors.name || errors.description) roleModalStep.value = 1;
    },
});
</script>

<template>
    <AppLayout title="Permisos" eyebrow="Configuración" description="Roles predeterminados y excepciones individuales con validación efectiva en todo el sistema.">
        <SettingsTabs :tabs="tabs" />

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Roles activos</p><p class="mt-1 text-2xl font-black">{{ roles.length }}</p></article>
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Usuarios</p><p class="mt-1 text-2xl font-black text-indigo-600">{{ users.length }}</p></article>
            <article class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Módulos controlados</p><p class="mt-1 text-2xl font-black text-emerald-600">{{ modules.length }}</p></article>
        </section>

        <section class="card mt-4 overflow-hidden">
            <header class="flex flex-col gap-3 border-b p-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex w-fit rounded-xl bg-slate-100 p-1">
                    <button type="button" class="rounded-lg px-4 py-2 text-xs font-bold transition" :class="mode === 'roles' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500'" @click="mode = 'roles'">Permisos por rol</button>
                    <button type="button" class="rounded-lg px-4 py-2 text-xs font-bold transition" :class="mode === 'users' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500'" @click="mode = 'users'">Excepciones por usuario</button>
                </div>
                <label class="relative min-w-0 sm:w-72"><span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">⌕</span><input v-model="moduleSearch" type="search" class="control m-0 pl-9" placeholder="Buscar módulo…"></label>
            </header>

            <div v-if="mode === 'roles'" class="p-3 sm:p-4">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row">
                    <select v-model="selectedRoleId" class="control m-0 min-w-0 flex-1"><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }} · {{ role.users_count }} usuario(s)</option></select>
                    <button v-if="canManage" type="button" class="btn-secondary" @click="openRoleModal">Nuevo rol</button>
                </div>
                <form v-if="selectedRole" class="space-y-4" @submit.prevent="saveRole">
                    <section class="grid gap-3 rounded-2xl border bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                        <label class="field-label">Nombre del rol<input v-model="roleForm.name" class="control" :disabled="!canManage" required></label>
                        <label class="field-label">Descripción<input v-model="roleForm.description" class="control" :disabled="!canManage"></label>
                    </section>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="module in filteredModules" :key="module.id" class="rounded-2xl border bg-white p-3 shadow-sm" :class="!module.is_enabled && 'opacity-60'">
                            <div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-bold text-slate-800">{{ module.name }}</h3><p class="mt-1 text-[10px] leading-4 text-slate-400">{{ module.description }}</p></div><span v-if="!module.is_enabled" class="badge bg-slate-100 text-slate-500">Inactivo</span></div>
                            <div class="mt-3 grid grid-cols-3 gap-1.5">
                                <button type="button" class="permission-choice" :class="roleForm.permissions[String(module.id)]?.view && 'is-view'" :disabled="!canManage" @click="setRolePermission(module, 'view')"><span>Vista</span><b>{{ roleForm.permissions[String(module.id)]?.view ? 'Sí' : 'No' }}</b></button>
                                <button type="button" class="permission-choice" :class="roleForm.permissions[String(module.id)]?.manage && 'is-manage'" :disabled="!canManage" @click="setRolePermission(module, 'manage')"><span>Editar</span><b>{{ roleForm.permissions[String(module.id)]?.manage ? 'Sí' : 'No' }}</b></button>
                                <button type="button" class="permission-choice" :class="roleForm.permissions[String(module.id)]?.full && 'is-full'" :disabled="!canManage" @click="setRolePermission(module, 'full')"><span>Total</span><b>{{ roleForm.permissions[String(module.id)]?.full ? 'Sí' : 'No' }}</b></button>
                            </div>
                        </article>
                    </div>
                    <div class="sticky bottom-3 z-20 flex justify-end rounded-2xl border bg-white/95 p-3 shadow-xl backdrop-blur"><span v-if="roleForm.recentlySuccessful" class="mr-auto self-center text-xs font-bold text-emerald-600">Rol actualizado</span><button v-if="canManage" class="btn-primary" :disabled="roleForm.processing">{{ roleForm.processing ? 'Guardando…' : 'Guardar rol' }}</button></div>
                </form>
            </div>

            <div v-else class="p-3 sm:p-4">
                <div class="mb-4 grid gap-2 md:grid-cols-2"><label class="field-label">Usuario<select v-model="selectedUserId" class="control"><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option></select></label><label class="field-label">Rol asignado<select v-model="userForm.system_role_id" class="control" :disabled="!canManage"><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></label></div>
                <form v-if="selectedUser" class="space-y-4" @submit.prevent="saveUser">
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-[11px] leading-5 text-indigo-700"><strong>Cómo funciona:</strong> Elige Bloqueado, Solo vista, Editar o Acceso total para cada módulo. “Usar permiso del rol” elimina la excepción individual.</div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="module in filteredModules" :key="module.id" class="rounded-2xl border bg-white p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0"><h3 class="truncate text-sm font-bold text-slate-800">{{ module.name }}</h3><p class="mt-1 text-[10px] text-slate-400">Rol: {{ accessLabel(inheritedLevel(module)) }}</p></div>
                                <span class="badge shrink-0" :class="effectiveLevel(module) === 'full' ? 'bg-violet-50 text-violet-700' : effectiveLevel(module) === 'edit' ? 'bg-emerald-50 text-emerald-700' : effectiveLevel(module) === 'view' ? 'bg-indigo-50 text-indigo-700' : 'bg-rose-50 text-rose-700'">{{ accessLabel(effectiveLevel(module)) }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-1.5">
                                <button v-for="level in accessLevels" :key="level.key" type="button" class="override-choice" :class="[`level-${level.key}`, overrideLevel(module) === level.key && 'is-selected']" :disabled="!canManage" @click="setAccessLevel(module, level.key)"><strong>{{ level.label }}</strong><small>{{ level.hint }}</small></button>
                            </div>
                            <button type="button" class="mt-2 w-full rounded-lg py-1.5 text-[10px] font-bold transition" :class="overrideLevel(module) === 'inherit' ? 'bg-slate-100 text-slate-500' : 'text-indigo-600 hover:bg-indigo-50'" :disabled="!canManage || overrideLevel(module) === 'inherit'" @click="setAccessLevel(module, 'inherit')">{{ overrideLevel(module) === 'inherit' ? 'Usando permiso del rol' : 'Usar permiso del rol' }}</button>
                        </article>
                    </div>
                    <div class="sticky bottom-3 z-20 flex justify-end rounded-2xl border bg-white/95 p-3 shadow-xl backdrop-blur"><span v-if="userForm.recentlySuccessful" class="mr-auto self-center text-xs font-bold text-emerald-600">Permisos actualizados</span><button v-if="canManage" class="btn-primary" :disabled="userForm.processing">{{ userForm.processing ? 'Guardando…' : 'Guardar excepciones' }}</button></div>
                </form>
            </div>
        </section>

        <BaseModal :open="roleModalOpen" title="Nuevo rol" :description="roleModalStep === 1 ? 'Identidad del rol' : 'Permisos predeterminados'" size="user-access-modal" compact @close="closeRoleModal">
            <div class="mb-3 grid grid-cols-2 gap-1.5 rounded-xl bg-slate-100 p-1">
                <button type="button" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[10px] font-bold transition" :class="roleModalStep === 1 ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400'" @click="roleModalStep = 1">
                    <span class="grid h-5 w-5 place-items-center rounded-full" :class="roleModalStep === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200'">1</span>
                    <span><strong class="block">Perfil</strong><small class="font-normal">Datos básicos</small></span>
                </button>
                <button type="button" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[10px] font-bold transition" :class="roleModalStep === 2 ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400'" :disabled="!canContinueRole" @click="nextRoleStep">
                    <span class="grid h-5 w-5 place-items-center rounded-full" :class="roleModalStep === 2 ? 'bg-indigo-600 text-white' : 'bg-slate-200'">2</span>
                    <span><strong class="block">Permisos</strong><small class="font-normal">Acceso inicial</small></span>
                </button>
            </div>

            <form id="role-create-form" @submit.prevent="createRole">
                <section v-show="roleModalStep === 1" class="space-y-2.5">
                    <label class="field-label block">Nombre del rol *<input v-model="roleCreateForm.name" class="control" maxlength="120" autocomplete="off" placeholder="Ej. Analista de crédito" required></label>
                    <label class="field-label block">Descripción<textarea v-model="roleCreateForm.description" rows="2" maxlength="255" class="control" placeholder="Responsabilidad principal del rol"></textarea></label>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-[10px] leading-4 text-indigo-700">Los permisos seleccionados serán la base para todos los usuarios que tengan este rol. Después podrás agregar excepciones individuales.</div>
                </section>

                <section v-show="roleModalStep === 2" class="space-y-2.5">
                    <div class="rounded-xl border bg-slate-50 p-2.5">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-indigo-600 text-xs font-black text-white">{{ roleCreateForm.name.charAt(0).toUpperCase() || 'R' }}</span>
                            <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{{ roleCreateForm.name }}</p><p class="text-[10px] text-slate-400">{{ roleCreateSummary.view }} con acceso · {{ roleCreateSummary.manage }} editan · {{ roleCreateSummary.full }} total</p></div>
                        </div>
                    </div>
                    <input v-model="rolePermissionSearch" type="search" class="control m-0" placeholder="Buscar módulo…">
                    <div class="max-h-[min(46dvh,23rem)] space-y-2 overflow-y-auto pr-1">
                        <article v-for="module in filteredRoleCreateModules" :key="module.id" class="rounded-xl border bg-white p-2.5">
                            <div class="mb-2 min-w-0"><p class="truncate text-[11px] font-bold text-slate-700">{{ module.name }}</p><p class="truncate text-[9px] text-slate-400">{{ module.description }}</p></div>
                            <div class="grid grid-cols-3 gap-1.5">
                                <button type="button" class="permission-choice" :class="roleCreateForm.permissions[String(module.id)]?.view && 'is-view'" @click="setRoleCreatePermission(module, 'view')"><span>Vista</span><b>{{ roleCreateForm.permissions[String(module.id)]?.view ? 'Sí' : 'No' }}</b></button>
                                <button type="button" class="permission-choice" :class="roleCreateForm.permissions[String(module.id)]?.manage && 'is-manage'" @click="setRoleCreatePermission(module, 'manage')"><span>Editar</span><b>{{ roleCreateForm.permissions[String(module.id)]?.manage ? 'Sí' : 'No' }}</b></button>
                                <button type="button" class="permission-choice" :class="roleCreateForm.permissions[String(module.id)]?.full && 'is-full'" @click="setRoleCreatePermission(module, 'full')"><span>Total</span><b>{{ roleCreateForm.permissions[String(module.id)]?.full ? 'Sí' : 'No' }}</b></button>
                            </div>
                        </article>
                        <p v-if="!filteredRoleCreateModules.length" class="py-5 text-center text-[10px] text-slate-400">No hay módulos que coincidan.</p>
                    </div>
                </section>

                <div v-if="Object.keys(roleCreateForm.errors).length" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-[10px] text-rose-700"><p v-for="(error, key) in roleCreateForm.errors" :key="key">{{ error }}</p></div>
            </form>

            <template #footer>
                <div class="flex items-center justify-between gap-2">
                    <button v-if="roleModalStep === 2" type="button" class="btn-secondary" @click="roleModalStep = 1">Atrás</button>
                    <button v-else type="button" class="text-[11px] font-semibold text-slate-500" @click="closeRoleModal">Cancelar</button>
                    <button v-if="roleModalStep === 1" type="button" class="btn-primary ml-auto" :disabled="!canContinueRole" @click="nextRoleStep">Continuar →</button>
                    <button v-else form="role-create-form" class="btn-primary ml-auto" :disabled="roleCreateForm.processing">{{ roleCreateForm.processing ? 'Creando…' : 'Crear rol' }}</button>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>

<style scoped>
.permission-choice { display:flex; align-items:center; justify-content:space-between; min-height:2.4rem; border:1px solid #e2e8f0; border-radius:.75rem; padding:.5rem .65rem; color:#64748b; font-size:.62rem; font-weight:800; transition:.15s ease; }
.permission-choice b { font-size:.58rem; }
.permission-choice.is-view { border-color:#a5b4fc; background:#eef2ff; color:#4338ca; }
.permission-choice.is-manage { border-color:#6ee7b7; background:#ecfdf5; color:#047857; }
.permission-choice.is-full { border-color:#c4b5fd; background:#f5f3ff; color:#6d28d9; }
.override-choice { display:flex; min-height:2.7rem; flex-direction:column; align-items:flex-start; justify-content:center; border:1px solid #e2e8f0; border-radius:.65rem; background:white; padding:.4rem .55rem; color:#64748b; text-align:left; transition:.15s ease; }
.override-choice strong { font-size:.58rem; }
.override-choice small { margin-top:.08rem; font-size:.48rem; font-weight:600; color:#94a3b8; }
.override-choice.is-selected.level-blocked { border-color:#fda4af; background:#fff1f2; color:#be123c; box-shadow:0 0 0 1px rgba(225,29,72,.06); }
.override-choice.is-selected.level-view { border-color:#a5b4fc; background:#eef2ff; color:#4338ca; box-shadow:0 0 0 1px rgba(79,70,229,.06); }
.override-choice.is-selected.level-edit { border-color:#6ee7b7; background:#ecfdf5; color:#047857; box-shadow:0 0 0 1px rgba(5,150,105,.06); }
.override-choice.is-selected.level-full { border-color:#c4b5fd; background:#f5f3ff; color:#6d28d9; box-shadow:0 0 0 1px rgba(109,40,217,.06); }
button:disabled { cursor:not-allowed; opacity:.55; }
</style>
