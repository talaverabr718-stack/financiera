<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: Boolean,
    navigation: { type: Array, default: () => [] },
    user: { type: Object, default: () => ({}) },
    routes: { type: Object, default: () => ({}) },
    currentUrl: { type: String, default: () => window.location.href },
    inertiaEnabled: { type: Boolean, default: true },
    csrf: String,
});
const emit = defineEmits(['close']);
const query = ref('');
const expandedGroups = ref(new Set());

const currentPath = computed(() => new URL(props.currentUrl, window.location.origin).pathname);
const groups = computed(() => props.navigation);
const filteredGroups = computed(() => {
    const term = query.value.trim().toLocaleLowerCase('es');
    if (!term) return groups.value;

    return groups.value
        .map(group => ({
            ...group,
            items: group.items.filter(item => item.label.toLocaleLowerCase('es').includes(term)),
        }))
        .filter(group => group.items.length);
});

const itemPath = item => new URL(item.url, window.location.origin).pathname;
const isActive = item => currentPath.value === itemPath(item)
    || (itemPath(item) !== '/' && currentPath.value.startsWith(`${itemPath(item)}/`));
const isExpanded = group => query.value.trim() !== '' || expandedGroups.value.has(group);
const toggleGroup = group => {
    const next = new Set(expandedGroups.value);
    next.has(group) ? next.delete(group) : next.add(group);
    expandedGroups.value = next;
};
const logout = () => {
    if (props.inertiaEnabled) return router.post(props.routes.logout);
    document.getElementById('sidebar-logout-form')?.submit();
};

watch(groups, value => {
    const activeGroup = value.find(group => group.items.some(isActive));
    expandedGroups.value = new Set([
        ...(activeGroup ? [activeGroup.group] : []),
        ...(expandedGroups.value.size ? expandedGroups.value : value.map(group => group.group)),
    ]);
}, { immediate: true });

watch(() => props.currentUrl, () => emit('close'));
</script>

<template>
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-white/5 bg-slate-950 text-white shadow-2xl transition-transform duration-300 lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        aria-label="Navegación principal"
    >
        <div class="flex h-17 items-center gap-3 border-b border-white/5 px-4">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-400 font-black shadow-lg shadow-indigo-950">F</div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold">Financiera</p>
                <p class="text-[10px] font-medium text-slate-500">Gestión integral</p>
            </div>
            <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Cerrar menú" @click="emit('close')">×</button>
        </div>

        <div class="px-3 pb-2 pt-3">
            <label class="relative block">
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-500">⌕</span>
                <input
                    v-model="query"
                    type="search"
                    class="h-9 w-full rounded-xl border border-white/5 bg-white/5 pl-9 pr-3 text-xs text-white outline-none transition placeholder:text-slate-600 focus:border-indigo-400/50 focus:bg-white/10 focus:ring-2 focus:ring-indigo-500/15"
                    placeholder="Filtrar módulos…"
                    aria-label="Filtrar módulos"
                >
            </label>
        </div>

        <nav class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-4">
            <section v-for="group in filteredGroups" :key="group.group">
                <button class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-[9px] font-bold uppercase tracking-[.14em] text-slate-600 hover:text-slate-400" @click="toggleGroup(group.group)">
                    <span>{{ group.group }}</span>
                    <span class="text-sm transition-transform" :class="isExpanded(group.group) ? 'rotate-90' : ''">›</span>
                </button>
                <div v-show="isExpanded(group.group)" class="space-y-0.5">
                    <component
                        v-for="item in group.items"
                        :key="item.key"
                        :is="inertiaEnabled && item.inertia ? Link : 'a'"
                        :href="item.url"
                        v-bind="inertiaEnabled && item.inertia ? { preserveScroll: true } : {}"
                        class="group flex min-h-10 items-center gap-3 rounded-xl px-3 text-xs font-semibold transition-all"
                        :class="isActive(item) ? 'bg-indigo-500/20 text-indigo-100 ring-1 ring-inset ring-indigo-400/30' : 'text-slate-400 hover:bg-white/5 hover:text-white'"
                    >
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg text-[10px]" :class="isActive(item) ? 'bg-indigo-400/20 text-indigo-200' : 'bg-white/5 text-slate-500 group-hover:text-white'">{{ item.label.charAt(0) }}</span>
                        <span class="truncate">{{ item.label }}</span>
                        <span v-if="isActive(item)" class="ml-auto h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                    </component>
                </div>
            </section>

            <div v-if="!filteredGroups.length" class="rounded-xl border border-dashed border-white/10 p-4 text-center text-xs text-slate-500">
                No hay módulos que coincidan.
            </div>
        </nav>

        <div class="border-t border-white/5 p-3">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-2.5">
                <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-500/20 text-xs font-bold text-indigo-200">{{ user?.name?.charAt(0) }}</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold">{{ user?.name }}</p>
                    <p class="truncate text-[10px] text-slate-500">{{ user?.email }}</p>
                </div>
                <button class="rounded-lg px-2 py-1.5 text-[10px] font-bold text-slate-400 hover:bg-rose-500/10 hover:text-rose-300" title="Cerrar sesión" @click="logout">Salir</button>
            </div>
            <form v-if="!inertiaEnabled" id="sidebar-logout-form" :action="routes.logout" method="post" class="hidden"><input type="hidden" name="_token" :value="csrf"></form>
        </div>
    </aside>
</template>
