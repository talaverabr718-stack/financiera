<script setup>
import { computed, reactive, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import BaseModal from '../../components/ui/BaseModal.vue';

const props = defineProps({ application: Object, guarantees: Array, endpoints: Object, statusLabels: Object, csrf: String });
const application = reactive({ ...props.application });
const decisionOpen = ref(false);
const saving = ref(false);
const errors = ref([]);
const notice = ref('');
const filters = ref('all');
const decision = reactive({ status: application.status, approved_amount: application.approved_amount ?? '', decision_reason: application.decision_reason ?? '' });
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const date = value => value ? new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T12:00:00`)) : 'Pendiente';
const dateTime = value => value ? new Intl.DateTimeFormat('es-NI', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Pendiente';
const locked = computed(() => ['disbursed', 'cancelled'].includes(application.status));
const visibleGuarantees = computed(() => props.guarantees.filter(item => filters.value === 'all' || item.status === filters.value));
const printPage = () => window.print();
const csrfHeaders = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf };
const frequencyLabel = value => ({ daily: 'Diaria', weekly: 'Semanal', biweekly: 'Quincenal', monthly: 'Mensual' }[value] ?? value);
const methodLabel = value => ({ french: 'Cuota nivelada', level_payment: 'Cuota nivelada', flat: 'Interés plano', flat_interest: 'Interés plano', declining_balance: 'Capital constante', constant_principal: 'Capital constante' }[value] ?? (value || 'Por definir'));
const statusTone = computed(() => ({ draft: 'slate', submitted: 'sky', review: 'amber', approved: 'emerald', rejected: 'rose', cancelled: 'slate', disbursed: 'indigo' }[application.status] ?? 'indigo'));
const summaryFields = computed(() => [
    { label: 'Monto solicitado', value: `${application.currency} ${money(application.requested_amount)}`, tone: 'sky', hint: 'Condición pedida por el cliente' },
    { label: 'Monto aprobado', value: application.approved_amount ? `${application.currency} ${money(application.approved_amount)}` : 'Pendiente', tone: 'emerald', hint: application.approved_amount ? 'Monto autorizado' : 'Aún sin decisión' },
    { label: 'Fecha de aprobación', value: dateTime(application.approved_at), tone: 'indigo', hint: 'Momento de la decisión' },
    { label: 'Último pago estimado', value: date(application.estimated_last_payment_date), tone: 'amber', hint: 'Proyección al aprobar' },
]);
const detailFields = computed(() => [
    { label: 'Fecha de solicitud', value: date(application.applied_on), tone: 'cyan' },
    { label: 'Pagos proyectados', value: `${application.term} pagos`, tone: 'violet' },
    { label: 'Cuota con interés', value: `${application.currency} ${money(application.installment_amount)}`, tone: 'teal' },
    { label: 'Frecuencia', value: frequencyLabel(application.payment_frequency), tone: 'sky' },
    { label: 'Tasa', value: application.interest_rate ? `${Number(application.interest_rate).toLocaleString('es-NI', { maximumFractionDigits: 4 })}%` : 'Por definir', tone: 'orange' },
    { label: 'Método', value: methodLabel(application.interest_method), tone: 'slate' },
    { label: 'Primer pago', value: date(application.proposed_first_payment_date), tone: 'amber' },
    { label: 'Vendedor', value: application.seller_name, tone: 'blue' },
]);

const openDecision = () => { Object.assign(decision, { status: application.status, approved_amount: application.approved_amount ?? application.requested_amount, decision_reason: application.decision_reason ?? '' }); errors.value = []; decisionOpen.value = true; };
const saveDecision = async () => {
    if (saving.value) return;
    saving.value = true; errors.value = []; notice.value = '';
    try {
        const response = await fetch(props.endpoints.status, { method: 'PATCH', headers: csrfHeaders, body: JSON.stringify(decision) });
        const data = await response.json();
        if (!response.ok) throw data;
        Object.assign(application, data.application);
        decisionOpen.value = false;
        notice.value = data.message;
    } catch (error) { errors.value = Object.values(error.errors ?? { error: [error.message ?? 'No fue posible actualizar la solicitud.'] }).flat(); }
    finally { saving.value = false; }
};

const decideGuarantee = async (guarantee, status) => {
    const reason = window.prompt(`Motivo para ${status === 'approved' ? 'aprobar' : 'rechazar'} al fiador:`);
    if (!reason) return;
    saving.value = true;
    try {
        const response = await fetch(guarantee.decision_url, { method: 'PATCH', headers: csrfHeaders, body: JSON.stringify({ status, decision_reason: reason }) });
        if (!response.ok) throw await response.json();
        guarantee.status = status;
        notice.value = 'Decisión del fiador registrada.';
    } catch (error) { notice.value = error.message ?? 'No fue posible registrar la decisión.'; }
    finally { saving.value = false; }
};
</script>

<template>
    <AppLayout :title="application.number" eyebrow="Solicitudes" :description="`${application.client_name} · ${application.product_name}`">
        <template #header-actions><div class="flex gap-2"><a :href="endpoints.index" class="btn-secondary">Volver</a><a v-if="!locked" :href="endpoints.edit" class="btn-secondary">Editar</a><button class="btn-primary" @click="printPage">Imprimir</button></div></template>

        <div v-if="notice" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-700"><span>{{ notice }}</span><button @click="notice = ''">×</button></div>
        <div v-if="locked" class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-800"><strong>Solicitud {{ statusLabels[application.status] }}.</strong> El historial de la solicitud permanece protegido. <a v-if="endpoints.loan" :href="endpoints.loan" class="ml-2 font-bold text-indigo-600">Ver préstamo →</a></div>

        <section class="application-panel">
            <header class="panel-status" :class="`tone-${statusTone}`">
                <div>
                    <p class="field-kicker">Estado actual</p>
                    <p class="field-amount">{{ statusLabels[application.status] }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/80 px-3 py-1 text-[10px] font-bold uppercase tracking-wide">{{ application.status }}</span>
                    <button v-if="!locked" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white" @click="openDecision">Evaluar solicitud</button>
                </div>
            </header>
            <div class="panel-body space-y-6">
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="item in summaryFields" :key="item.label" class="field-cell" :class="`tone-${item.tone}`">
                        <p class="field-kicker">{{ item.label }}</p>
                        <p class="field-amount">{{ item.value }}</p>
                        <p class="field-hint">{{ item.hint }}</p>
                    </div>
                </div>
                <div class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in detailFields" :key="item.label" class="field-cell" :class="`tone-${item.tone}`">
                        <p class="field-kicker">{{ item.label }}</p>
                        <p class="field-value">{{ item.value }}</p>
                    </div>
                    <div class="field-cell tone-indigo sm:col-span-2 lg:col-span-4">
                        <p class="field-kicker">Propósito</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ application.purpose }}</p>
                    </div>
                    <p v-if="application.decision_reason" class="rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600 sm:col-span-2 lg:col-span-4">{{ application.decision_reason }}</p>
                </div>
            </div>

            <div v-if="application.status === 'approved'" class="grid gap-5 border-t border-emerald-100 bg-emerald-50/40 p-5 lg:grid-cols-2">
                <div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700">Lista para desembolso</span>
                    <h2 class="mt-3 text-lg font-semibold">Desembolsar {{ application.currency }} {{ money(application.approved_amount) }}</h2>
                    <p class="mt-2 text-xs text-slate-500">El desembolso crea el préstamo y su registro financiero permanente.</p>
                </div>
                <form :action="endpoints.disburse" method="post" class="grid gap-3 sm:grid-cols-2">
                    <input type="hidden" name="_token" :value="csrf">
                    <input type="hidden" name="idempotency_key" :value="application.disbursement_key">
                    <label class="text-xs text-slate-500">Fecha de desembolso<input type="date" name="disbursed_at" :value="application.today" :max="application.today" required class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"></label>
                    <label class="text-xs text-slate-500">Forma de entrega<select name="payment_method" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="check">Cheque</option></select></label>
                    <label class="text-xs text-slate-500 sm:col-span-2">Referencia<input name="reference" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"></label>
                    <button class="rounded-xl bg-emerald-600 px-4 py-3 text-xs font-bold text-white sm:col-span-2">Confirmar desembolso</button>
                </form>
            </div>
            <div v-else-if="application.disbursement" class="flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Desembolso registrado</p>
                    <p class="mt-1 text-base font-semibold">{{ application.disbursement.number }} · {{ application.currency }} {{ money(application.disbursement.amount) }}</p>
                </div>
                <a v-if="endpoints.loan" :href="endpoints.loan" class="font-bold text-indigo-600">Ver préstamo →</a>
            </div>

            <div class="border-t border-slate-100">
                <header class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Garantías y fiadores</h2>
                        <p class="mt-1 text-[11px] text-slate-400">{{ application.requires_guarantor ? 'Esta solicitud requiere fiador.' : 'La política aplicada permite continuar sin fiador.' }}</p>
                    </div>
                    <select v-model="filters" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs"><option value="all">Todos</option><option value="pending">Pendientes</option><option value="approved">Aprobados</option><option value="rejected">Rechazados</option></select>
                </header>
                <div class="divide-y divide-slate-100 border-t border-slate-100">
                    <div v-for="guarantee in visibleGuarantees" :key="guarantee.id" class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_1fr_auto]">
                        <div>
                            <p class="text-sm font-semibold">{{ guarantee.name }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">{{ guarantee.relationship }} · Garantiza {{ application.currency }} {{ money(guarantee.amount) }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <span>Ingresos: <b>{{ money(guarantee.income) }}</b></span>
                            <span>Gastos: <b>{{ money(guarantee.expenses) }}</b></span>
                            <span>Vencidas: <b>{{ guarantee.overdue ? 'Sí' : 'No' }}</b></span>
                            <span>Estado: <b>{{ guarantee.status }}</b></span>
                        </div>
                        <div v-if="!['active','released'].includes(guarantee.status)" class="flex gap-2">
                            <button class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700" @click="decideGuarantee(guarantee,'approved')">Aprobar</button>
                            <button class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" @click="decideGuarantee(guarantee,'rejected')">Rechazar</button>
                        </div>
                    </div>
                    <div v-if="!visibleGuarantees.length" class="px-5 py-8 text-center text-xs text-slate-400">No hay fiadores con este filtro.</div>
                </div>
            </div>
        </section>

        <BaseModal :open="decisionOpen" title="Evaluar solicitud" description="Registra la decisión y, al aprobar, el monto autorizado." @close="decisionOpen = false"><div v-if="errors.length" class="mb-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700"><p v-for="error in errors" :key="error">{{ error }}</p></div><div class="space-y-4"><label class="block text-xs font-semibold text-slate-600">Nuevo estado<select v-model="decision.status" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option v-for="(label,key) in statusLabels" :key="key" :value="key" :disabled="key === 'disbursed'">{{ label }}</option></select></label><label v-if="decision.status === 'approved'" class="block text-xs font-semibold text-slate-600">Monto aprobado<input v-model="decision.approved_amount" type="number" min="0.01" step="0.01" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"></label><label class="block text-xs font-semibold text-slate-600">Motivo / decisión<textarea v-model="decision.decision_reason" rows="3" class="mt-1.5 w-full rounded-xl border border-slate-200 p-3"></textarea></label></div><template #footer><div class="flex justify-end gap-2"><button class="btn-secondary" @click="decisionOpen = false">Cancelar</button><button class="btn-primary" :disabled="saving" @click="saveDecision">{{ saving ? 'Guardando…' : 'Guardar decisión' }}</button></div></template></BaseModal>
    </AppLayout>
</template>
