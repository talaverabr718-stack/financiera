<script setup>
defineProps({ modelValue: String, status: String, statuses: { type: Array, default: () => [] }, placeholder: { type: String, default: 'Buscar…' } });
defineEmits(['update:modelValue', 'update:status', 'clear']);
</script>

<template>
    <div class="flex flex-col gap-2 rounded-2xl border bg-white p-3 shadow-sm sm:flex-row">
        <input :value="modelValue" type="search" class="control min-w-0 flex-1" :placeholder="placeholder" @input="$emit('update:modelValue', $event.target.value)">
        <select v-if="statuses.length" :value="status" class="control sm:w-48" @change="$emit('update:status', $event.target.value)">
            <option value="">Todos los estados</option>
            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <button class="rounded-xl border px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50" @click="$emit('clear')">Limpiar</button>
        <slot />
    </div>
</template>
