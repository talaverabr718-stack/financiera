<script setup>
defineProps({ columns: Array, rows: Array, rowKey: { type: String, default: 'id' }, empty: { type: String, default: 'No hay registros.' } });
defineEmits(['row']);
</script>
<template><div class="data-table card table-wrap"><table class="w-full"><thead class="text-left"><tr><th v-for="column in columns" :key="column.key" class="table-cell">{{ column.label }}</th></tr></thead><tbody><tr v-for="row in rows" :key="row[rowKey]" class="cursor-pointer" @click="$emit('row', row)"><td v-for="column in columns" :key="column.key" class="table-cell"><slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot></td></tr><tr v-if="!rows.length"><td :colspan="columns.length" class="p-12 text-center text-xs text-slate-400">{{ empty }}</td></tr></tbody></table></div></template>
