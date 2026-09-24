<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    count: { type: Number, default: 0 },
});
</script>

<template>
    <section class="config-section">
        <header>
            <div>
                <h2>{{ title }}</h2>
                <p v-if="description">{{ description }}</p>
            </div>
            <span>{{ count }}</span>
        </header>
        <div class="config-section__grid">
            <slot />
        </div>
    </section>
</template>

<style scoped>
.config-section { min-width: 0; }
.config-section > header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .8rem;
}
.config-section h2 { color: var(--sv-text); font-size: .82rem; font-weight: 800; }
.config-section p { margin-top: .18rem; color: var(--sv-muted); font-size: .65rem; line-height: 1.45; }
.config-section header > span {
    display: grid;
    min-width: 1.7rem;
    height: 1.7rem;
    place-items: center;
    border: 1px solid var(--sv-line);
    border-radius: .55rem;
    color: var(--sv-muted);
    font-size: .65rem;
    font-weight: 800;
}
.config-section__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .8rem;
}
@media (max-width: 720px) {
    .config-section__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
