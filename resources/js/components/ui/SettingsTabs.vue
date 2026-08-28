<script setup>
defineProps({ tabs: Array });
</script>
<template>
    <nav class="mb-4 flex gap-1 overflow-x-auto rounded-xl border border-white/10 bg-white/5 p-1">
        <a v-for="tab in tabs" :key="tab.url" :href="tab.url" class="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition" :class="tab.active ? 'bg-indigo-500 text-white' : 'text-slate-400 hover:bg-white/5'">{{ tab.label }}</a>
    </nav>
</template>
