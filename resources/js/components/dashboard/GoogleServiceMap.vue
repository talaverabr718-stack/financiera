<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import DashboardIcon from './DashboardIcon.vue';

const L = typeof Leaflet?.map === 'function' ? Leaflet : Leaflet.default;

const props = defineProps({ pins: { type: Array, default: () => [] }, routesUrl: String });
const container = ref(null);
const error = ref('');
const usingOsm = ref(true);
let map;
let markersLayer;
let resizeObserver;

const pinColor = status => ({ pending: '#5b8cff', visited: '#34d399', not_found: '#fb7185', rescheduled: '#fbbf24' }[status] || '#5b8cff');
const validPins = computed(() => props.pins.filter(pin => Number.isFinite(Number(pin.lat)) && Number.isFinite(Number(pin.lng))));
const center = computed(() => {
    if (!validPins.value.length) return { lat: 13.0919, lng: -86.3538 };
    return {
        lat: validPins.value.reduce((sum, pin) => sum + Number(pin.lat), 0) / validPins.value.length,
        lng: validPins.value.reduce((sum, pin) => sum + Number(pin.lng), 0) / validPins.value.length,
    };
});

const pinIcon = (color, label) => L.divIcon({
    className: 'ops-map-pin',
    html: `<span class="ops-map-pin-mark" style="--pin:${color}">${label}</span>`,
    iconSize: [28, 36],
    iconAnchor: [14, 34],
    popupAnchor: [0, -30],
});

const escapeHtml = value => String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

const renderMarkers = () => {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();
    validPins.value.forEach((pin, index) => {
        const pinMarker = L.marker([Number(pin.lat), Number(pin.lng)], {
            icon: pinIcon(pinColor(pin.status), String(index + 1)),
            title: `${pin.name || 'Cliente'} · ${pin.statusLabel || pin.route || ''}`.trim(),
        });
        const neighborhood = [pin.neighborhood, pin.statusLabel || pin.route].filter(Boolean).join(' · ');
        pinMarker.bindPopup(
            `<strong>${escapeHtml(pin.name || 'Cliente')}</strong><div class="ops-map-popup-meta">${escapeHtml(neighborhood)}</div>`,
        );
        markersLayer.addLayer(pinMarker);
    });
    if (validPins.value.length > 1) {
        map.fitBounds(markersLayer.getBounds(), { padding: [36, 36], maxZoom: 15 });
    } else if (validPins.value.length === 1) {
        map.setView([Number(validPins.value[0].lat), Number(validPins.value[0].lng)], 15);
    } else {
        map.setView([center.value.lat, center.value.lng], 13);
    }
    map.invalidateSize();
};

const addTiles = () => {
    usingOsm.value = true;
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);
};

const refreshSize = () => map?.invalidateSize();

onMounted(async () => {
    await nextTick();
    if (!container.value || typeof L?.map !== 'function') {
        error.value = 'El mapa no pudo inicializarse.';
        return;
    }
    try {
        map = L.map(container.value, {
            center: [center.value.lat, center.value.lng],
            zoom: 13,
            zoomControl: true,
        });
        addTiles();
        markersLayer = L.layerGroup().addTo(map);
        renderMarkers();
        refreshSize();
        requestAnimationFrame(refreshSize);
        setTimeout(refreshSize, 80);
        setTimeout(refreshSize, 400);
        window.addEventListener('resize', refreshSize);
        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(refreshSize);
            resizeObserver.observe(container.value);
        }
    } catch {
        error.value = 'El mapa no pudo cargarse. Revisa la conexión e inténtalo de nuevo.';
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', refreshSize);
    resizeObserver?.disconnect();
    resizeObserver = null;
    map?.remove();
    map = null;
    markersLayer = null;
});

watch(() => props.pins, renderMarkers, { deep: true });
</script>

<template>
    <div class="google-service-map" :class="{ 'is-osm': usingOsm }">
        <div v-show="!error" ref="container" class="google-service-map-canvas"></div>
        <div v-if="error" class="map-configuration">
            <DashboardIcon name="route"/>
            <strong>Mapa no disponible</strong>
            <p>{{ error }}</p>
            <a v-if="routesUrl" :href="routesUrl">Gestionar ubicaciones →</a>
        </div>
    </div>
</template>
