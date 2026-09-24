<script setup>
import { ref } from 'vue';

const { canManage } = usePermissions();`nconst props = defineProps({ summary: Object, currency: { type: String, default: 'NIO' }, endpoint: String });
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const method = ref(props.summary.method || 'daily_percentage');
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
        <form v-if="endpoint && canManage('delinquency')" :action="endpoint" method="post" class="border-t border-slate-100 px-4 py-3">
            <input type="hidden" name="_token" :value="$page.props.csrf ?? document.querySelector('meta[name=csrf-token]')?.content">
            <p class="text-xs font-semibold text-slate-700">Recalcular mora</p>
            <p class="mt-1 text-[11px] text-slate-400">Elige porcentaje diario o un cargo fijo por cada cuota vencida.</p>
            <div class="mt-3 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                <label class="text-xs font-medium text-slate-600">Tipo de mora<select v-model="method" name="method" class="control"><option value="daily_percentage">Porcentaje diario</option><option value="fixed">Cargo fijo por cuota vencida</option></select></label>
                <label v-if="method === 'daily_percentage'" class="text-xs font-medium text-slate-600">% mora por día<input type="number" name="daily_rate" :value="summary.daily_rate" min="0" max="100" step="0.000001" required class="control" placeholder="Ej. 1"></label>
                <label v-else class="text-xs font-medium text-slate-600">Cargo fijo<input type="number" name="fixed_amount" :value="summary.fixed_amount" min="0" step="0.01" required class="control" placeholder="Ej. 15"></label>
                <button class="btn-primary text-xs">Recalcular</button>
            </div>
        </form>
    </section>
</template>
