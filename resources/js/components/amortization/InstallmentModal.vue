<script setup>
defineProps({ row: Object, currency: Function, date: Function });
defineEmits(['close']);
</script>
<template>
    <div v-if="row" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="$emit('close')">
        <article class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-4 text-white"><div><p class="text-[10px] uppercase tracking-widest text-indigo-200">Detalle del plazo</p><h2 class="mt-1 text-lg font-bold">Cuota #{{ row.number }}</h2></div><button class="grid h-8 w-8 place-items-center rounded-full bg-white/10 text-xl" @click="$emit('close')">×</button></header>
            <div class="grid grid-cols-2 gap-3 p-5"><div v-for="item in [['Fecha', date(row.date)], ['Saldo inicial', currency(row.opening_balance)], ['Abono a capital', currency(row.principal)], ['Interés', currency(row.interest)], ['Monto de cuota', currency(row.payment)], ['Saldo final', currency(row.closing_balance)]]" :key="item[0]" class="rounded-xl bg-slate-50 p-3"><p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ item[0] }}</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ item[1] }}</p></div></div>
        </article>
    </div>
</template>
