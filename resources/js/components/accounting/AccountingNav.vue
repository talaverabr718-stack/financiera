<script setup>
import { computed } from 'vue';
import DashboardIcon from '../dashboard/DashboardIcon.vue';

const items = [
    { label: 'Balance general', detail: 'Posición financiera', icon: 'wallet', url: '/contabilidad/balance-general', section: 'balance-general', tone: 'navy' },
    { label: 'Estado de resultados', detail: 'Ingresos y gastos', icon: 'cash', url: '/contabilidad/estado-resultados', section: 'estado-resultados', tone: 'emerald' },
    { label: 'Centro contable', detail: 'Vista ejecutiva', icon: 'shield', url: '/contabilidad', section: 'dashboard', tone: 'blue' },
    { label: 'Asientos', detail: 'Registro y consulta', icon: 'credit', url: '/contabilidad/asientos', section: 'asientos', tone: 'blue' },
    { label: 'Catálogo', detail: 'Plan de cuentas', icon: 'wallet', url: '/contabilidad/cuentas', section: 'cuentas', tone: 'emerald' },
    { label: 'Centros de costo', detail: 'Análisis por área', icon: 'route', url: '/contabilidad/centros-de-costo', section: 'centros-de-costo', tone: 'gold' },
    { label: 'Períodos', detail: 'Cierres contables', icon: 'calendar', url: '/contabilidad/periodos', section: 'periodos', tone: 'navy' },
    { label: 'Libro diario', detail: 'Orden cronológico', icon: 'credit', url: '/contabilidad/diario', section: 'diario', tone: 'blue' },
    { label: 'Libro mayor', detail: 'Saldos por cuenta', icon: 'wallet', url: '/contabilidad/mayor', section: 'mayor', tone: 'emerald' },
    { label: 'Comprobación', detail: 'Debe y haber', icon: 'shield', url: '/contabilidad/balance-comprobacion', section: 'balance-comprobacion', tone: 'gold' },
];

const path = computed(() => typeof window === 'undefined' ? '/contabilidad' : window.location.pathname.replace(/\/$/, ''));
const activeSection = computed(() => {
    const current = path.value;
    if (current === '/contabilidad') return 'dashboard';
    return items.find(item => item.section !== 'dashboard' && current.startsWith(item.url))?.section;
});
</script>

<template>
    <nav class="accounting-module-nav" aria-label="Navegación interna de contabilidad">
        <a
            v-for="item in items"
            :key="item.section"
            :href="item.url"
            :data-tone="item.tone"
            :aria-current="activeSection === item.section ? 'page' : undefined"
        >
            <i><DashboardIcon :name="item.icon" /></i>
            <span><strong>{{ item.label }}</strong><small>{{ item.detail }}</small></span>
        </a>
    </nav>
</template>

<style scoped>
.accounting-module-nav{position:sticky;top:4.5rem;z-index:30;isolation:isolate;display:flex;gap:.55rem;margin:0 -.15rem 1.25rem;overflow-x:auto;border:1px solid #d5e2e1;border-top:0;border-radius:0 0 1rem 1rem;background:#fff;padding:.7rem .75rem .8rem;box-shadow:0 12px 20px -12px rgba(8,43,54,.32);scrollbar-width:thin}.accounting-module-nav:before{content:'';position:absolute;inset:-1px;z-index:-1;border-radius:inherit;background:#fff}.accounting-module-nav a{display:grid;grid-template-columns:2.65rem minmax(7.5rem,1fr);flex:1 0 11.5rem;align-items:center;gap:.7rem;min-height:4.15rem;border:1px solid transparent;border-radius:.85rem;background:#fff;padding:.55rem .7rem;transition:transform .16s,border-color .16s,background .16s}.accounting-module-nav a:hover{border-color:#9bd0c8;background:#f2faf8;transform:translateY(-1px)}.accounting-module-nav a[aria-current=page]{border-color:#8ecbc2;background:#eaf7f4;box-shadow:inset 0 -3px #0a8575}.accounting-module-nav a>i{display:grid;width:2.65rem;height:2.65rem;place-items:center;border-radius:.78rem;background:#eaf2ff;color:#176bd5}.accounting-module-nav a>i svg{width:1.3rem;height:1.3rem}.accounting-module-nav a[data-tone=emerald]>i{background:#e8f7f2;color:#07806f}.accounting-module-nav a[data-tone=gold]>i{background:#fff5db;color:#b77a08}.accounting-module-nav a[data-tone=navy]>i{background:#e8eef2;color:#173f51}.accounting-module-nav a>span{display:grid;min-width:0}.accounting-module-nav strong{color:#173943;font-size:.8rem;line-height:1.2}.accounting-module-nav small{margin-top:.12rem;overflow:hidden;color:#7c8f95;font-size:.65rem;text-overflow:ellipsis;white-space:nowrap}@media(max-width:640px){.accounting-module-nav{top:4.5rem;margin-inline:-.25rem;border-radius:0 0 .85rem .85rem;padding:.5rem}.accounting-module-nav a{grid-template-columns:2.4rem minmax(6.8rem,1fr);flex-basis:10.2rem;min-height:3.7rem;padding:.45rem}.accounting-module-nav a>i{width:2.4rem;height:2.4rem}.accounting-module-nav strong{font-size:.74rem}.accounting-module-nav small{font-size:.6rem}}
</style>
