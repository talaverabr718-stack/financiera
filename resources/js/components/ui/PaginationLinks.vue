<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: { type: Array, default: () => [] } });
</script>
<template>
    <div v-if="links.length > 3" class="flex flex-wrap justify-center gap-1">
        <Link v-for="link in links" :key="`${link.label}-${link.url}`" :href="link.url || ''" preserve-scroll class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="[link.active ? 'border-indigo-400 bg-indigo-500 text-white' : 'border-white/10 bg-white/5 text-slate-300', !link.url && 'pointer-events-none opacity-40']"><span v-html="link.label" /></Link>
    </div>
</template>
