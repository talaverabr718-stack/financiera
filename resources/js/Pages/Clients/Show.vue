<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InstallmentLedger from '../../components/loans/InstallmentLedger.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ client: Object, timeline: Array, delinquency: Object, sellers: Array, endpoints: Object });
const filter = ref('all');
const transfer = useForm({ seller_id: props.sellers[0]?.id ?? '', reason: '' });
const visibleTimeline = computed(() => filter.value === 'all' ? props.timeline : props.timeline.filter(item => item.type === filter.value));
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2 }).format(Number(value || 0));
const date = value => value ? new Intl.DateTimeFormat('es-NI', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '';
const initials = computed(() => (props.client.full_name ?? '').split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase());
const inactivate = () => { if (confirm('¿Deseas inactivar este cliente? Su historial se conservará.')) router.delete(props.endpoints.destroy, { preserveScroll: true }); };
const submitTransfer = () => transfer.post(props.endpoints.transfer, { preserveScroll: true, onSuccess: () => transfer.reset('reason') });
</script>
<template>
    <AppLayout :title="client.full_name" eyebrow="Clientes" :description="`${client.code} · ${client.identity_number || 'Sin cédula registrada'}`">
        <template #header-actions>
            <div class="flex flex-wrap gap-2">
                <button class="btn-secondary" @click="inactivate">Inactivar</button>
                <a :href="endpoints.edit" class="btn-secondary">Editar</a>
                <a :href="endpoints.create_application" class="btn-primary">Nueva solicitud</a>
            </div>
        </template>
        <div class="mb-5"><InstallmentLedger :rows="delinquency.ledger ?? []" show-loan empty="Sin plan de cuotas en los créditos de este cliente." /></div>
        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <div class="space-y-5">
                <section class="card p-6">
                    <h2 class="text-sm font-semibold">Información del expediente</h2>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="item in [['Teléfono', client.phone], ['Correo', client.email], ['Ubicación', [client.neighborhood, client.municipality].filter(Boolean).join(', ')], ['Actividad', client.economic_activity], ['Trabajo / negocio', client.workplace], ['Nacimiento', client.birth_date]]" :key="item[0]"><p class="text-[11px] text-slate-400">{{ item[0] }}</p><p class="mt-1 text-sm font-medium">{{ item[1] || 'No registrado' }}</p></div>
                    </div>
                    <div class="mt-6 rounded-xl bg-slate-50 p-4"><p class="text-[11px] text-slate-400">Dirección detallada</p><p class="mt-1 text-sm text-slate-600">{{ client.address }}</p></div>
                </section>
                <section class="card p-6">
                    <h2 class="text-sm font-semibold">Historial de cartera</h2>
                    <div class="mt-5 space-y-4">
                        <div v-for="assignment in client.portfolio_assignments" :key="assignment.id" class="border-b border-slate-100 pb-4">
                            <div class="flex justify-between"><p class="text-sm font-semibold">{{ assignment.seller_name }}</p><span class="text-[11px] text-slate-400">{{ date(assignment.assigned_at) }}</span></div>
                            <p class="mt-1 text-xs text-slate-400">{{ assignment.reason }}{{ assignment.ended_at ? ` · finalizada ${date(assignment.ended_at)}` : '' }}</p>
                        </div>
                    </div>
                </section>
                <section class="card p-6">
                    <h2 class="text-sm font-semibold">Pertenencias declaradas</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div v-for="asset in client.assets" :key="asset.id" class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold">{{ asset.description }}</p><p class="mt-1 text-[10px] text-slate-400">{{ asset.type }} · Valor C$ {{ money(asset.estimated_value) }}</p></div>
                        <p v-if="!client.assets?.length" class="text-xs text-slate-400">No se declararon pertenencias.</p>
                    </div>
                </section>
                <section class="card p-6">
                    <h2 class="text-sm font-semibold">Historial de solicitudes y créditos</h2>
                    <div class="mt-4 space-y-3">
                        <a v-for="application in client.applications" :key="application.id" :href="application.url" class="flex items-center justify-between rounded-xl border p-4 hover:bg-indigo-50/40"><div><p class="text-sm font-semibold text-indigo-600">{{ application.number }}</p><p class="mt-1 text-[11px] text-slate-400">{{ application.product }} · {{ money(application.requested_amount) }} {{ application.currency }}</p></div><span class="text-[10px] font-semibold uppercase text-slate-500">{{ application.status }}</span></a>
                        <p v-if="!client.applications?.length" class="text-xs text-slate-400">No hay solicitudes registradas.</p>
                    </div>
                </section>
            </div>
            <aside class="space-y-5">
                <section class="card p-5">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Situación de mora</p>
                    <p v-if="delinquency.in_arrears" class="mt-3 text-sm">Hay cuotas vencidas. El detalle está en la tabla de cuotas.</p>
                    <p v-else class="mt-3 text-xs text-slate-500">El cliente no tiene cuotas vencidas pendientes.</p>
                </section>
                <section class="card p-5">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Capacidad mensual</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-indigo-50 p-3"><p class="text-[11px] text-slate-400">Ingresos totales</p><p class="mt-1 text-sm font-semibold">C$ {{ money(client.total_income) }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] text-slate-400">Disponible</p><p class="mt-1 text-sm font-semibold text-indigo-600">C$ {{ money(client.available) }}</p></div>
                    </div>
                </section>
                <section class="card p-5">
                    <h2 class="text-sm font-semibold">Vendedor responsable</h2>
                    <p class="mt-4 text-sm font-semibold">{{ client.seller_name || 'Sin asignar' }}</p>
                    <p class="mt-1 text-[11px] text-slate-400">{{ client.seller_code }}</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitTransfer">
                        <select v-model="transfer.seller_id" class="w-full rounded-lg border px-3 py-2 text-xs"><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.user?.name }}</option></select>
                        <textarea v-model="transfer.reason" required rows="2" class="w-full rounded-lg border p-3 text-xs" placeholder="Motivo de transferencia"></textarea>
                        <button class="btn-soft w-full">Transferir cartera</button>
                    </form>
                </section>
            </aside>
        </div>
        <section class="card mt-5 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div><h2 class="text-sm font-semibold">Línea de tiempo financiera</h2><p class="text-[11px] text-slate-400">Expediente cronológico de solicitudes, créditos y cobranza.</p></div>
                <div class="flex gap-1">
                    <button v-for="item in [['all','Todo'],['credit','Créditos'],['collection','Cobranza'],['activity','Actividad']]" :key="item[0]" type="button" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold" :class="filter === item[0] ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500'" @click="filter = item[0]">{{ item[1] }}</button>
                </div>
            </div>
            <div class="space-y-4 p-5">
                <article v-for="(event, index) in visibleTimeline" :key="index" class="border-b border-slate-100 pb-4">
                    <div class="flex justify-between gap-3"><div><p class="text-[10px] uppercase text-slate-400">{{ event.type }}</p><h3 class="mt-1 text-sm font-semibold">{{ event.title }}</h3><p class="mt-1 text-xs text-slate-500">{{ event.description }}</p><a v-if="event.url" :href="event.url" class="mt-2 inline-flex text-[11px] font-semibold text-indigo-600">Abrir registro →</a></div><time class="text-[10px] text-slate-400">{{ date(event.date) }}</time></div>
                </article>
                <p v-if="!visibleTimeline.length" class="text-xs text-slate-400">Todavía no hay movimientos para este cliente.</p>
            </div>
        </section>
    </AppLayout>
</template>
