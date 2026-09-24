<script setup>
import ConfigStatus from './ConfigStatus.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    href: { type: String, required: true },
    icon: { type: String, default: 'settings' },
    status: { type: String, default: 'Pendiente' },
    statusTone: { type: String, default: 'pending' },
});

const icons = {
    brand: ['M4 19.5V5.8c0-.7.4-1.3 1-1.6l6-3 6 3c.6.3 1 .9 1 1.6v13.7', 'M2 22h20', 'M8 10h8M8 14h8'],
    appearance: ['M12 3a9 9 0 1 0 0 18c1.1 0 1.7-.9 1.7-1.7 0-.4-.2-.8-.5-1.1-.3-.3-.5-.7-.5-1.2 0-.9.7-1.7 1.7-1.7H16a5 5 0 0 0 5-5C21 6.3 17 3 12 3Z', 'M7.5 10h.01M9.5 6.5h.01M14.5 6.5h.01M17 10h.01'],
    institution: ['M3 21h18M5 21V9h14v12M8 13h2v2H8zM14 13h2v2h-2zM8 18h2M14 18h2', 'M4 9 12 3l8 6'],
    users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
    permissions: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
    modules: ['M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
    sequences: ['M6 4h12M6 12h12M6 20h12', 'M3 4h.01M3 12h.01M3 20h.01'],
    financial: ['M3 6h18v12H3z', 'M7 10h.01M17 14h.01', 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
    accounting: ['M4 5h16M4 19h16M6 9h12v6H6z', 'M9 9v6M15 9v6'],
    settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z'],
};
</script>

<template>
    <a :href="href" class="config-card">
        <span class="config-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path v-for="path in (icons[icon] || icons.settings)" :key="path" :d="path"/></svg>
        </span>
        <span class="config-card__content">
            <span class="config-card__top">
                <strong>{{ title }}</strong>
                <span class="config-card__arrow" aria-hidden="true">→</span>
            </span>
            <span class="config-card__description">{{ description }}</span>
            <ConfigStatus :label="status" :tone="statusTone" />
        </span>
    </a>
</template>

<style scoped>
.config-card {
    position: relative;
    display: flex;
    min-height: 9.5rem;
    gap: 1rem;
    overflow: hidden;
    border: 1px solid var(--sv-line);
    border-radius: 1.1rem;
    padding: 1.05rem;
    background: var(--sv-glass);
    color: var(--sv-text);
    text-decoration: none;
    box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease, background .2s ease;
}
.config-card::after {
    position: absolute;
    inset: auto 1.1rem 0 1.1rem;
    height: 2px;
    border-radius: 999px;
    background: var(--app-primary);
    content: '';
    opacity: 0;
    transform: scaleX(.4);
    transition: opacity .2s ease, transform .2s ease;
}
.config-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--app-primary) 34%, var(--sv-line));
    background: var(--sv-glass-2);
    box-shadow: 0 18px 34px rgba(0, 0, 0, .13);
}
.config-card:hover::after { opacity: 1; transform: scaleX(1); }
.config-card:focus-visible {
    outline: 3px solid color-mix(in srgb, var(--app-primary) 24%, transparent);
    outline-offset: 2px;
}
.config-card__icon {
    display: grid;
    width: 2.7rem;
    height: 2.7rem;
    flex: none;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--app-primary) 20%, var(--sv-line));
    border-radius: .85rem;
    background: color-mix(in srgb, var(--app-primary) 10%, transparent);
    color: var(--app-primary);
}
.config-card__icon svg {
    width: 1.25rem;
    height: 1.25rem;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.config-card__content { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.config-card__top { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
.config-card__top strong { color: var(--sv-text); font-size: .84rem; line-height: 1.35; }
.config-card__arrow {
    color: var(--sv-muted);
    font-size: 1rem;
    transition: color .2s ease, transform .2s ease;
}
.config-card:hover .config-card__arrow { color: var(--app-primary); transform: translateX(3px); }
.config-card__description {
    display: block;
    margin: .42rem 0 .9rem;
    color: var(--sv-muted);
    font-size: .69rem;
    line-height: 1.55;
}
.config-card :deep(.config-status) { margin-top: auto; }
@media (prefers-reduced-motion: reduce) {
    .config-card, .config-card::after, .config-card__arrow { transition: none; }
}
</style>
