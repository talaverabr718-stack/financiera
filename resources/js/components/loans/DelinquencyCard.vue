<script setup>
const props = defineProps({ summary: Object, currency: { type: String, default: 'NIO' }, endpoint: String });
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
</script>
<template>
    <section class="card" :class="summary.in_arrears && 'border-rose-200'">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="badge" :class="summary.in_arrears ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'">{{ summary.in_arrears ? 'En mora' : 'Al día' }}</span>
                <p v-if="summary.in_arrears" class="text-sm text-slate-700"><span class="font-semibold">{{ summary.code || 'Sin código' }}</span> · {{ summary.current_days }} días · {{ summary.overdue_count }} cuotas · {{ currency }} {{ money(summary.overdue_balance) }} · mora {{ currency }} {{ money(summary.total_mora) }}</p>
                <p v-else class="text-sm text-slate-500">Sin cuotas vencidas pendientes.</p>
            </div>
        </div>
        <form v-if="endpoint" :action="endpoint" method="post" class="border-t border-slate-100 px-4 py-3">
            <input type="hidden" name="_token" :value="$page.props.csrf ?? document.querySelector('meta[name=csrf-token]')?.content">
            <p class="text-xs font-semibold text-slate-700">Recalcular mora</p>
            <p class="mt-1 text-[11px] text-slate-400">Monto = saldo de la cuota × (% diario / 100) × días de retraso.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <label class="min-w-44 text-xs font-medium text-slate-600">% mora por día<input type="number" name="daily_rate" :value="summary.daily_rate" min="0" max="100" step="0.000001" required class="control" placeholder="Ej. 1"></label>
                <button class="btn-primary text-xs">Recalcular</button>
            </div>
        </form>
    </section>
</template>
