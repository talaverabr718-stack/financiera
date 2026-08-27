<script setup>
defineProps({ open: Boolean, title: String, description: String, size: { type: String, default: 'max-w-lg' } });
defineEmits(['close']);
</script>
<template><Teleport to="body"><div v-if="open" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="$emit('close')"><section class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl" :class="size"><header class="flex items-start justify-between border-b p-4"><div><h2 class="font-bold">{{ title }}</h2><p v-if="description" class="mt-1 text-xs text-slate-500">{{ description }}</p></div><button class="grid h-8 w-8 place-items-center rounded-full bg-slate-100 text-lg" @click="$emit('close')">×</button></header><div class="p-4"><slot /></div><footer v-if="$slots.footer" class="border-t bg-slate-50 p-4"><slot name="footer" /></footer></section></div></Teleport></template>
