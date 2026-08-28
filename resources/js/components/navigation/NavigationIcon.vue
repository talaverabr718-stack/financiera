<script setup>
defineProps({ name: { type: String, default: 'dashboard' } });

const paths = {
    dashboard: ['M3 3h7v7H3z', 'M14 3h7v4h-7z', 'M14 11h7v10h-7z', 'M3 14h7v7H3z'],
    clients: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
    applications: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M9 13h6', 'M9 17h4'],
    loans: ['M3 6h18', 'M5 6v12', 'M19 6v12', 'M3 18h18', 'M6 3h12l3 3H3z', 'M9 10h6'],
    credit_history: ['M3 12a9 9 0 1 0 3-6.7L3 8', 'M3 3v5h5', 'M12 7v5l3 2'],
    delinquency: ['M12 9v4', 'M12 17h.01', 'M10.3 3.7 2.2 18a2 2 0 0 0 1.8 3h16a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0z'],
    amortization: ['M4 19V9', 'M10 19V5', 'M16 19v-7', 'M22 19V3', 'M2 19h22'],
    routes: ['M5 19l14-14', 'M5 5h.01', 'M19 19h.01', 'M7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0', 'M21 19a2 2 0 1 1-4 0 2 2 0 0 1 4 0'],
    collections: ['M12 2v20', 'M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6'],
    cash: ['M3 6h18v12H3z', 'M7 10h.01', 'M17 14h.01', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6'],
    collaborators: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 8v6', 'M23 11h-6'],
    accounting: ['M3 21h18', 'M5 21V10', 'M19 21V10', 'M9 21V10', 'M15 21V10', 'M2 10h20L12 3z'],
    reports: ['M4 19V9', 'M10 19V5', 'M16 19v-3', 'M22 19V8'],
    settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7', 'M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2 3.46-.08-.02a1.7 1.7 0 0 0-1.8.38l-.62.36a1.7 1.7 0 0 0-.84 1.72V23h-4v-.08a1.7 1.7 0 0 0-.84-1.72l-.62-.36a1.7 1.7 0 0 0-1.8-.38l-.08.02-2-3.46.06-.06A1.7 1.7 0 0 0 4.6 15v-.72a1.7 1.7 0 0 0-.94-1.52l-.08-.04v-4l.08-.04a1.7 1.7 0 0 0 .94-1.52V6.5a1.7 1.7 0 0 0-.34-1.02l-.06-.06 2-3.46.08.02a1.7 1.7 0 0 0 1.8-.38l.62-.36A1.7 1.7 0 0 0 9.54-.48V-.5h4v.08a1.7 1.7 0 0 0 .84 1.72l.62.36a1.7 1.7 0 0 0 1.8.38l.08-.02 2 3.46-.06.06a1.7 1.7 0 0 0-.34 1.88v.72a1.7 1.7 0 0 0 .94 1.52l.08.04v4l-.08.04a1.7 1.7 0 0 0-.94 1.52z'],
};
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="path in (paths[name] || paths.dashboard)" :key="path" :d="path" />
    </svg>
</template>
