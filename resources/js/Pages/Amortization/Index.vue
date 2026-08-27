<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import CalculatorForm from '../../components/amortization/CalculatorForm.vue';
import InstallmentModal from '../../components/amortization/InstallmentModal.vue';
import ScheduleFilters from '../../components/amortization/ScheduleFilters.vue';
import ScheduleTable from '../../components/amortization/ScheduleTable.vue';
import SummaryDashboard from '../../components/amortization/SummaryDashboard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ input: Object, methods: Object, frequencies: Object, calculateUrl: String });
const form = reactive({ ...props.input });
const result = ref(null);
const loading = ref(false);
const errors = ref({});
const selected = ref(null);
const calculated = ref(false);
const currentPage = ref(1);
const filters = reactive({ search: '', from: null, to: null, perPage: 25 });
let timer;
let requestController;

const valid = computed(() => Number(form.principal) > 0 && Number(form.annual_rate) >= 0 && Number(form.periods) > 0 && form.first_payment_date);
const filteredRows = computed(() => (result.value?.rows || []).filter(row => {
    const term = filters.search.trim().toLowerCase();
    return (!term || String(row.number).includes(term) || date(row.date).includes(term)) && (!filters.from || row.number >= filters.from) && (!filters.to || row.number <= filters.to);
}));
const pages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / filters.perPage)));
const paginatedRows = computed(() => filteredRows.value.slice((currentPage.value - 1) * filters.perPage, currentPage.value * filters.perPage));
const currency = value => new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(Number(value || 0));
const date = value => new Intl.DateTimeFormat('es-NI').format(new Date(`${String(value).slice(0, 10)}T12:00:00`));
const printPage = () => window.print();

async function calculate() {
    if (!valid.value) return;
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    loading.value = true;
    errors.value = {};
    try {
        const { data } = await axios.post(props.calculateUrl, form, { headers: { Accept: 'application/json' }, signal: controller.signal });
        result.value = data;
        calculated.value = true;
        currentPage.value = 1;
    } catch (error) {
        if (error.code !== 'ERR_CANCELED') errors.value = error.response?.data?.errors || { form: ['No se pudo completar la simulación.'] };
    } finally {
        if (requestController === controller) loading.value = false;
    }
}

function exportCsv() {
    const header = ['Cuota', 'Fecha', 'Saldo inicial', 'Capital', 'Interés', 'Pago', 'Saldo final'];
    const body = filteredRows.value.map(row => [row.number, date(row.date), row.opening_balance, row.principal, row.interest, row.payment, row.closing_balance]);
    const csv = '\uFEFF' + [header, ...body].map(columns => columns.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url; link.download = 'tabla-amortizacion.csv'; link.click(); URL.revokeObjectURL(url);
}

watch(form, () => { clearTimeout(timer); if (calculated.value && valid.value) timer = setTimeout(calculate, 300); }, { deep: true });
watch(filters, () => { currentPage.value = 1; }, { deep: true });
const closeOnEscape = event => { if (event.key === 'Escape') selected.value = null; };
onMounted(() => window.addEventListener('keydown', closeOnEscape));
onBeforeUnmount(() => { clearTimeout(timer); requestController?.abort(); window.removeEventListener('keydown', closeOnEscape); });
</script>

<template>
    <Head title="Calculadora de amortización" />
    <AppLayout title="Calculadora de amortización" eyebrow="Simulador financiero" description="Actualizaciones instantáneas con validación segura en Laravel.">
        <div class="grid gap-4 xl:grid-cols-[310px_minmax(0,1fr)]">
                    <aside class="card h-fit overflow-hidden xl:sticky xl:top-20"><div class="border-b bg-gradient-to-r from-indigo-50 to-cyan-50 px-4 py-3"><p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600">Parámetros</p><p class="mt-1 text-[11px] text-slate-500">Cálculo seguro procesado por Laravel.</p></div><CalculatorForm :form="form" :methods="methods" :frequencies="frequencies" :errors="errors" :loading="loading" :valid="valid" @calculate="calculate" /></aside>

                    <section class="min-w-0 space-y-3" aria-live="polite">
                        <template v-if="result">
                            <SummaryDashboard :result="result" :currency="currency" />
                            <article class="card overflow-hidden"><div class="flex items-center justify-between border-b px-4 py-3"><div><h2 class="text-sm font-bold">Plan de pagos</h2><p class="text-[10px] text-slate-400">Tasa periódica {{ result.periodic_rate }}% · selecciona una fila para ver detalles</p></div><button class="btn-secondary" @click="printPage">Imprimir</button></div><ScheduleFilters :filters="filters" :total="result.rows.length" :visible="filteredRows.length" @export="exportCsv" /><ScheduleTable :rows="paginatedRows" :page="currentPage" :pages="pages" :currency="currency" :date="date" @select="selected = $event" @page="currentPage = $event" /></article>
                        </template>
                        <div v-else class="card grid min-h-[520px] place-items-center p-8 text-center"><div class="max-w-md"><div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-cyan-400 text-2xl font-black text-white shadow-xl shadow-indigo-200">%</div><h2 class="mt-5 text-xl font-bold">Construye una proyección clara</h2><p class="mt-2 text-sm leading-6 text-slate-500">Configura el monto, tasa y plazos. Obtendrás indicadores, tabla filtrable, paginación, exportación y detalle por cuota.</p><div class="mt-5 flex justify-center gap-2 text-[10px] font-semibold text-slate-400"><span class="rounded-full bg-slate-100 px-3 py-1">Sin persistencia</span><span class="rounded-full bg-slate-100 px-3 py-1">Validación backend</span><span class="rounded-full bg-slate-100 px-3 py-1">Vue 3</span></div></div></div>
                    </section>
        </div>
        <InstallmentModal :row="selected" :currency="currency" :date="date" @close="selected = null" />
    </AppLayout>
</template>
