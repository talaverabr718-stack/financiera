<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ users: Array, modules: Array, permissions: Object, endpoints: Object, tabs: Array });
const form = useForm({ permissions: JSON.parse(JSON.stringify(props.permissions ?? {})) });
const search = ref('');
const moduleFilter = ref('');

const visibleUsers = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    if (!term) return props.users;
    return props.users.filter(user => `${user.name} ${user.email}`.toLocaleLowerCase('es').includes(term));
});
const visibleModules = computed(() => moduleFilter.value ? props.modules.filter(module => String(module.id) === moduleFilter.value) : props.modules);
const enabledCount = computed(() => Object.values(form.permissions).reduce((total, modules) => total + Object.values(modules).filter(permission => permission.view).length, 0));
const managedCount = computed(() => Object.values(form.permissions).reduce((total, modules) => total + Object.values(modules).filter(permission => permission.manage).length, 0));

const permission = (user, module) => form.permissions[String(user.id)][String(module.id)];
const toggleView = (user, module) => {
    const item = permission(user, module);
    item.view = !item.view;
    if (!item.view) item.manage = false;
};
const toggleManage = (user, module) => {
    const item = permission(user, module);
    item.manage = !item.manage;
    if (item.manage) item.view = true;
};
const grantUser = user => props.modules.forEach(module => { permission(user, module).view = true; });
const clearUser = user => props.modules.forEach(module => Object.assign(permission(user, module), { view: false, manage: false }));
const submit = () => form.put(props.endpoints.update, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Permisos" eyebrow="Configuración" description="Control de consulta y administración por usuario y módulo.">
        <nav class="mb-4 flex gap-1 overflow-x-auto rounded-xl border bg-white p-1">
            <a v-for="tab in tabs" :key="tab.url" :href="tab.url" class="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition" :class="tab.active ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-50'">{{ tab.label }}</a>
        </nav>

        <form class="space-y-4" @submit.prevent="submit">
            <section class="grid gap-3 sm:grid-cols-3">
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Usuarios</p><p class="mt-1 text-2xl font-black">{{ users.length }}</p></article>
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Accesos de consulta</p><p class="mt-1 text-2xl font-black text-indigo-600">{{ enabledCount }}</p></article>
                <article class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Accesos administrativos</p><p class="mt-1 text-2xl font-black text-emerald-600">{{ managedCount }}</p></article>
            </section>

            <section class="rounded-2xl border bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative min-w-0 flex-1"><span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">⌕</span><input v-model="search" type="search" class="h-10 w-full rounded-xl border bg-slate-50 pl-9 pr-3 text-xs outline-none focus:border-indigo-400 focus:bg-white" placeholder="Buscar usuario o correo…"></label>
                    <select v-model="moduleFilter" class="h-10 rounded-xl border bg-white px-3 text-xs text-slate-600 sm:w-52"><option value="">Todos los módulos</option><option v-for="module in modules" :key="module.id" :value="String(module.id)">{{ module.name }}</option></select>
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 text-[10px] text-slate-500"><span><i class="inline-block h-2 w-2 rounded-full bg-indigo-500"></i> Ver</span><span><i class="inline-block h-2 w-2 rounded-full bg-emerald-500"></i> Administrar</span></div>
                </div>
            </section>

            <section class="hidden overflow-hidden rounded-2xl border bg-white shadow-sm md:block">
                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full border-separate border-spacing-0 text-xs">
                        <thead class="sticky top-0 z-20 bg-slate-50/95 backdrop-blur"><tr><th class="sticky left-0 z-30 min-w-56 border-b bg-slate-50 px-4 py-3 text-left text-[10px] uppercase tracking-wide text-slate-500">Usuario</th><th v-for="module in visibleModules" :key="module.id" class="min-w-36 border-b px-3 py-3 text-center"><span class="block font-bold text-slate-700">{{ module.name }}</span><span v-if="!module.is_enabled" class="mt-1 inline-block rounded-full bg-slate-200 px-2 py-0.5 text-[8px] uppercase text-slate-500">Inactivo</span></th></tr></thead>
                        <tbody><tr v-for="user in visibleUsers" :key="user.id" class="group hover:bg-indigo-50/30"><td class="sticky left-0 z-10 border-b bg-white px-4 py-3 group-hover:bg-indigo-50"><div class="flex items-center gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-[10px] font-black text-indigo-600">{{ user.name.charAt(0) }}</span><span class="min-w-0"><strong class="block truncate text-xs text-slate-800">{{ user.name }}</strong><small class="block truncate text-[9px] text-slate-400">{{ user.email }}</small></span></div></td><td v-for="module in visibleModules" :key="module.id" class="border-b px-3 py-2.5"><div class="flex justify-center gap-1.5"><button type="button" class="permission-pill" :class="permission(user,module).view ? 'permission-view-active' : ''" @click="toggleView(user,module)">Ver</button><button type="button" class="permission-pill" :class="permission(user,module).manage ? 'permission-manage-active' : ''" @click="toggleManage(user,module)">Editar</button></div></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3 md:hidden">
                <article v-for="user in visibleUsers" :key="user.id" class="overflow-hidden rounded-2xl border bg-white shadow-sm"><header class="flex items-center gap-3 border-b bg-slate-50 p-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-100 font-black text-indigo-700">{{ user.name.charAt(0) }}</span><div class="min-w-0 flex-1"><strong class="block truncate text-xs">{{ user.name }}</strong><small class="block truncate text-[9px] text-slate-400">{{ user.email }}</small></div><button type="button" class="text-[9px] font-bold text-indigo-600" @click="grantUser(user)">Todo</button><button type="button" class="text-[9px] font-bold text-rose-500" @click="clearUser(user)">Ninguno</button></header><div class="divide-y"><div v-for="module in visibleModules" :key="module.id" class="flex items-center justify-between gap-3 px-3 py-2.5"><span class="truncate text-[11px] font-semibold text-slate-600">{{ module.name }}</span><div class="flex gap-1.5"><button type="button" class="permission-pill" :class="permission(user,module).view ? 'permission-view-active' : ''" @click="toggleView(user,module)">Ver</button><button type="button" class="permission-pill" :class="permission(user,module).manage ? 'permission-manage-active' : ''" @click="toggleManage(user,module)">Editar</button></div></div></div></article>
            </section>

            <div class="sticky bottom-3 z-30 flex items-center justify-between gap-3 rounded-2xl border border-indigo-200 bg-white/95 p-3 shadow-xl backdrop-blur"><p class="hidden text-[10px] text-slate-500 sm:block">Administrar activa automáticamente el permiso de consulta.</p><p v-if="form.recentlySuccessful" class="text-xs font-bold text-emerald-600">Cambios guardados</p><button type="submit" class="ml-auto rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-indigo-200 disabled:opacity-50" :disabled="form.processing"><span v-if="form.processing">Guardando…</span><span v-else>Guardar permisos</span></button></div>
        </form>
    </AppLayout>
</template>

<style scoped>
.permission-pill { min-height: 1.75rem; border: 1px solid #e2e8f0; border-radius: .55rem; background: #fff; padding: .25rem .55rem; color: #94a3b8; font-size: .58rem; font-weight: 800; transition: all .15s ease; }
.permission-view-active { border-color: #c7d2fe; background: #eef2ff; color: #4f46e5; box-shadow: inset 0 0 0 1px rgba(99,102,241,.05); }
.permission-manage-active { border-color: #a7f3d0; background: #ecfdf5; color: #059669; box-shadow: inset 0 0 0 1px rgba(16,185,129,.05); }
</style>
