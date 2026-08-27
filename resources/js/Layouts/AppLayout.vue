<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSidebar from '../components/navigation/AppSidebar.vue';

defineProps({ title: String, eyebrow: { type: String, default: 'Financiera' }, description: String });
const menuOpen = ref(false);
const page = usePage();
const initials = computed(() => (page.props.auth.user?.name ?? 'A').split(/\s+/).slice(0, 2).map(value => value.charAt(0)).join('').toUpperCase());
const today = new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date());
</script>

<template>
    <div class="min-h-screen bg-[#f5f7fb]">
        <AppSidebar :open="menuOpen" :navigation="page.props.navigation" :user="page.props.auth.user" :routes="page.props.routes" :current-url="page.url" @close="menuOpen = false" />
        <button v-if="menuOpen" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-label="Cerrar menú" @click="menuOpen = false"></button>
        <main class="min-h-screen lg:ml-64">
            <header class="app-topbar sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/90 px-3 backdrop-blur-xl sm:px-5 lg:px-6">
                <div class="flex min-w-0 items-center gap-2">
                    <button class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 lg:hidden" aria-label="Abrir menú" @click="menuOpen = true">☰</button>
                    <nav class="hidden min-w-0 items-center gap-2 text-xs text-slate-400 md:flex" aria-label="Breadcrumb">
                        <a href="/" class="hover:text-indigo-600">Inicio</a><span>/</span><span class="max-w-40 truncate font-medium text-slate-600">{{ title }}</span>
                    </nav>
                </div>
                <form :action="page.props.routes.search" method="get" class="relative hidden max-w-xl min-w-0 flex-1 sm:block">
                    <span class="pointer-events-none absolute left-3 top-2 text-base text-slate-400">⌕</span>
                    <input name="q" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs outline-none transition focus:border-indigo-300 focus:bg-white" placeholder="Buscar cliente, cédula, crédito o solicitud…" aria-label="Búsqueda global">
                </form>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" class="relative hidden h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 sm:grid" aria-label="Actividades">♢</button>
                    <span class="hidden text-[11px] text-slate-400 xl:inline">{{ today }}</span>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-full bg-indigo-50 text-[10px] font-semibold text-indigo-700" aria-label="Perfil" :title="page.props.auth.user?.name">{{ initials }}</button>
                    <button type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" title="Cerrar sesión" @click="router.post(page.props.routes.logout)"><span class="hidden sm:inline">Salir</span><span class="sm:hidden">↪</span></button>
                </div>
            </header>
            <div class="mx-auto max-w-[1500px] p-3 sm:p-5 lg:p-6">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="eyebrow">{{ eyebrow }}</p><h1 class="page-title">{{ title }}</h1><p v-if="description" class="page-description">{{ description }}</p></div>
                    <div class="shrink-0"><slot name="header-actions" /></div>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
