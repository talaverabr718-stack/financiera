<script setup>
import { computed, ref } from 'vue';
import BaseModal from '../ui/BaseModal.vue';

const props = defineProps({ routesCount: Number, totalStops: Number, initialVisited: Number, initialPending: Number, hasSelectedRoute: Boolean, stops: Array, csrf: String, endpointTemplate: String, dashboardTarget: String });
const stops = ref((props.stops || []).map(stop => ({ ...stop })));
const query = ref('');
const filter = ref('all');
const selected = ref(null);
const notes = ref('');
const saving = ref(false);
const error = ref('');
const visited = computed(() => props.initialVisited + stops.value.filter(stop => stop.initial_status !== 'visited' && stop.status === 'visited').length);
const pending = computed(() => Math.max(0, props.initialPending - stops.value.filter(stop => stop.initial_status === 'pending' && stop.status === 'visited').length));
const progress = computed(() => props.totalStops ? Math.round(visited.value / props.totalStops * 100) : 0);
const visibleStops = computed(() => stops.value.filter(stop => (filter.value === 'all' || stop.status === filter.value) && `${stop.name} ${stop.address} ${stop.neighborhood}`.toLowerCase().includes(query.value.toLowerCase())));
const labels = { pending: 'Pendiente', visited: 'Visitado', not_found: 'No encontrado', rescheduled: 'Reprogramado' };
const openVisit = stop => { selected.value = stop; notes.value = ''; error.value = ''; };
const close = () => { if (!saving.value) selected.value = null; };
const confirmVisit = async () => {
    if (!selected.value || saving.value) return;
    saving.value = true; error.value = '';
    try {
        const response = await fetch(props.endpointTemplate.replace('__STOP__', selected.value.id), { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf }, body: JSON.stringify({ notes: notes.value || null }) });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'No se pudo registrar la visita.');
        selected.value.status = data.stop.status;
        selected.value.visited_at = data.stop.visited_at;
        selected.value = null;
    } catch (exception) { error.value = exception.message; }
    finally { saving.value = false; }
};
</script>

<template>
    <Teleport v-if="dashboardTarget" :to="dashboardTarget">
        <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="metric in [{label:'Rutas del día',value:routesCount,tone:'indigo'},{label:'Visitas realizadas',value:`${visited} de ${totalStops}`,tone:'emerald'},{label:'Avance general',value:`${progress}%`,tone:'violet'},{label:'Visitas pendientes',value:pending,tone:'amber'}]" :key="metric.label" class="card p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ metric.label }}</p><p class="mt-2 text-xl font-semibold text-slate-800">{{ metric.value }}</p><div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500 transition-all" :style="{width: metric.label === 'Avance general' ? `${progress}%` : '35%'}"></div></div></article>
        </section>
    </Teleport>
    <article v-if="hasSelectedRoute" class="card overflow-hidden">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-sm font-semibold">Clientes de esta ruta</h2><p class="mt-1 text-[11px] text-slate-400">Confirma la visita aquí. Los cobros continúan registrándose en Cobranza.</p></div><div class="flex gap-2"><input v-model="query" class="h-9 min-w-0 rounded-xl border border-slate-200 px-3 text-xs" placeholder="Buscar cliente"><select v-model="filter" class="h-9 rounded-xl border border-slate-200 bg-white px-2 text-xs"><option value="all">Todos</option><option value="pending">Pendientes</option><option value="visited">Visitados</option><option value="not_found">No encontrados</option><option value="rescheduled">Reprogramados</option></select></div></header>
        <div class="divide-y divide-slate-100"><div v-for="stop in visibleStops" :key="stop.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-xs font-semibold text-indigo-600">{{ stop.position }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ stop.name }}</p><p class="mt-1 truncate text-[11px] text-slate-400">{{ stop.neighborhood }} · {{ stop.address }}</p><p v-if="stop.latitude && stop.longitude" class="mt-1 text-[10px] font-medium text-indigo-500">{{ stop.latitude }}, {{ stop.longitude }}</p></div><span class="self-start rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="stop.status === 'visited' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ labels[stop.status] }}</span><button v-if="stop.status !== 'visited'" type="button" class="rounded-xl bg-indigo-500 px-4 py-2.5 text-xs font-semibold text-white" @click="openVisit(stop)">Marcar visitado</button><span v-else class="text-[10px] font-medium text-emerald-600">Visita confirmada</span></div><div v-if="!visibleStops.length" class="p-10 text-center text-sm text-slate-400">No hay clientes que coincidan con el filtro.</div></div>
    </article>
    <BaseModal :open="!!selected" title="Confirmar visita" :description="selected ? `Registrar visita a ${selected.name}` : ''" @close="close"><p class="rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">Esta acción confirma la visita operativa. No registra pagos ni resultados de cobranza.</p><label class="mt-4 block text-xs font-semibold text-slate-600">Nota opcional<textarea v-model="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm" placeholder="Observación de la visita"></textarea></label><p v-if="error" class="mt-3 text-xs text-rose-600">{{ error }}</p><template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="close">Cancelar</button><button type="button" class="btn-primary" :disabled="saving" @click="confirmVisit">{{ saving ? 'Guardando…' : 'Confirmar visita' }}</button></div></template></BaseModal>
</template>
