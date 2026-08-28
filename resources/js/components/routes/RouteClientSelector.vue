<script setup>
import { computed, ref } from 'vue';
import BaseModal from '../ui/BaseModal.vue';

const props = defineProps({ clients: Array, selectedIds: Array, lockedIds: Array, cancelUrl: String, submitLabel: String });
const search = ref('');
const locationFilter = ref('all');
const selected = ref(new Set(props.selectedIds.map(Number)));
const detail = ref(null);
const locked = computed(() => new Set(props.lockedIds.map(Number)));
const isMapped = client => client.latitude !== null && client.latitude !== '' && client.longitude !== null && client.longitude !== '';
const coordinates = client => isMapped(client) ? `${Number(client.latitude).toFixed(6)}, ${Number(client.longitude).toFixed(6)}` : 'Coordenadas pendientes';
const mapsUrl = client => `https://www.google.com/maps?q=${encodeURIComponent(`${client.latitude},${client.longitude}`)}`;
const visibleClients = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    return props.clients.filter(client => {
        const matchesTerm = !term || `${client.full_name} ${client.code} ${client.neighborhood ?? ''} ${client.municipality ?? ''} ${client.address ?? ''}`.toLocaleLowerCase('es').includes(term);
        const mapped = isMapped(client);
        return matchesTerm && (locationFilter.value === 'all' || (locationFilter.value === 'mapped' ? mapped : !mapped));
    });
});
const mappedCount = computed(() => props.clients.filter(isMapped).length);
const selectedCount = computed(() => selected.value.size);
const toggle = client => {
    if (locked.value.has(client.id)) return;
    const next = new Set(selected.value); next.has(client.id) ? next.delete(client.id) : next.add(client.id); selected.value = next;
};
const selectMapped = () => { const next = new Set(selected.value); props.clients.filter(isMapped).forEach(client => next.add(client.id)); selected.value = next; };
const clearUnlocked = () => { selected.value = new Set([...selected.value].filter(id => locked.value.has(id))); };
</script>

<template>
    <div class="flex h-full flex-col">
        <header class="border-b border-slate-100 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-sm font-bold text-slate-800">Clientes de la ruta</h2><p class="mt-1 text-[10px] text-slate-400">Selecciona clientes y verifica su cobertura geográfica.</p></div><span class="rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-bold text-indigo-600">{{ selectedCount }} seleccionados</span></div>
            <div class="mt-3 grid grid-cols-3 gap-2"><article class="stat"><strong>{{ clients.length }}</strong><span>Activos</span></article><article class="stat stat-green"><strong>{{ mappedCount }}</strong><span>En mapa</span></article><article class="stat stat-amber"><strong>{{ clients.length - mappedCount }}</strong><span>Sin coordenadas</span></article></div>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row"><label class="relative min-w-0 flex-1"><span class="pointer-events-none absolute left-3 top-2 text-slate-400">⌕</span><input v-model="search" type="search" class="h-9 w-full rounded-lg border bg-slate-50 pl-9 pr-3 text-xs outline-none focus:border-indigo-400 focus:bg-white" placeholder="Nombre, código, barrio o dirección…"></label><button type="button" class="btn-soft" @click="selectMapped">Seleccionar ubicados</button></div>
            <div class="mt-2 flex items-center gap-1"><button v-for="filter in [{v:'all',l:'Todos'},{v:'mapped',l:'En mapa'},{v:'unmapped',l:'Sin coordenadas'}]" :key="filter.v" type="button" class="filter-pill" :class="locationFilter === filter.v && 'filter-active'" @click="locationFilter=filter.v">{{ filter.l }}</button><button v-if="selectedCount > locked.size" type="button" class="ml-auto text-[9px] font-bold text-rose-500" @click="clearUnlocked">Limpiar selección</button></div>
        </header>
        <div class="max-h-[440px] min-h-60 divide-y overflow-y-auto">
            <article v-for="client in visibleClients" :key="client.id" class="group flex cursor-pointer items-center gap-3 p-3 transition hover:bg-indigo-50/40" :class="[selected.has(client.id) && 'bg-indigo-50/60', locked.has(client.id) && 'cursor-not-allowed bg-slate-50']" @click="toggle(client)">
                <input type="checkbox" :checked="selected.has(client.id)" :disabled="locked.has(client.id)" class="pointer-events-none rounded border-slate-300 text-indigo-600">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl" :class="isMapped(client) ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">⌖</span>
                <span class="min-w-0 flex-1"><strong class="block truncate text-xs text-slate-800">{{ client.full_name }}</strong><small class="block truncate text-[9px] text-slate-400">{{ client.code }} · {{ client.neighborhood || client.municipality || 'Ubicación sin detallar' }}</small><small class="mt-0.5 block font-mono text-[9px]" :class="isMapped(client) ? 'text-emerald-600' : 'text-amber-600'">{{ coordinates(client) }}</small></span>
                <a v-if="isMapped(client)" :href="mapsUrl(client)" target="_blank" rel="noopener" class="hidden rounded-lg bg-emerald-50 px-2 py-1 text-[8px] font-bold text-emerald-700 hover:bg-emerald-100 lg:block" @click.stop>Abrir mapa ↗</a>
                <button type="button" class="hidden text-[9px] font-bold text-indigo-500 sm:block" @click.stop="detail=client">Detalles</button>
                <span v-if="locked.has(client.id)" class="status status-locked">Con gestión</span><span v-else class="status" :class="isMapped(client) ? 'status-mapped' : 'status-unmapped'">{{ isMapped(client) ? 'En mapa' : 'Sin coordenadas' }}</span>
            </article>
            <div v-if="!visibleClients.length" class="grid min-h-48 place-items-center p-6 text-center"><div><p class="text-xs font-bold text-slate-500">Sin coincidencias</p><p class="mt-1 text-[10px] text-slate-400">Cambia la búsqueda o el filtro de ubicación.</p></div></div>
        </div>
        <input v-for="id in selected" :key="id" type="hidden" name="client_ids[]" :value="id">
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t bg-slate-50 px-4 py-3"><span class="text-[9px] text-slate-400">{{ visibleClients.length }} visibles · las visitas conservarán el orden mostrado.</span><div class="ml-auto flex gap-2"><a :href="cancelUrl" class="btn-secondary">Cancelar</a><button type="submit" class="btn-primary" :disabled="!selectedCount">{{ submitLabel }}</button></div></footer>
    </div>
    <BaseModal :open="Boolean(detail)" title="Información del cliente" size="max-w-md" @close="detail=null"><div v-if="detail" class="space-y-3 text-xs"><div><span class="label">Cliente</span><strong class="block">{{ detail.full_name }} · {{ detail.code }}</strong></div><div><span class="label">Dirección</span><p>{{ detail.address || 'Sin dirección registrada' }}</p></div><div class="grid grid-cols-2 gap-3"><div><span class="label">Municipio</span><p>{{ detail.municipality || '—' }}</p></div><div><span class="label">Barrio</span><p>{{ detail.neighborhood || '—' }}</p></div></div><div class="rounded-xl p-3" :class="isMapped(detail) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"><strong class="block">{{ coordinates(detail) }}</strong><p class="mt-1">{{ isMapped(detail) ? 'Punto disponible para planificar la visita.' : 'Este cliente no aparecerá como punto en el mapa.' }}</p><a v-if="isMapped(detail)" :href="mapsUrl(detail)" target="_blank" rel="noopener" class="mt-2 inline-flex rounded-lg bg-white/10 px-3 py-2 text-[10px] font-bold text-emerald-300">Abrir ubicación exacta ↗</a></div></div></BaseModal>
</template>

<style scoped>
.stat{display:flex;align-items:center;gap:.4rem;border-radius:.65rem;background:rgba(255,255,255,.05);padding:.45rem .6rem;color:#8b93a7}.stat strong{font-size:.85rem;color:#f4f7ff}.stat span{font-size:.52rem;font-weight:700;text-transform:uppercase}.stat-green{background:rgba(52,211,153,.12)}.stat-green strong{color:#6ee7b7}.stat-amber{background:rgba(251,191,36,.12)}.stat-amber strong{color:#fcd34d}.filter-pill{border-radius:.5rem;padding:.35rem .6rem;font-size:.55rem;font-weight:800;color:#8b93a7}.filter-active{background:rgba(91,140,255,.16);color:#9eb6ff}.status{flex:none;border-radius:9999px;padding:.25rem .5rem;font-size:.52rem;font-weight:800}.status-mapped{background:rgba(52,211,153,.14);color:#6ee7b7}.status-unmapped{background:rgba(251,191,36,.14);color:#fcd34d}.status-locked{background:rgba(255,255,255,.08);color:#8b93a7}.label{display:block;margin-bottom:.2rem;font-size:.55rem;font-weight:800;text-transform:uppercase;color:#8b93a7}
</style>
