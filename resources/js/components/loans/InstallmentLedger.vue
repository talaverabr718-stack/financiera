<script setup>
const props = defineProps({ rows: { type: Array, default: () => [] }, showLoan: Boolean, empty: { type: String, default: 'No se ha generado una tabla de amortización para este crédito.' } });
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const date = value => value ? new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`)) : '—';
const tone = value => ({ on_time: 'bg-emerald-50 text-emerald-700', late: 'bg-amber-50 text-amber-800', overdue: 'bg-rose-50 text-rose-700', partial: 'bg-amber-50 text-amber-800', pending: 'bg-slate-100 text-slate-600', excluded: 'bg-slate-100 text-slate-500' }[value] ?? 'bg-slate-100 text-slate-600');
</script>
<template>
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <div><h2 class="text-sm font-semibold">Cuotas</h2><p class="mt-0.5 text-[11px] text-slate-400">Pagadas a tiempo, pendientes y en mora.</p></div>
            <div class="flex gap-3 text-[10px] text-slate-500"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>A tiempo</span><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span>En mora</span><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Tarde</span></div>
        </div>
        <div class="table-wrap max-h-[min(28rem,62vh)] overflow-auto">
            <table class="w-full text-left">
                <thead class="text-[10px] uppercase tracking-wide text-slate-400"><tr>
                    <th v-if="showLoan" class="table-cell">Crédito</th>
                    <th class="table-cell">#</th><th class="table-cell">Vence</th><th class="table-cell text-right">Cuota</th><th class="table-cell text-right">Pagado</th><th class="table-cell text-right">Saldo</th><th class="table-cell">Situación</th><th class="table-cell text-right">Mora</th><th class="table-cell">Historial</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="`${row.loan_id}-${row.number}`" :class="row.settlement === 'overdue' ? 'bg-rose-50/50' : row.settlement === 'on_time' ? 'bg-emerald-50/30' : ''">
                        <td v-if="showLoan" class="table-cell font-mono text-[11px]"><a :href="`/cartera/${row.loan_id}`" class="font-semibold text-indigo-600">{{ row.loan_number }}</a></td>
                        <td class="table-cell font-semibold">{{ row.number }}</td>
                        <td class="table-cell">{{ date(row.due_date) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ money(row.amount_due) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ money(row.amount_paid) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ money(row.outstanding_amount) }}</td>
                        <td class="table-cell"><span class="badge" :class="tone(row.settlement)">{{ row.settlement_label }}</span></td>
                        <td class="table-cell text-right" :class="Number(row.mora_outstanding || row.mora_amount || 0) > 0 || row.days_overdue ? 'text-rose-700' : 'text-slate-400'">
                            <template v-if="Number(row.mora_outstanding || row.mora_amount || 0) > 0 || row.days_overdue"><span class="font-semibold tabular-nums">{{ money(row.mora_outstanding ?? row.mora_amount) }}</span><span class="mt-0.5 block text-[10px] font-medium">{{ row.mora_label }}</span></template>
                            <template v-else>—</template>
                        </td>
                        <td class="table-cell min-w-52 whitespace-normal">
                            <div v-if="row.history?.length" class="max-h-20 space-y-1 overflow-y-auto pr-1 text-[11px] leading-4 text-slate-600">
                                <p v-for="(entry, index) in row.history" :key="index">{{ date(entry.date) }} · {{ entry.title }} · {{ row.currency || 'NIO' }} {{ money(entry.amount) }} · <span :class="entry.on_time === true ? 'text-emerald-700' : entry.on_time === false ? 'text-amber-700' : 'text-slate-400'">{{ entry.timing_label }}</span></p>
                            </div>
                            <span v-else class="text-[11px] text-slate-400">Sin pagos</span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td :colspan="showLoan ? 9 : 8" class="p-8 text-center text-xs text-slate-400">{{ empty }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
