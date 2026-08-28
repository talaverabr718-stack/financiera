<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import NavigationIcon from './NavigationIcon.vue';

const props = defineProps({
    open: Boolean,
    collapsed: Boolean,
    navigation: { type: Array, default: () => [] },
    user: { type: Object, default: () => ({}) },
    routes: { type: Object, default: () => ({}) },
    brand: { type: Object, default: () => ({}) },
    currentUrl: { type: String, default: () => window.location.href },
    inertiaEnabled: { type: Boolean, default: true },
    csrf: String,
});
const emit = defineEmits(['close', 'toggle-collapse']);
const query = ref('');
const expandedGroups = ref(new Set());

const currentPath = computed(() => new URL(props.currentUrl, window.location.origin).pathname);
const groups = computed(() => props.navigation);
const flatItems = computed(() => groups.value.flatMap(group => group.items));
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
        class="premium-sidebar app-sidebar fixed inset-y-0 left-0 z-50 flex flex-col overflow-hidden border-r border-white/8 text-white shadow-2xl transition-[transform,width] duration-300 lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        :style="{ width: 'var(--sidebar-width, 16rem)' }"
        aria-label="Navegación principal"
    >
        <div class="sidebar-brand flex h-[5.25rem] items-center gap-3 border-b border-white/7 px-4" :class="collapsed && 'justify-center px-2'">
            <img v-if="!collapsed && brand.logo_url" :src="brand.logo_url" alt="Logotipo" class="h-11 w-11 shrink-0 bg-white object-contain p-1.5 shadow-lg shadow-black/30">
            <span v-else-if="!collapsed" class="grid h-11 w-11 shrink-0 place-items-center bg-gradient-to-br from-sky-400 via-indigo-500 to-violet-700 text-base font-black shadow-lg shadow-indigo-950/40 ring-1 ring-white/10">{{ brand.system_name?.charAt(0) || 'F' }}</span>
            <div v-if="!collapsed" class="min-w-0 flex-1"><strong class="block truncate text-sm font-bold tracking-tight">{{ brand.system_name || 'Financiera' }}</strong><span class="mt-1 block truncate text-[11px] font-medium text-white/40">Centro de operación</span></div>
            <button class="hidden h-9 w-9 shrink-0 place-items-center border border-white/10 text-emerald-50 transition hover:bg-white/10 lg:grid" :title="collapsed ? 'Expandir menú' : 'Contraer menú'" @click="emit('toggle-collapse')"><svg viewBox="0 0 24 24" class="h-5 w-5 transition-transform" :class="collapsed && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
            <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Cerrar menú" @click="emit('close')">×</button>
        </div>

        <nav class="sidebar-scroll min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4" :class="collapsed && 'px-2'">
            <section v-for="group in groups" :key="group.group">
                <p v-if="!collapsed" class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-[.2em] text-white/30">{{ group.group }}</p>
                <div v-else class="mx-auto mb-2 h-px w-7 bg-white/10"></div>
                <div class="space-y-1.5">
                    <component v-for="item in group.items" :key="item.key" :is="inertiaEnabled && item.inertia ? Link : 'a'" :href="item.url" :title="collapsed ? (item.key === 'dashboard' ? 'Inicio' : item.label) : undefined" v-bind="inertiaEnabled && item.inertia ? { preserveScroll: true } : {}" class="sidebar-item group flex min-h-[3.25rem] items-center gap-3 px-3 text-sm font-semibold tracking-[-.01em] transition-all" :class="[isActive(item) ? 'sidebar-item-active text-white' : 'text-white/65 hover:bg-white/7 hover:text-white', collapsed && 'justify-center px-0']">
                        <span class="sidebar-item-icon grid h-10 w-10 shrink-0 place-items-center"><NavigationIcon :name="item.key" class="h-6 w-6" /></span>
                        <span v-if="!collapsed" class="truncate">{{ item.key === 'dashboard' ? 'Inicio' : item.label }}</span>
                        <span v-if="isActive(item)" class="ml-auto h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                    </component>
                </div>
            </section>
        </nav>

        <div v-if="!collapsed" class="cathedral-line pointer-events-none mx-3 h-20 text-cyan-300/35" aria-hidden="true">
            <svg viewBox="0 0 240 58" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M1 56h238M28 56V32h20v24M31 32V15l7-8 7 8v17M34 20h8M38 10V2M35 5h6M48 56V27h36v29M84 56V32h20v24M87 32V15l7-8 7 8v17M90 20h8M94 10V2M91 5h6M57 56V37h18v19M62 56V43h8v13M107 56c18-7 34-7 50 0s32 7 48 0 24-6 34-2"/><path d="M52 27l14-11 14 11M58 31h16M115 50l8-8 9 8 10-13 12 13"/></svg>
        </div>
        <div class="border-t border-white/7 p-3" :class="collapsed && 'px-2'"><div class="sidebar-user flex items-center gap-3 p-2.5" :class="collapsed && 'justify-center p-1'"><span class="grid h-10 w-10 shrink-0 place-items-center bg-gradient-to-br from-sky-400/25 to-indigo-400/20 text-xs font-bold text-white ring-1 ring-white/10">{{ user?.name?.charAt(0) }}</span><div v-if="!collapsed" class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-white">{{ user?.name }}</p><p class="mt-0.5 truncate text-[10px] text-white/40">Estelí · Nicaragua</p></div><button v-if="!collapsed" class="grid h-8 w-8 place-items-center text-sm text-slate-400 transition hover:bg-rose-500/10 hover:text-rose-300" title="Salir" @click="logout">↪</button></div></div>
        <form v-if="!inertiaEnabled" id="sidebar-logout-form" :action="routes.logout" method="post" class="hidden"><input type="hidden" name="_token" :value="csrf"></form>
    </aside>
</template>
