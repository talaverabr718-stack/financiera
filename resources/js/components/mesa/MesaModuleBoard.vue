<script setup>
import { computed } from 'vue';

const props = defineProps({
    board: { type: Object, default: () => ({}) },
    kicker: { type: String, default: 'Mesa' },
    pieLabel: { type: String, default: 'Composición' },
    pieCenter: { type: String, default: 'Total' },
    tradeLabel: { type: String, default: 'Evolución' },
    tradeCaption: String,
    fillId: { type: String, default: 'mesa-trade-fill' },
    chart: { type: String, default: 'full' },
});

const briefing = computed(() => props.board?.briefing || {});
const stats = computed(() => props.board?.stats || {});
const ring = 226;
const mixTotal = computed(() => (props.board?.mix || []).reduce((sum, item) => sum + Number(item.value || 0), 0));
const pieSlices = computed(() => {
    let offset = 0;
    return (props.board?.mix || []).map(item => {
        const value = Number(item.value || 0);
        const length = mixTotal.value > 0 ? (value / mixTotal.value) * ring : 0;
        const slice = { ...item, value, length, offset, percent: mixTotal.value > 0 ? Math.round((value / mixTotal.value) * 100) : 0 };
        offset += length;
        return slice;
    });
});
const growth = computed(() => props.board?.growth || { points: [], added: 0, delta: 0 });
const growthChart = computed(() => {
    const points = growth.value.points || [];
    const values = points.map(item => Number(item.total || 0));
    const width = 240;
    const height = 88;
    const max = Math.max(...values, 1);
    const coords = values.map((value, index) => {
        const x = values.length <= 1 ? width / 2 : (index / (values.length - 1)) * width;
        const y = 8 + (1 - value / max) * 58;
        return { x, y, value, label: points[index]?.label, added: Number(points[index]?.added || 0) };
    });
    const line = coords.map((point, index) => `${index ? 'L' : 'M'}${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');
    const last = coords[coords.length - 1];
    const area = coords.length ? `${line} L${last.x.toFixed(2)},${height} L${coords[0].x.toFixed(2)},${height} Z` : '';
    const volumeMax = Math.max(...coords.map(point => point.added), 1);
    return { coords, line, area, width, height, volumeMax, last };
});
const delta = computed(() => Number(growth.value.delta || 0));
const bars = computed(() => {
    const items = props.board?.bars || [];
    const max = Math.max(...items.map(item => Number(item.value || 0)), 1);
    return items.map(item => ({
        ...item,
        percent: Math.max(8, Math.round((Number(item.value || 0) / max) * 100)),
    }));
});
const isBars = computed(() => props.chart === 'bars');
</script>
<template>
    <section class="mesa-briefing" :class="{ 'is-bars': isBars }">
        <div class="mesa-briefing-copy">
            <p class="mesa-kicker"><i></i> {{ kicker }}</p>
            <div class="mesa-briefing-meta">
                <span>{{ briefing.date_label }}</span>
            </div>
            <h1>{{ briefing.title }}</h1>
            <p class="mesa-situation">{{ briefing.situation }}</p>
            <div v-if="$slots.actions" class="mesa-actions">
                <slot name="actions" />
            </div>
        </div>
        <aside v-if="isBars" class="mesa-pulse mesa-bars" aria-label="Visitas por ruta">
            <div class="mesa-pulse-head">
                <span>{{ tradeLabel }}</span>
                <strong>{{ stats.total || 0 }}</strong>
            </div>
            <p class="mesa-trade-caption">{{ tradeCaption || briefing.trade_caption }}</p>
            <div v-if="bars.length" class="mesa-pulse-bars" :style="{ gridTemplateColumns: `repeat(${bars.length}, minmax(0, 1fr))` }">
                <div v-for="bar in bars" :key="bar.key || bar.label" class="mesa-pulse-col">
                    <b>{{ bar.value }}</b>
                    <i :style="{ '--mesa-h': `${bar.percent}%` }"></i>
                    <small>{{ bar.label }}</small>
                </div>
            </div>
            <p v-else class="mesa-trade-caption">Sin rutas para graficar en esta fecha.</p>
        </aside>
        <aside v-if="!isBars" class="mesa-pulse mesa-pie" :aria-label="pieLabel">
            <div class="mesa-pulse-head">
                <span>{{ pieLabel }}</span>
                <strong>{{ stats.total || 0 }}</strong>
            </div>
            <div class="mesa-pie-chart">
                <svg viewBox="0 0 80 80" aria-hidden="true">
                    <circle class="mesa-pie-track" cx="40" cy="40" r="36" pathLength="226"></circle>
                    <circle
                        v-for="slice in pieSlices"
                        :key="slice.key"
                        class="mesa-pie-slice"
                        :data-key="slice.key"
                        :data-tone="slice.tone || slice.key"
                        cx="40" cy="40" r="36"
                        pathLength="226"
                        :stroke-dasharray="`${slice.length} ${ring - slice.length}`"
                        :stroke-dashoffset="`${-slice.offset}`"
                    ></circle>
                </svg>
                <div class="mesa-pie-center">
                    <small>{{ pieCenter }}</small>
                    <b>{{ stats.total || 0 }}</b>
                </div>
            </div>
            <ul class="mesa-pie-legend">
                <li v-for="slice in pieSlices" :key="slice.key" :data-key="slice.key" :data-tone="slice.tone || slice.key">
                    <i></i>
                    <span>{{ slice.label }}</span>
                    <b>{{ slice.value }}</b>
                    <em>{{ slice.percent }}%</em>
                </li>
            </ul>
        </aside>
        <aside v-if="!isBars" class="mesa-pulse mesa-trade" :aria-label="tradeLabel">
            <div class="mesa-pulse-head">
                <span>{{ tradeLabel }}</span>
                <strong :data-dir="delta >= 0 ? 'up' : 'down'">{{ delta >= 0 ? '+' : '' }}{{ growth.delta || 0 }}</strong>
            </div>
            <p class="mesa-trade-caption">{{ tradeCaption || briefing.trade_caption }}</p>
            <svg class="mesa-trade-svg" :viewBox="`0 0 ${growthChart.width} ${growthChart.height}`" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient :id="fillId" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#34d399" stop-opacity=".42"></stop>
                        <stop offset="100%" stop-color="#34d399" stop-opacity="0"></stop>
                    </linearGradient>
                </defs>
                <line v-for="guide in [22, 40, 58]" :key="guide" x1="0" :y1="guide" :x2="growthChart.width" :y2="guide" class="mesa-trade-grid"></line>
                <path v-if="growthChart.area" :d="growthChart.area" :fill="`url(#${fillId})`"></path>
                <path v-if="growthChart.line" :d="growthChart.line" class="mesa-trade-line"></path>
                <g class="mesa-trade-volume">
                    <rect
                        v-for="(point, index) in growthChart.coords"
                        :key="`vol-${index}`"
                        :x="point.x - 3.2"
                        :y="point.added ? growthChart.height - Math.max(3, (point.added / growthChart.volumeMax) * 14) : growthChart.height"
                        width="6.4"
                        :height="point.added ? Math.max(3, (point.added / growthChart.volumeMax) * 14) : 0"
                    ></rect>
                </g>
                <circle v-if="growthChart.last" class="mesa-trade-tip" :cx="growthChart.last.x" :cy="growthChart.last.y" r="3.2"></circle>
            </svg>
            <div class="mesa-trade-axis">
                <span>{{ growthChart.coords[0]?.label }}</span>
                <span>{{ growthChart.coords[Math.floor((growthChart.coords.length - 1) / 2)]?.label }}</span>
                <span>{{ growthChart.last?.label }}</span>
            </div>
        </aside>
        <div class="mesa-skyline" aria-hidden="true">
            <svg viewBox="0 0 760 170" fill="none" stroke="currentColor" stroke-width="1.15">
                <path d="M0 164h760M55 164V95h67v69M66 95V42l22-27 22 27v53M75 59h27M88 25V2M78 12h20M122 164V82h127v82M249 164V95h67v69M260 95V42l22-27 22 27v53M269 59h27M282 25V2M272 12h20M152 164v-55h67v55M173 164v-36h25v36M134 82l52-40 51 40M151 94h69M349 164c61-29 111-24 161 0s105 21 155-2 70-20 95-8"/>
            </svg>
        </div>
    </section>
</template>
