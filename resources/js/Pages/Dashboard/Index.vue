<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ stats: Object, recentApplications: Array, recentCollections: Array, links: Object, monthName: String });
const currency = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(Number(value || 0));
</script>
<template>
    <Head title="Panel general" />
    <AppLayout title="Panel general" eyebrow="Resumen operativo" description="Panorama construido con información real del sistema.">
        <template #header-actions><div class="flex gap-2"><a :href="links.newApplication" class="btn-secondary">Nueva solicitud</a><a :href="links.newClient" class="btn-primary">Nuevo cliente</a></div></template>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a v-for="card in [
                ['Cartera activa', currency(stats.activePortfolio), 'Crédito vigente otorgado a los clientes', links.loans],
                ['Monto colocado', currency(stats.placed), `Total de préstamos desembolsados en ${monthName}`, links.loans],
                ['Cobros del día', currency(stats.collectedToday), `${stats.routesToday} rutas programadas`, links.collections],
                ['Índice de mora', `${stats.delinquencyRate}%`, `${stats.delinquentLoans} créditos en mora`, links.loans],
            ]" :key="card[0]" :href="card[3]" class="metric"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ card[0] }}</p><p class="mt-3 text-xl font-bold">{{ card[1] }}</p><p class="mt-2 text-[10px] text-slate-500">{{ card[2] }}</p></a>
        </section>
        <section class="mt-4 grid gap-4 xl:grid-cols-[1.25fr_.75fr]">
            <article class="card p-5"><div class="flex items-center justify-between"><div><h2 class="font-bold">Distribución de cartera</h2><p class="text-xs text-slate-400">Comportamiento actual</p></div><span class="badge bg-indigo-50 text-indigo-700">{{ stats.activeLoans }} activos</span></div><div class="mt-8 flex h-3 overflow-hidden rounded-full bg-slate-100"><span class="bg-indigo-500" :style="{ width: `${Math.max(0, 100-stats.delinquencyRate)}%` }"></span><span class="flex-1 bg-rose-500"></span></div><div class="mt-6 grid grid-cols-3 gap-3"><div v-for="item in [['Solicitudes',stats.pendingApplications],['Rutas hoy',stats.routesToday],['Clientes',stats.clients]]" :key="item[0]" class="rounded-xl bg-slate-50 p-4"><p class="text-[10px] text-slate-500">{{ item[0] }}</p><p class="mt-1 text-xl font-bold">{{ item[1] }}</p></div></div></article>
            <article class="card overflow-hidden"><div class="section-heading"><h2 class="font-bold">Cobranza reciente</h2></div><div class="divide-y"><div v-for="record in recentCollections" :key="record.id" class="p-4"><div class="flex justify-between"><p class="text-xs font-bold">{{ record.client.full_name }}</p><p v-if="record.amount" class="text-xs font-bold text-emerald-700">{{ currency(record.amount) }}</p></div><p class="mt-1 text-[10px] text-slate-400">{{ record.collector?.user?.name }} · {{ record.outcome }}</p></div><p v-if="!recentCollections.length" class="empty-state">Sin actividad reciente</p></div></article>
        </section>
        <article class="card mt-4 overflow-hidden"><div class="section-heading"><h2 class="font-bold">Solicitudes recientes</h2></div><div class="divide-y"><a v-for="application in recentApplications" :key="application.id" :href="`${links.applications}/${application.id}`" class="flex items-center justify-between p-4 hover:bg-slate-50"><div><p class="text-xs font-bold">{{ application.number }} · {{ application.client.full_name }}</p><p class="mt-1 text-[10px] text-slate-400">{{ application.product.name }} · {{ currency(application.requested_amount) }}</p></div><span class="badge bg-indigo-50 text-indigo-700">{{ application.status }}</span></a><p v-if="!recentApplications.length" class="empty-state">Sin solicitudes recientes</p></div></article>
    </AppLayout>
</template>
