<script setup>
import { computed, ref } from 'vue';

const latitude = defineModel('latitude', { type: [String, Number], default: '' });
const longitude = defineModel('longitude', { type: [String, Number], default: '' });
const locating = ref(false);
const message = ref('');
const error = ref(false);
const complete = computed(() => latitude.value !== '' && longitude.value !== '');
const mapsUrl = computed(() => complete.value ? `https://www.google.com/maps?q=${encodeURIComponent(`${latitude.value},${longitude.value}`)}` : '#');

const locate = () => {
    message.value = ''; error.value = false;
    if (!navigator.geolocation) { error.value = true; message.value = 'Este dispositivo no permite obtener la ubicación.'; return; }
    locating.value = true;
    navigator.geolocation.getCurrentPosition(position => {
        latitude.value = position.coords.latitude.toFixed(7);
        longitude.value = position.coords.longitude.toFixed(7);
        message.value = `Ubicación capturada con precisión aproximada de ${Math.round(position.coords.accuracy)} m.`;
        locating.value = false;
    }, geolocationError => {
        error.value = true;
        message.value = geolocationError.code === 1 ? 'Permite el acceso a la ubicación o ingresa las coordenadas manualmente.' : 'No fue posible determinar la ubicación. Intenta nuevamente.';
        locating.value = false;
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
};
const clear = () => { latitude.value = ''; longitude.value = ''; message.value = ''; error.value = false; };
</script>

<template>
    <div class="relative z-0 mt-6 block w-full clear-both rounded-xl border border-indigo-100 bg-indigo-50/35 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600">Ubicación exacta</p><p class="mt-1 text-[10px] text-slate-500">Permite colocar al cliente correctamente en las rutas de cobranza.</p></div><span class="rounded-full px-2.5 py-1 text-[9px] font-bold" :class="complete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ complete ? 'En mapa' : 'Sin coordenadas' }}</span></div>
        <div class="mt-4 grid min-w-0 items-end gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <label>Latitud<input v-model="latitude" type="number" min="-90" max="90" step="0.0000001" placeholder="13.0918000"></label>
            <label>Longitud<input v-model="longitude" type="number" min="-180" max="180" step="0.0000001" placeholder="-86.3538000"></label>
            <button type="button" class="btn-primary h-9 whitespace-nowrap" :disabled="locating" @click="locate">{{ locating ? 'Ubicando…' : 'Usar mi ubicación' }}</button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3"><p v-if="message" class="text-[10px]" :class="error ? 'text-rose-600' : 'text-emerald-700'">{{ message }}</p><a v-if="complete" :href="mapsUrl" target="_blank" rel="noopener" class="text-[10px] font-bold text-indigo-600">Ver punto en el mapa ↗</a><button v-if="complete" type="button" class="text-[10px] font-bold text-rose-500" @click="clear">Quitar coordenadas</button></div>
    </div>
</template>

<style scoped>
label{display:flex;min-width:0;flex-direction:column;color:#64748b;font-size:.62rem;font-weight:700}input{width:100%;min-height:2.2rem;margin-top:.15rem;border:0;border-bottom:1px solid #aeb8c5;border-radius:0;background:transparent;padding:.35rem .1rem;color:#172033;font-size:.75rem;outline:none}label:focus-within{color:#0f9f98}input:focus{border-bottom:2px solid #0f9f98;background:linear-gradient(to bottom,transparent 75%,rgba(15,159,152,.035))}
</style>
