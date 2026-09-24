<script setup>
defineProps({
    label: { type: String, required: true },
    tone: { type: String, default: 'pending' },
});
</script>

<template>
    <span class="config-status" :data-tone="tone">
        <i aria-hidden="true"></i>
        {{ label }}
    </span>
</template>

<style scoped>
.config-status {
    display: inline-flex;
    align-items: center;
    gap: .42rem;
    width: fit-content;
    border: 1px solid var(--sv-line);
    border-radius: 999px;
    padding: .34rem .62rem;
    background: var(--sv-glass-2);
    color: var(--sv-muted);
    font-size: .64rem;
    font-weight: 700;
    line-height: 1;
}
.config-status i {
    width: .42rem;
    height: .42rem;
    border-radius: 999px;
    background: currentColor;
}
.config-status[data-tone="configured"] { color: var(--sv-ok); }
.config-status[data-tone="pending"] { color: var(--sv-warn); }
.config-status[data-tone="inactive"] { color: var(--sv-muted); }
.config-status[data-tone="alert"] { color: var(--sv-bad); }
</style>
