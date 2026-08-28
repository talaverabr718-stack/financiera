<script setup>
defineProps({ open: Boolean, title: String, description: String, size: { type: String, default: 'max-w-lg' } });
defineEmits(['close']);
</script>
<template><Teleport to="body"><div v-if="open" class="fixed inset-0 z-[200] grid place-items-center bg-[#05070d]/75 p-4 backdrop-blur-sm" @click.self="$emit('close')"><section class="modal-panel w-full overflow-hidden" :class="size"><header class="modal-heading flex items-start justify-between"><div><p class="dark-kicker">Operación</p><h2 class="mt-1 text-lg font-semibold text-white">{{ title }}</h2><p v-if="description" class="mt-1 text-xs text-white/50">{{ description }}</p></div><button type="button" class="grid h-9 w-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-lg text-white" @click="$emit('close')">×</button></header><div class="p-5"><slot /></div><footer v-if="$slots.footer" class="border-t p-4"><slot name="footer" /></footer></section></div></Teleport></template>
