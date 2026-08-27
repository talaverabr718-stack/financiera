<script setup>
defineProps({ result: Object, currency: Function });
</script>
<template>
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
        <article v-for="(item, index) in [['Capital', result.principal], ['Interés total', result.total_interest], ['Total a pagar', result.total_payment], ['Cuota promedio', result.average_payment]]" :key="item[0]" class="relative overflow-hidden rounded-xl border bg-white p-3.5 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1" :class="['bg-indigo-500','bg-amber-500','bg-emerald-500','bg-cyan-500'][index]"></span>
            <p class="text-[9px] font-bold uppercase tracking-[.12em] text-slate-400">{{ item[0] }}</p><p class="mt-1 truncate text-lg font-bold text-slate-800">{{ currency(item[1]) }}</p>
        </article>
        <article class="col-span-2 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-cyan-50 p-3 lg:col-span-4">
            <div class="flex items-center justify-between text-[10px] font-semibold"><span class="text-indigo-700">Capital {{ ((Number(result.principal) / Number(result.total_payment)) * 100).toFixed(1) }}%</span><span class="text-amber-700">Interés {{ ((Number(result.total_interest) / Number(result.total_payment)) * 100).toFixed(1) }}%</span></div>
            <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-white"><span class="bg-indigo-500" :style="{ width: `${(Number(result.principal) / Number(result.total_payment)) * 100}%` }"></span><span class="flex-1 bg-amber-400"></span></div>
        </article>
    </div>
</template>
