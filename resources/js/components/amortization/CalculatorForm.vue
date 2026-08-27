<script setup>
defineProps({ form: Object, methods: Object, frequencies: Object, errors: Object, loading: Boolean, valid: Boolean });
defineEmits(['calculate']);
</script>
<template>
    <form class="grid grid-cols-2 gap-3 p-4" @submit.prevent="$emit('calculate')">
        <label class="field-label col-span-2">Monto del crédito<div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">C$</span><input v-model="form.principal" type="number" min="0.01" max="1000000000" step="0.01" class="control pl-9" required></div></label>
        <label class="field-label">Tasa anual<div class="relative"><input v-model="form.annual_rate" type="number" min="0" max="1000" step="0.000001" class="control pr-8" required><span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span></div></label>
        <label class="field-label">Plazos<input v-model="form.periods" type="number" min="1" max="600" class="control" required></label>
        <label class="field-label">Frecuencia<select v-model="form.frequency" class="control"><option v-for="(item, key) in frequencies" :key="key" :value="key">{{ item.label }}</option></select></label>
        <label class="field-label">Primera cuota<input v-model="form.first_payment_date" type="date" class="control" required></label>
        <label class="field-label col-span-2">Método<select v-model="form.method" class="control"><option v-for="(label, key) in methods" :key="key" :value="key">{{ label }}</option></select></label>
        <div v-if="Object.keys(errors).length" class="col-span-2 rounded-lg bg-rose-50 p-2 text-[11px] text-rose-700"><p v-for="message in Object.values(errors).flat()" :key="message">{{ message }}</p></div>
        <button class="btn-primary col-span-2" :disabled="loading || !valid"><span v-if="loading" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ loading ? 'Actualizando…' : 'Calcular ahora' }}</button>
    </form>
</template>
