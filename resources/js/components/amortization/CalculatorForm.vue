<script setup>
import { computed, watch } from 'vue';

const props = defineProps({ form: Object, frequencies: Object, errors: Object, loading: Boolean, valid: Boolean });
defineEmits(['calculate']);

const monthlyFlat = computed(() => props.form.method === 'monthly_flat');
const availableFrequencies = computed(() => Object.fromEntries(
    Object.entries(props.frequencies).filter(([key]) => !monthlyFlat.value || ['weekly', 'biweekly', 'monthly'].includes(key)),
));
const monthlyFactor = computed(() => ({ weekly: 4, biweekly: 2, monthly: 1 }[props.form.frequency] || 0));
const calculatedPayments = computed(() => Number(props.form.term_months || 0) * monthlyFactor.value);
const totalRate = computed(() => Number(props.form.annual_rate || 0) * Number(props.form.term_months || 0));

watch(monthlyFlat, enabled => {
    if (enabled && !['weekly', 'biweekly', 'monthly'].includes(props.form.frequency)) {
        props.form.frequency = 'weekly';
    }
}, { immediate: true });
</script>
<template>
    <form class="grid grid-cols-2 gap-3 p-4" @submit.prevent="$emit('calculate')">
        <label class="field-label col-span-2">Monto del crédito<div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">C$</span><input v-model="form.principal" type="number" min="0.01" max="1000000000" step="0.01" class="control pl-9" required></div></label>
        <label class="field-label">{{ monthlyFlat ? 'Tasa mensual' : 'Tasa anual' }}<div class="relative"><input v-model="form.annual_rate" type="number" min="0" max="1000" step="0.000001" class="control pr-8" required><span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span></div></label>
        <label v-if="monthlyFlat" class="field-label">Plazo (meses)<input v-model="form.term_months" type="number" min="1" max="60" class="control" required></label>
        <label v-else class="field-label">Cantidad de pagos<input v-model="form.periods" type="number" min="1" max="365" class="control" required></label>
        <label class="field-label">Frecuencia<select v-model="form.frequency" class="control"><option v-for="(item, key) in availableFrequencies" :key="key" :value="key">{{ item.label }}</option></select></label>
        <label class="field-label">Primera cuota<input v-model="form.first_payment_date" type="date" class="control" required></label>
        <div v-if="monthlyFlat" class="col-span-2 rounded-xl border border-emerald-100 bg-emerald-50 p-3">
            <div class="flex items-center justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Proyección automática</p><p class="mt-1 text-[10px] text-emerald-600">{{ form.frequency === 'weekly' ? '4 cuotas por mes' : form.frequency === 'biweekly' ? '2 cuotas por mes' : '1 cuota por mes' }}</p></div><strong class="text-lg text-emerald-700">{{ calculatedPayments }} cuotas</strong></div>
            <p class="mt-2 border-t border-emerald-100 pt-2 text-[10px] text-emerald-700">Porcentaje total: {{ Number.isFinite(totalRate) ? totalRate.toFixed(2) : '0.00' }}%</p>
        </div>
        <div v-if="Object.keys(errors).length" class="col-span-2 rounded-lg bg-rose-50 p-2 text-[11px] text-rose-700"><p v-for="message in Object.values(errors).flat()" :key="message">{{ message }}</p></div>
        <button class="btn-primary col-span-2" :disabled="loading || !valid"><span v-if="loading" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ loading ? 'Actualizando…' : 'Calcular ahora' }}</button>
    </form>
</template>
