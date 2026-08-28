<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InstallmentLedger from '../../components/loans/InstallmentLedger.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ client: Object, timeline: Array, cycles: { type: Array, default: () => [] }, delinquency: Object, sellers: Array, endpoints: Object });
const transfer = useForm({ seller_id: props.sellers[0]?.id ?? '', reason: '' });
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2 }).format(Number(value || 0));
const date = value => value ? new Intl.DateTimeFormat('es-NI', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '';
const dateDay = value => value ? new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`)) : '—';
const statusLabel = { active: 'Activo', inactive: 'Inactivo', blocked: 'Bloqueado' };
const location = computed(() => [props.client.neighborhood, props.client.municipality, props.client.department].filter(Boolean).join(', ') || 'No registrada');
const facts = computed(() => [
    ['Código', props.client.code],
    ['Cédula', props.client.identity_number],
    ['Estado', statusLabel[props.client.status] || props.client.status],
    ['Teléfono', props.client.phone],
    ['Correo', props.client.email],
    ['Nacimiento', props.client.birth_date],
    ['Ubicación', location.value],
    ['Vendedor', props.client.seller_name ? `${props.client.seller_name}${props.client.seller_code ? ` · ${props.client.seller_code}` : ''}` : 'Sin asignar'],
    ['Actividad', props.client.economic_activity],
    ['Trabajo', props.client.workplace],
    ['Dirección', props.client.address],
]);
const inactivate = () => { if (confirm('¿Deseas inactivar este cliente? Su historial se conservará.')) router.delete(props.endpoints.destroy, { preserveScroll: true }); };
const submitTransfer = () => transfer.post(props.endpoints.transfer, { preserveScroll: true, onSuccess: () => transfer.reset('reason') });
</script>
<template>
    <AppLayout hide-header :title="client.full_name">
        <div class="mesa clients-mesa client-show-mesa">
            <section class="mesa-briefing">
                <div class="mesa-briefing-copy">
                    <div class="client-show-top">
                        <p class="mesa-kicker"><i></i> Expediente</p>
                    </div>
                    <div class="mesa-briefing-meta">
                        <span>{{ client.code }}</span>
                        <span>{{ client.identity_number || 'Sin cédula' }}</span>
                        <span>{{ statusLabel[client.status] || client.status }}</span>
                    </div>
                    <h1>{{ client.full_name }}</h1>
                    <p class="mesa-situation">{{ client.address || 'Sin dirección detallada registrada.' }}</p>
                    <div class="client-show-facts">
                        <div v-for="item in facts" :key="item[0]" class="client-show-fact">
                            <p>{{ item[0] }}</p>
                            <strong>{{ item[1] || 'No registrado' }}</strong>
                        </div>
                    </div>
                </div>
                <aside class="client-show-pulse">
                    <div class="mesa-pulse">
                        <div class="mesa-pulse-head">
                            <span>Mora</span>
                            <strong :data-dir="delinquency.in_arrears ? 'down' : 'up'">{{ delinquency.in_arrears ? 'En atraso' : 'Al día' }}</strong>
                        </div>
                        <p v-if="delinquency.in_arrears" class="client-show-pulse-copy">{{ delinquency.current_days || 0 }} {{ Number(delinquency.current_days) === 1 ? 'día' : 'días' }} · vencido C$ {{ money(delinquency.overdue_balance) }} · mora C$ {{ money(delinquency.total_mora) }}</p>
                        <p v-else class="client-show-pulse-copy">Sin cuotas vencidas pendientes.</p>
                    </div>
                    <div class="mesa-pulse">
                        <div class="mesa-pulse-head">
                            <span>Capacidad</span>
                            <strong>C$ {{ money(client.available) }}</strong>
                        </div>
                        <p class="client-show-pulse-copy">Ingresos C$ {{ money(client.total_income) }} disponibles para cuota.</p>
                    </div>
                </aside>
                <div class="mesa-actions client-show-actions">
                    <div class="client-show-actions-main">
                        <a :href="endpoints.create_application" class="mesa-action" data-tone="emerald">
                            <strong>Nueva solicitud</strong>
                            <small>Iniciar crédito</small>
                        </a>
                        <a :href="endpoints.edit" class="mesa-action" data-tone="blue">
                            <strong>Editar</strong>
                            <small>Actualizar expediente</small>
                        </a>
                        <button type="button" class="mesa-action" data-tone="rose" @click="inactivate">
                            <strong>Inactivar</strong>
                            <small>Conservar historial</small>
                        </button>
                    </div>
                    <Link :href="endpoints.index" class="mesa-action" data-tone="gold">
                        <strong>Regresar</strong>
                        <small>Volver a clientes</small>
                    </Link>
                </div>
            </section>

            <div class="mb-0"><InstallmentLedger :rows="delinquency.ledger ?? []" show-loan empty="Sin plan de cuotas en los créditos de este cliente." /></div>
            <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
                <div class="space-y-5">
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
                        <h2 class="text-sm font-semibold">Vendedor responsable</h2>
                        <p class="mt-4 text-sm font-semibold">{{ client.seller_name || 'Sin asignar' }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ client.seller_code }}</p>
                        <form class="mt-4 space-y-3" @submit.prevent="submitTransfer">
                            <select v-model="transfer.seller_id" class="w-full rounded-lg border px-3 py-2 text-xs"><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.display_name }}</option></select>
                            <textarea v-model="transfer.reason" required rows="2" class="w-full rounded-lg border p-3 text-xs" placeholder="Motivo de transferencia"></textarea>
                            <button class="btn-soft w-full">Transferir cartera</button>
                        </form>
                    </section>
                </aside>
            </div>
            <section class="card overflow-hidden">
                <div class="border-b px-5 py-4">
                    <h2 class="text-sm font-semibold">Línea de tiempo financiera</h2>
                    <p class="mt-0.5 text-[11px] text-slate-400">Ciclo de cada crédito: solicitud, desembolso y cuotas.</p>
                </div>
                <div v-if="cycles.length" class="cycle-table-wrap">
                    <table class="cycle-table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Fecha</th>
                                <th>Referencia</th>
                                <th class="is-num">Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody v-for="cycle in cycles" :key="cycle.id">
                            <tr class="cycle-group">
                                <td colspan="5">{{ cycle.title }}{{ cycle.product ? ` · ${cycle.product}` : '' }}</td>
                            </tr>
                            <tr v-for="(row, index) in cycle.rows" :key="`${cycle.id}-${row.kind}-${index}`" :data-kind="row.kind">
                                <td>{{ row.label }}</td>
                                <td>{{ dateDay(row.date) }}</td>
                                <td>
                                    <a v-if="row.url" :href="row.url">{{ row.reference }}</a>
                                    <span v-else>{{ row.reference || '—' }}</span>
                                </td>
                                <td class="is-num">{{ row.currency === 'NIO' ? 'C$' : row.currency }} {{ money(row.amount) }}</td>
                                <td>{{ row.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="px-5 py-8 text-center text-xs text-slate-400">Todavía no hay solicitudes para armar el ciclo financiero.</p>
            </section>
        </div>
    </AppLayout>
</template>
