<script setup>
defineProps({ modelValue: String, status: String, statuses: { type: Array, default: () => [] }, placeholder: { type: String, default: 'Buscar…' } });
defineEmits(['update:modelValue', 'update:status', 'clear']);
</script>

<template>
    <div class="resource-toolbar flex flex-col gap-3 sm:flex-row sm:items-center">
        <input :value="modelValue" type="search" class="control min-w-0 flex-1" :placeholder="placeholder" @input="$emit('update:modelValue', $event.target.value)">
        <select v-if="statuses.length" :value="status" class="control sm:w-48" @change="$emit('update:status', $event.target.value)">
            <option value="">Todos los estados</option>
            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <button class="btn-secondary" @click="$emit('clear')">Limpiar filtros</button>
        <slot />
    </div>
</template>
