<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SettingsTabs from '../../components/ui/SettingsTabs.vue';

const props = defineProps({
    section: String,
    tabs: Array,
    checks: Object,
    settings: Object,
    modules: Array,
    products: Array,
    criticalSettings: Array,
    productsUrl: String,
    accounts: Array,
    sequences: Array,
    update: String,
});

const appearanceDefaults = {
    theme: 'night',
    primary_color: '#5b8cff',
    sidebar_color: '#080b14',
    accent_color: '#22d3ee',
    background_color: '#05070d',
    font_family: 'inter',
    density: 'comfortable',
    border_radius: 'soft',
};

const initialForm = () => {
    if (props.section === 'modules') {
        return { modules: Object.fromEntries((props.modules || []).map(m => [m.id, { enabled: m.is_enabled, visible: m.is_visible, sort_order: m.sort_order }])) };
    }
    if (props.section === 'sequences') {
        return { sequences: Object.fromEntries((props.sequences || []).map(s => [s.key, { prefix: s.prefix, padding: s.padding }])) };
    }
    if (props.section === 'appearance') {
        const settings = { ...(props.settings || {}) };
        Object.entries(appearanceDefaults).forEach(([key, value]) => {
            if (!settings[key]) settings[key] = value;
        });
        return settings;
    }
    return { ...(props.settings || {}) };
};

const form = useForm(initialForm());

const themePresets = {
    night: { theme: 'night', primary_color: '#5b8cff', sidebar_color: '#080b14', accent_color: '#22d3ee', background_color: '#05070d' },
    day: { theme: 'day', primary_color: '#1d4ed8', sidebar_color: '#ffffff', accent_color: '#0f766e', background_color: '#f3f5f8' },
};

const previewTheme = theme => {
    const mode = theme === 'day' ? 'day' : 'night';
    document.documentElement.classList.toggle('theme-day', mode === 'day');
    document.documentElement.classList.toggle('theme-night', mode !== 'day');
    document.documentElement.dataset.theme = mode;
};

const applyTheme = theme => {
    Object.entries(themePresets[theme] || themePresets.night).forEach(([key, value]) => {
        form[key] = value;
    });
    previewTheme(theme);
};

const save = (files = false) => form.put(props.update, { forceFormData: files, preserveScroll: true });
const fields = {
    general: [['institution_name', 'Nombre de la institución'], ['legal_name', 'Razón social'], ['tax_id', 'Identificación tributaria'], ['phone', 'Teléfono'], ['email', 'Correo'], ['address', 'Dirección']],
    appearance: [['primary_color', 'Color principal', 'color'], ['sidebar_color', 'Barra lateral', 'color'], ['accent_color', 'Acento', 'color'], ['background_color', 'Fondo', 'color']],
    brand: [['system_name', 'Nombre del sistema'], ['system_tagline', 'Descripción']],
    accounting: [['cash_account_id', 'Cuenta de caja'], ['loan_receivable_account_id', 'Cartera por cobrar'], ['interest_income_account_id', 'Ingresos por interés'], ['fee_income_account_id', 'Ingresos por cargos'], ['delinquency_income_account_id', 'Ingresos por mora']],
};
</script>
<template>
    <AppLayout title="Configuración" eyebrow="Administración" description="Parámetros del sistema sin reglas financieras implícitas.">
        <SettingsTabs :tabs="tabs"/>
        <section v-if="section==='index'" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a v-for="tab in tabs.slice(1)" :key="tab.url" :href="tab.url" class="card p-5">
                <p class="font-semibold">{{ tab.label }}</p>
                <p class="mt-2 text-xs" :class="checks?.[tab.url.split('/').pop()] ? 'text-emerald-600' : 'text-amber-700'">{{ checks?.[tab.url.split('/').pop()] ? 'Configurado' : 'Requiere revisión' }}</p>
            </a>
        </section>
        <form v-else-if="['general','appearance','brand','accounting'].includes(section)" class="card p-5" novalidate @submit.prevent="save(section==='brand')">
            <div v-if="section==='appearance'" class="theme-picker">
                <p class="theme-picker-kicker">Tema del sistema</p>
                <p class="theme-picker-copy">Misma interfaz. Solo cambia el color: oscuro para operación, claro y sobrio para trabajo de oficina.</p>
                <div class="theme-picker-grid">
                    <button type="button" class="theme-swatch is-night" :class="{ 'is-selected': (form.theme || 'night') === 'night' }" @click="applyTheme('night')">
                        <span class="theme-swatch-preview" aria-hidden="true"></span>
                        <strong>Sala de control</strong>
                        <small>Oscuro, vidrio, acento eléctrico</small>
                    </button>
                    <button type="button" class="theme-swatch is-day" :class="{ 'is-selected': form.theme === 'day' }" @click="applyTheme('day')">
                        <span class="theme-swatch-preview" aria-hidden="true"></span>
                        <strong>Oficina</strong>
                        <small>Claro, blanco, sobrio</small>
                    </button>
                </div>
            </div>
            <p v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-300">
                <span v-for="(error, key) in form.errors" :key="key" class="block">{{ error }}</span>
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <label v-for="field in fields[section]" :key="field[0]" class="field-label">{{ field[1] }}
                    <select v-if="section==='accounting'" v-model="form[field[0]]" class="control">
                        <option value="">Seleccionar</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.code }} · {{ account.name }}</option>
                    </select>
                    <input v-else v-model="form[field[0]]" :type="field[2] || 'text'" class="control">
                </label>
                <template v-if="section==='appearance'">
                    <label class="field-label">Tipografía
                        <select v-model="form.font_family" class="control">
                            <option value="inter">Inter</option>
                            <option value="system">Sistema</option>
                            <option value="humanist">Humanista</option>
                            <option value="serif">Serif</option>
                            <option value="merriweather">Merriweather</option>
                            <option value="georgia">Georgia</option>
                            <option value="mono">Monoespaciada</option>
                        </select>
                    </label>
                    <label class="field-label">Densidad
                        <select v-model="form.density" class="control">
                            <option value="comfortable">Cómoda</option>
                            <option value="compact">Compacta</option>
                        </select>
                    </label>
                    <label class="field-label">Bordes
                        <select v-model="form.border_radius" class="control">
                            <option value="soft">Suaves</option>
                            <option value="rounded">Redondeados</option>
                            <option value="square">Cuadrados</option>
                        </select>
                    </label>
                </template>
                <template v-if="section==='brand'">
                    <label class="field-label">Logotipo<input type="file" class="control" @change="form.logo = $event.target.files[0]"></label>
                    <label class="flex items-center gap-2 text-xs"><input v-model="form.remove_logo" type="checkbox"> Quitar logotipo actual</label>
                </template>
            </div>
            <button type="submit" class="btn-primary mt-5" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</button>
        </form>
        <form v-else-if="section==='modules'" class="card overflow-hidden" @submit.prevent="save(false)">
            <div v-for="module in modules" :key="module.id" class="grid grid-cols-[1fr_auto_auto_90px] items-center gap-3 border-b p-4">
                <div><p class="font-semibold">{{ module.name }}</p><p class="text-[10px] text-slate-400">{{ module.key }}</p></div>
                <label class="text-xs"><input v-model="form.modules[module.id].enabled" type="checkbox"> Activo</label>
                <label class="text-xs"><input v-model="form.modules[module.id].visible" type="checkbox"> Visible</label>
                <input v-model="form.modules[module.id].sort_order" type="number" class="control">
            </div>
            <div class="p-4"><button class="btn-primary">Aplicar módulos</button></div>
        </form>
        <form v-else-if="section==='sequences'" class="card overflow-hidden" @submit.prevent="save(false)">
            <div v-for="sequence in sequences" :key="sequence.key" class="grid gap-3 border-b p-4 sm:grid-cols-[1fr_180px_120px]">
                <div><p class="font-semibold">{{ sequence.key }}</p><p class="text-xs text-slate-400">Siguiente: {{ sequence.next_number }}</p></div>
                <label class="field-label">Prefijo<input v-model="form.sequences[sequence.key].prefix" class="control"></label>
                <label class="field-label">Dígitos<input v-model="form.sequences[sequence.key].padding" type="number" class="control"></label>
            </div>
            <div class="p-4"><button class="btn-primary">Guardar formatos</button></div>
        </form>
        <section v-else-if="section==='financial'" class="space-y-4">
            <div class="card p-5">
                <div class="flex justify-between">
                    <div><h2 class="font-semibold">Productos crediticios</h2><p class="text-xs text-slate-400">{{ products.length }} configurados</p></div>
                    <a :href="productsUrl" class="btn-primary">Administrar productos</a>
                </div>
            </div>
            <div class="card overflow-hidden">
                <div v-for="item in criticalSettings" :key="item.key" class="flex justify-between border-b p-4 text-xs">
                    <span>{{ item.label }}</span>
                    <span :class="item.value === null ? 'text-amber-700' : 'text-emerald-700'">{{ item.value === null ? 'Sin configurar' : item.value }}</span>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
