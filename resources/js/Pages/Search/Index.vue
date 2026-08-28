<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
defineProps({ term: String, results: Array });
</script>
<template><AppLayout title="Búsqueda global" eyebrow="Explorar" :description="term ? `Resultados para “${term}”` : 'Busca clientes, créditos, solicitudes o colaboradores.'"><section class="card overflow-hidden"><div class="divide-y"><a v-for="result in results" :key="`${result.type}-${result.url}`" :href="result.url" class="flex items-center gap-4 p-4 hover:bg-blue-50/50"><span class="badge bg-blue-50 text-blue-700">{{ result.type }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ result.title }}</p><p class="text-[11px] text-slate-400">{{ result.meta }}</p></div><span class="text-slate-400">↗</span></a><p v-if="!results.length" class="empty-state">Escribe al menos dos caracteres o intenta con otro dato.</p></div></section></AppLayout></template>
