<script setup>
defineProps({ name: { type: String, required: true } });
const paths = {
    wallet: ['M20 7V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V7','M16 14h.01'],
    warning: ['M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z','M12 9v4','M12 17h.01'],
    cash: ['M2 6h20v12H2z','M6 10h.01','M18 14h.01','M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
    credit: ['M3 5h18v14H3z','M3 10h18','M7 15h2'],
    calendar: ['M6 2v4M18 2v4M3 9h18','M4 4h16a1 1 0 0 1 1 1v16H3V5a1 1 0 0 1 1-1Z'],
    phone: ['M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.11L8.1 9.9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.84.57 2.8.7A2 2 0 0 1 22 16.92Z'],
    shield: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z','m9 12 2 2 4-4'],
    client: ['M20 21a8 8 0 0 0-16 0','M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
    route: ['M6 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM18 15a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z','M6 9v2a4 4 0 0 0 4 4h5'],
    plus: ['M12 5v14M5 12h14'],
};
</script>
<template><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path v-for="path in (paths[name] || paths.credit)" :key="path" :d="path"/></svg></template>
