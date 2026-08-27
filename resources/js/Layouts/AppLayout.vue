<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppSidebar from '../components/navigation/AppSidebar.vue';

defineProps({ title: String, eyebrow: { type: String, default: 'Financiera' }, description: String });
const menuOpen = ref(false);
const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-[#f5f7fb]">
        <AppSidebar :open="menuOpen" :navigation="page.props.navigation" :user="page.props.auth.user" :routes="page.props.routes" :current-url="page.url" @close="menuOpen = false" />
        <button v-if="menuOpen" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-label="Cerrar menú" @click="menuOpen = false"></button>
        <main class="min-h-screen lg:ml-64">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-white/90 px-4 backdrop-blur lg:px-6"><div class="flex items-center gap-3"><button class="grid h-9 w-9 place-items-center rounded-lg border lg:hidden" @click="menuOpen = true">☰</button><div><p class="text-[10px] font-bold uppercase tracking-[.15em] text-indigo-600">{{ eyebrow }}</p><h1 class="text-base font-bold">{{ title }}</h1></div></div><slot name="header-actions" /></header>
            <div class="mx-auto max-w-[1500px] p-3 sm:p-5 lg:p-6"><p v-if="description" class="mb-4 text-xs text-slate-500">{{ description }}</p><slot /></div>
        </main>
    </div>
</template>
