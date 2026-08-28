<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppSidebar from '../components/navigation/AppSidebar.vue';
import AccountingNav from '../components/accounting/AccountingNav.vue';

defineProps({
    title: String,
    eyebrow: { type: String, default: 'Financiera' },
    description: String,
    hideHeader: { type: Boolean, default: false },
});
const menuOpen = ref(false);
const sidebarCollapsed = ref(false);
const searchTerm = ref('');
const clock = ref('');
const page = usePage();
const isAccounting = computed(() => page.url.split('?')[0].startsWith('/contabilidad'));
let clockTimer;

const initials = computed(() => (page.props.auth.user?.name ?? 'A').split(/\s+/).slice(0, 2).map(value => value.charAt(0)).join('').toUpperCase());
const today = new Intl.DateTimeFormat('es-NI', { weekday: 'short', day: '2-digit', month: 'short' }).format(new Date());
const fontFamilies = {
    instrument: "'Manrope', sans-serif",
    inter: "'Inter', ui-sans-serif, system-ui, sans-serif",
    system: 'system-ui, sans-serif',
    humanist: 'Optima, Candara, sans-serif',
    nunito: "'Nunito', sans-serif",
    poppins: "'Poppins', sans-serif",
    roboto: "'Roboto', sans-serif",
    lato: "'Lato', sans-serif",
    serif: 'ui-serif, Georgia, serif',
    merriweather: "'Merriweather', Georgia, serif",
    georgia: 'Georgia, serif',
    mono: 'ui-monospace, monospace',
};
const theme = computed(() => page.props.appearance?.theme === 'day' ? 'day' : 'night');
const appearanceStyle = computed(() => ({
    '--app-primary': page.props.appearance?.primary_color || (theme.value === 'day' ? '#1d4ed8' : '#5b8cff'),
    '--app-night-soft': page.props.appearance?.sidebar_color || (theme.value === 'day' ? '#ffffff' : '#080b14'),
    '--app-accent': page.props.appearance?.accent_color || (theme.value === 'day' ? '#0f766e' : '#22d3ee'),
    '--app-canvas': page.props.appearance?.background_color || (theme.value === 'day' ? '#f3f5f8' : '#05070d'),
    fontFamily: fontFamilies[page.props.appearance?.font_family] || fontFamilies.inter,
}));
const shellStyle = computed(() => ({
    ...appearanceStyle.value,
    '--sidebar-width': sidebarCollapsed.value ? '5rem' : '16rem',
    '--sidebar-space': sidebarCollapsed.value ? '5rem' : '17rem',
}));

const syncSearch = () => {
    searchTerm.value = new URLSearchParams(page.url.split('?')[1] || '').get('q') || '';
};
const submitSearch = event => {
    event.preventDefault();
    router.get(page.props.routes.search, { q: searchTerm.value.trim() });
};

onMounted(() => {
    syncSearch();
    const tick = () => {
        clock.value = new Intl.DateTimeFormat('es-NI', {
            hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'America/Managua',
        }).format(new Date());
    };
    tick();
    clockTimer = setInterval(tick, 1000);
});
onUnmounted(() => clearInterval(clockTimer));
watch(() => page.url, syncSearch);
</script>

<template>
    <div class="app-root min-h-screen bg-[var(--app-canvas)]" :class="[`theme-${theme}`, `density-${page.props.appearance?.density || 'comfortable'}`, `radius-${page.props.appearance?.border_radius || 'soft'}`]" :style="shellStyle">
        <AppSidebar :open="menuOpen" :collapsed="sidebarCollapsed" :navigation="page.props.navigation" :user="page.props.auth.user" :routes="page.props.routes" :brand="page.props.brand" :current-url="page.url" @close="menuOpen = false" @toggle-collapse="sidebarCollapsed = !sidebarCollapsed" />
        <button v-if="menuOpen" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-label="Cerrar menú" @click="menuOpen = false"></button>
        <main class="app-main min-h-screen">
            <header class="app-topbar sticky top-0 z-30 flex h-[4.5rem] items-center justify-between gap-3 border-b px-3 backdrop-blur-xl sm:px-5 lg:px-8">
                <div class="flex min-w-0 items-center gap-2 lg:min-w-[13rem]">
                    <button class="grid h-9 w-9 shrink-0 place-items-center border border-slate-200 text-slate-600 lg:hidden" aria-label="Abrir menú" @click="menuOpen = true; sidebarCollapsed = false">☰</button>
                    <nav class="hidden min-w-0 items-center gap-2 text-xs text-slate-400 md:flex" aria-label="Ubicación">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 text-blue-700" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg>
                        <span class="font-semibold text-slate-700">Estelí, Nicaragua</span>
                    </nav>
                </div>
                <form class="relative min-w-0 flex-1 max-w-xl" @submit="submitSearch">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input v-model="searchTerm" name="q" class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/80 pl-10 pr-3 text-xs outline-none transition focus:border-blue-300 focus:bg-white focus:ring-4 focus:ring-blue-100/60" placeholder="Buscar cliente, cédula, crédito o solicitud…" aria-label="Búsqueda global">
                </form>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="hidden text-[11px] tabular-nums text-slate-400 xl:inline">{{ today }} · {{ clock }}</span>
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-amber-100 text-[10px] font-semibold text-slate-800" aria-label="Usuario actual" :title="page.props.auth.user?.name">{{ initials }}</span>
                    <div class="hidden min-w-0 xl:block">
                        <p class="max-w-28 truncate text-[11px] font-bold text-slate-800">{{ page.props.auth.user?.name }}</p>
                        <p class="truncate text-[9px] text-slate-400">{{ page.props.brand?.system_tagline || 'Estelí' }}</p>
                    </div>
                    <button type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" title="Cerrar sesión" @click="router.post(page.props.routes.logout)"><span class="hidden sm:inline">Salir</span><span class="sm:hidden">↪</span></button>
                </div>
            </header>
            <div class="mx-auto max-w-[1720px] p-4 sm:p-5 lg:p-6">
                <div class="print-brand">
                    <div class="print-brand-identity">
                        <img v-if="page.props.brand?.logo_url" :src="page.props.brand.logo_url" alt="Logotipo">
                        <span v-else class="print-brand-fallback">F</span>
                        <div><strong>{{ page.props.brand?.system_name }}</strong><span>{{ page.props.brand?.system_tagline }}</span></div>
                    </div>
                </div>
                <p v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-400/10 px-4 py-3 text-xs font-semibold text-emerald-300">{{ page.props.flash.success }}</p>
                <div v-if="!hideHeader" class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="eyebrow">{{ eyebrow }}</p><h1 class="page-title mt-1">{{ title }}</h1><p v-if="description" class="page-description">{{ description }}</p></div>
                    <div class="shrink-0"><slot name="header-actions" /></div>
                </div>
                <AccountingNav v-if="isAccounting" />
                <slot />
            </div>
        </main>
    </div>
</template>
