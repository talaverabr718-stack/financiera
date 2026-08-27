<script setup>
defineProps({ filters: Object, total: Number, visible: Number });
defineEmits(['export']);
</script>
<template>
    <div class="flex flex-wrap items-end gap-2 border-b border-slate-100 p-3">
        <label class="min-w-40 flex-1 text-[10px] font-semibold text-slate-500">Buscar cuota<input v-model="filters.search" type="search" placeholder="Número o fecha…" class="control"></label>
        <label class="w-28 text-[10px] font-semibold text-slate-500">Desde<input v-model.number="filters.from" type="number" min="1" :max="total" class="control"></label>
        <label class="w-28 text-[10px] font-semibold text-slate-500">Hasta<input v-model.number="filters.to" type="number" min="1" :max="total" class="control"></label>
        <label class="w-28 text-[10px] font-semibold text-slate-500">Filas<select v-model.number="filters.perPage" class="control"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option></select></label>
        <button type="button" class="btn-secondary" @click="$emit('export')">Exportar CSV</button>
        <span class="pb-2 text-[10px] text-slate-400">{{ visible }} de {{ total }}</span>
    </div>
</template>
