<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    resultCount: { type: Number, default: 0 },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="config-search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input
            :value="modelValue"
            type="search"
            placeholder="Buscar configuración…"
            aria-label="Buscar configuración"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <span>{{ resultCount }} {{ resultCount === 1 ? 'opción' : 'opciones' }}</span>
    </label>
</template>

<style scoped>
.config-search {
    display: flex;
    align-items: center;
    gap: .7rem;
    width: 100%;
    min-height: 3rem;
    border: 1px solid var(--sv-line);
    border-radius: 1rem;
    padding: .25rem .8rem;
    background: var(--sv-glass);
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}
.config-search:focus-within {
    border-color: color-mix(in srgb, var(--app-primary) 48%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--app-primary) 11%, transparent);
}
.config-search svg {
    width: 1.05rem;
    height: 1.05rem;
    flex: none;
    fill: none;
    stroke: var(--sv-muted);
    stroke-width: 1.8;
}
.config-search input {
    min-width: 0;
    flex: 1;
    border: 0 !important;
    padding: .55rem 0 !important;
    background: transparent !important;
    color: var(--sv-text) !important;
    font-size: .78rem;
    outline: none;
    box-shadow: none !important;
}
.config-search input::placeholder { color: var(--sv-muted); }
.config-search span {
    flex: none;
    color: var(--sv-muted);
    font-size: .62rem;
    font-weight: 700;
}
@media (max-width: 480px) {
    .config-search span { display: none; }
}
</style>
