<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConfigCard from '../../components/settings/ConfigCard.vue';
import ConfigSearch from '../../components/settings/ConfigSearch.vue';
import ConfigSection from '../../components/settings/ConfigSection.vue';
import ConfigStatus from '../../components/settings/ConfigStatus.vue';
import SettingsTabs from '../../components/ui/SettingsTabs.vue';
import { usePermissions } from '../../composables/usePermissions.js';

const { canManage, canFull } = usePermissions();
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
    theme: 'day',
    primary_color: '#1d4ed8',
    sidebar_color: '#ffffff',
    accent_color: '#0f766e',
    background_color: '#f3f5f8',
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
    if (form.processing || form.theme === theme) return;

    Object.entries(themePresets[theme] || themePresets.night).forEach(([key, value]) => {
        form[key] = value;
    });
    previewTheme(theme);
    form.clearErrors();
    form.put(props.update, { preserveScroll: true, preserveState: true });
};

const save = (files = false) => form.put(props.update, { forceFormData: files, preserveScroll: true });
const fields = {
    general: [['institution_name', 'Nombre de la institución'], ['legal_name', 'Razón social'], ['tax_id', 'Identificación tributaria'], ['phone', 'Teléfono'], ['email', 'Correo'], ['address', 'Dirección']],
    appearance: [['primary_color', 'Color principal', 'color'], ['sidebar_color', 'Barra lateral', 'color'], ['accent_color', 'Acento', 'color'], ['background_color', 'Fondo', 'color']],
    brand: [['system_name', 'Nombre del sistema'], ['system_tagline', 'Descripción']],
    accounting: [['cash_account_id', 'Cuenta de caja'], ['loan_receivable_account_id', 'Cartera por cobrar'], ['interest_income_account_id', 'Ingresos por interés'], ['fee_income_account_id', 'Ingresos por cargos'], ['delinquency_income_account_id', 'Ingresos por mora']],
};

const configSearch = ref('');
const sectionKey = url => url?.split('?')[0].split('/').filter(Boolean).pop();
const urlFor = key => props.tabs?.find(tab => sectionKey(tab.url) === key)?.url || '#';
const configurationGroups = computed(() => [
    {
        title: 'General',
        description: 'Identidad visual y datos institucionales.',
        items: [
            { key: 'brand', title: 'Marca', description: 'Nombre, descripción y logotipo del sistema.', icon: 'brand' },
            { key: 'appearance', title: 'Apariencia', description: 'Tema, paleta, tipografía y densidad visual.', icon: 'appearance' },
            { key: 'general', title: 'Institución', description: 'Información legal y datos de contacto.', icon: 'institution' },
        ],
    },
    {
        title: 'Usuarios y seguridad',
        description: 'Accesos, perfiles y alcance operativo.',
        items: [
            { key: 'users', title: 'Usuarios', description: 'Cuentas activas, credenciales y vinculación.', icon: 'users' },
            { key: 'permissions', title: 'Roles y permisos', description: 'Permisos por rol y excepciones individuales.', icon: 'permissions' },
        ],
    },
    {
        title: 'Sistema',
        description: 'Disponibilidad y numeración de los módulos.',
        items: [
            { key: 'modules', title: 'Módulos', description: 'Activa, oculta y ordena funciones del sistema.', icon: 'modules' },
            { key: 'sequences', title: 'Consecutivos', description: 'Prefijos y formatos de documentos.', icon: 'sequences' },
        ],
    },
    {
        title: 'Financiera',
        description: 'Productos y parámetros financieros explícitos.',
        items: [
            { key: 'financial', title: 'Configuración financiera', description: 'Productos, tasas y parámetros críticos.', icon: 'financial' },
        ],
    },
    {
        title: 'Contabilidad',
        description: 'Integración con el catálogo contable.',
        items: [
            { key: 'accounting', title: 'Configuración contable', description: 'Cuentas utilizadas en cada operación.', icon: 'accounting' },
        ],
    },
]);
const allConfigurationItems = computed(() => configurationGroups.value.flatMap(group => group.items));
const normalizeSearch = value => value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const filteredGroups = computed(() => {
    const query = normalizeSearch(configSearch.value.trim());
    if (!query) return configurationGroups.value;

    return configurationGroups.value
        .map(group => ({
            ...group,
            items: group.items.filter(item => normalizeSearch(group.title + ' ' + item.title + ' ' + item.description).includes(query)),
        }))
        .filter(group => group.items.length);
});
const resultCount = computed(() => filteredGroups.value.reduce((total, group) => total + group.items.length, 0));
const configuredCount = computed(() => allConfigurationItems.value.filter(item => Boolean(props.checks?.[item.key])).length);
const pendingItems = computed(() => allConfigurationItems.value.filter(item => !props.checks?.[item.key]));
const completion = computed(() => Math.round((configuredCount.value / Math.max(allConfigurationItems.value.length, 1)) * 100));
const statusFor = item => {
    if (props.checks?.[item.key]) return { label: 'Configurado', tone: 'configured' };
    if (item.key === 'modules') return { label: 'Inactivo', tone: 'inactive' };
    return { label: 'Pendiente', tone: 'pending' };
};
const alerts = computed(() => [
    !props.checks?.accounting && { key: 'accounting', label: 'Falta completar la integración contable.' },
    !props.checks?.financial && { key: 'financial', label: 'No hay productos financieros activos.' },
    !props.checks?.permissions && { key: 'permissions', label: 'Los roles y permisos aún no están configurados.' },
    !props.checks?.users && { key: 'users', label: 'No hay usuarios registrados para operar.' },
].filter(Boolean));
</script>
<template>
    <AppLayout title="Configuración" eyebrow="Administración" description="Parámetros del sistema sin reglas financieras implícitas.">
        <SettingsTabs :tabs="tabs"/>
        <section v-if="section==='index'" class="config-center">
            <div class="config-overview">
                <div class="config-overview__intro">
                    <p class="section-kicker">Centro de administración</p>
                    <h2>Estado general de la configuración</h2>
                    <p>Administra la identidad, los accesos y los parámetros operativos desde un solo lugar.</p>
                </div>
                <div class="config-progress">
                    <div class="config-progress__ring" :style="{ '--progress': completion * 3.6 + 'deg' }">
                        <span>{{ completion }}%</span>
                    </div>
                    <div>
                        <strong>{{ configuredCount }} de {{ allConfigurationItems.length }}</strong>
                        <small>configuraciones completadas</small>
                    </div>
                </div>
            </div>

            <div class="config-metrics">
                <article>
                    <span>Completadas</span>
                    <strong>{{ configuredCount }}</strong>
                    <ConfigStatus label="Configurado" tone="configured" />
                </article>
                <article>
                    <span>Configuraciones pendientes</span>
                    <strong>{{ pendingItems.length }}</strong>
                    <ConfigStatus :label="pendingItems.length ? 'Pendiente' : 'Al día'" :tone="pendingItems.length ? 'pending' : 'configured'" />
                </article>
                <article>
                    <span>Alertas importantes</span>
                    <strong>{{ alerts.length }}</strong>
                    <ConfigStatus :label="alerts.length ? 'Atención' : 'Sin alertas'" :tone="alerts.length ? 'alert' : 'configured'" />
                </article>
            </div>

            <ConfigSearch v-model="configSearch" :result-count="resultCount" />

            <div class="config-center__layout">
                <div class="config-center__sections">
                    <ConfigSection
                        v-for="group in filteredGroups"
                        :key="group.title"
                        :title="group.title"
                        :description="group.description"
                        :count="group.items.length"
                    >
                        <ConfigCard
                            v-for="item in group.items"
                            :key="item.key"
                            :title="item.title"
                            :description="item.description"
                            :icon="item.icon"
                            :href="urlFor(item.key)"
                            :status="statusFor(item).label"
                            :status-tone="statusFor(item).tone"
                        />
                    </ConfigSection>
                    <div v-if="!filteredGroups.length" class="config-empty">
                        <strong>Sin resultados</strong>
                        <p>No encontramos una configuración que coincida con “{{ configSearch }}”.</p>
                        <button type="button" @click="configSearch = ''">Limpiar búsqueda</button>
                    </div>
                </div>

                <aside class="config-center__aside">
                    <section class="config-insight">
                        <header>
                            <div>
                                <p class="section-kicker">Prioridad</p>
                                <h2>Alertas importantes</h2>
                            </div>
                            <span>{{ alerts.length }}</span>
                        </header>
                        <div v-if="alerts.length" class="config-alerts">
                            <a v-for="alert in alerts" :key="alert.key" :href="urlFor(alert.key)">
                                <i aria-hidden="true">!</i>
                                <span>{{ alert.label }}</span>
                                <b aria-hidden="true">→</b>
                            </a>
                        </div>
                        <div v-else class="config-insight__empty">
                            <span aria-hidden="true">✓</span>
                            <div><strong>Todo en orden</strong><p>No hay alertas importantes en la configuración actual.</p></div>
                        </div>
                    </section>

                    <section class="config-insight">
                        <header>
                            <div>
                                <p class="section-kicker">Seguimiento</p>
                                <h2>Actividad reciente</h2>
                            </div>
                        </header>
                        <div class="config-insight__empty">
                            <span aria-hidden="true">↻</span>
                            <div>
                                <strong>Sin actividad disponible</strong>
                                <p>Esta pantalla aún no recibe eventos de auditoría recientes.</p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
        <form v-else-if="['general','appearance','brand','accounting'].includes(section)" class="card p-5" novalidate @submit.prevent="save(section==='brand')">
            <div v-if="section==='appearance'" class="theme-picker">
                <p class="theme-picker-kicker">Tu tema personal</p>
                <p class="theme-picker-copy">Solo cambia tu vista. Cada usuario conserva su propio tema, paleta y estilo.</p>
                <div class="theme-picker-grid">
                    <button type="button" class="theme-swatch is-night" :class="{ 'is-selected': (form.theme || 'night') === 'night' }" :disabled="form.processing" @click="applyTheme('night')">
                        <span class="theme-swatch-preview" aria-hidden="true"></span>
                        <strong>Sala de control</strong>
                        <small>Oscuro, vidrio, acento eléctrico</small>
                    </button>
                    <button type="button" class="theme-swatch is-day" :class="{ 'is-selected': form.theme === 'day' }" :disabled="form.processing" @click="applyTheme('day')">
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
            <button v-if="section === 'appearance' || canFull('settings')" type="submit" class="btn-primary mt-5" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</button>
        </form>
        <form v-else-if="section==='modules'" class="card overflow-hidden" @submit.prevent="save(false)">
            <div v-for="module in modules" :key="module.id" class="grid grid-cols-[1fr_auto_auto_90px] items-center gap-3 border-b p-4">
                <div><p class="font-semibold">{{ module.name }}</p><p class="text-[10px] text-slate-400">{{ module.key }}</p></div>
                <label class="text-xs"><input v-model="form.modules[module.id].enabled" type="checkbox"> Activo</label>
                <label class="text-xs"><input v-model="form.modules[module.id].visible" type="checkbox"> Visible</label>
                <input v-model="form.modules[module.id].sort_order" type="number" class="control">
            </div>
            <div v-if="canFull('settings')" class="p-4"><button class="btn-primary">Aplicar módulos</button></div>
        </form>
        <form v-else-if="section==='sequences'" class="card overflow-hidden" @submit.prevent="save(false)">
            <div v-for="sequence in sequences" :key="sequence.key" class="grid gap-3 border-b p-4 sm:grid-cols-[1fr_180px_120px]">
                <div><p class="font-semibold">{{ sequence.key }}</p><p class="text-xs text-slate-400">Siguiente: {{ sequence.next_number }}</p></div>
                <label class="field-label">Prefijo<input v-model="form.sequences[sequence.key].prefix" class="control"></label>
                <label class="field-label">Dígitos<input v-model="form.sequences[sequence.key].padding" type="number" class="control"></label>
            </div>
            <div v-if="canFull('settings')" class="p-4"><button class="btn-primary">Guardar formatos</button></div>
        </form>
        <section v-else-if="section==='financial'" class="space-y-4">
            <div class="card p-5">
                <div class="flex justify-between">
                    <div><h2 class="font-semibold">Productos crediticios</h2><p class="text-xs text-slate-400">{{ products.length }} configurados</p></div>
                    <a v-if="canFull('settings')" :href="productsUrl" class="btn-primary">Administrar productos</a>
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

<style scoped>
.config-center {
    display: grid;
    gap: 1rem;
}
.config-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    overflow: hidden;
    border: 1px solid var(--sv-line);
    border-radius: 1.25rem;
    padding: 1.25rem;
    background:
        radial-gradient(circle at 92% 0, color-mix(in srgb, var(--app-primary) 13%, transparent), transparent 42%),
        var(--sv-glass);
    box-shadow: 0 16px 36px rgba(0, 0, 0, .1);
}
.config-overview__intro { max-width: 39rem; }
.config-overview__intro h2 {
    margin-top: .28rem;
    color: var(--sv-text);
    font-size: clamp(1rem, 2vw, 1.28rem);
    font-weight: 800;
    letter-spacing: -.02em;
}
.config-overview__intro > p:last-child {
    margin-top: .4rem;
    color: var(--sv-muted);
    font-size: .72rem;
    line-height: 1.55;
}
.config-progress {
    display: flex;
    min-width: 14rem;
    align-items: center;
    gap: .8rem;
    border-left: 1px solid var(--sv-line);
    padding-left: 1.2rem;
}
.config-progress__ring {
    display: grid;
    width: 4.2rem;
    height: 4.2rem;
    flex: none;
    place-items: center;
    border-radius: 50%;
    background: conic-gradient(var(--app-primary) var(--progress), var(--sv-line) 0);
}
.config-progress__ring::before {
    grid-area: 1 / 1;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 50%;
    background: var(--sv-bg-2);
    content: '';
}
.config-progress__ring span {
    z-index: 1;
    grid-area: 1 / 1;
    color: var(--sv-text);
    font-size: .8rem;
    font-weight: 800;
}
.config-progress strong { display: block; color: var(--sv-text); font-size: .78rem; }
.config-progress small { display: block; margin-top: .12rem; color: var(--sv-muted); font-size: .62rem; line-height: 1.4; }
.config-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .75rem;
}
.config-metrics article {
    display: grid;
    min-width: 0;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: .5rem 1rem;
    border: 1px solid var(--sv-line);
    border-radius: 1rem;
    padding: .9rem 1rem;
    background: var(--sv-glass);
}
.config-metrics article > span:not(.config-status) { color: var(--sv-muted); font-size: .65rem; font-weight: 700; }
.config-metrics article > strong {
    grid-row: 1 / 3;
    grid-column: 2;
    color: var(--sv-text);
    font-size: 1.55rem;
    line-height: 1;
}
.config-center__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(16.5rem, 20rem);
    align-items: start;
    gap: 1rem;
}
.config-center__sections { display: grid; min-width: 0; gap: 1.35rem; }
.config-center__aside {
    display: grid;
    position: sticky;
    top: 5.5rem;
    gap: .85rem;
}
.config-insight {
    overflow: hidden;
    border: 1px solid var(--sv-line);
    border-radius: 1.05rem;
    background: var(--sv-glass);
}
.config-insight > header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .8rem;
    border-bottom: 1px solid var(--sv-line);
    padding: .9rem 1rem;
}
.config-insight h2 { margin-top: .15rem; color: var(--sv-text); font-size: .78rem; font-weight: 800; }
.config-insight header > span {
    display: grid;
    width: 1.7rem;
    height: 1.7rem;
    place-items: center;
    border-radius: .55rem;
    background: color-mix(in srgb, var(--sv-warn) 12%, transparent);
    color: var(--sv-warn);
    font-size: .65rem;
    font-weight: 800;
}
.config-alerts { display: grid; }
.config-alerts a {
    display: grid;
    grid-template-columns: 1.45rem 1fr auto;
    align-items: center;
    gap: .65rem;
    border-bottom: 1px solid var(--sv-line);
    padding: .8rem 1rem;
    color: var(--sv-text);
    font-size: .66rem;
    line-height: 1.45;
    text-decoration: none;
    transition: background .18s ease;
}
.config-alerts a:last-child { border-bottom: 0; }
.config-alerts a:hover { background: var(--sv-glass-2); }
.config-alerts i {
    display: grid;
    width: 1.45rem;
    height: 1.45rem;
    place-items: center;
    border-radius: .5rem;
    background: color-mix(in srgb, var(--sv-warn) 12%, transparent);
    color: var(--sv-warn);
    font-size: .7rem;
    font-style: normal;
    font-weight: 900;
}
.config-alerts b { color: var(--sv-muted); font-weight: 700; }
.config-insight__empty {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    padding: 1rem;
}
.config-insight__empty > span {
    display: grid;
    width: 1.8rem;
    height: 1.8rem;
    flex: none;
    place-items: center;
    border: 1px solid var(--sv-line);
    border-radius: .58rem;
    color: var(--sv-muted);
    font-size: .7rem;
}
.config-insight__empty strong { color: var(--sv-text); font-size: .68rem; }
.config-insight__empty p { margin-top: .2rem; color: var(--sv-muted); font-size: .63rem; line-height: 1.5; }
.config-empty {
    border: 1px dashed var(--sv-line);
    border-radius: 1rem;
    padding: 2.2rem 1rem;
    text-align: center;
}
.config-empty strong { color: var(--sv-text); font-size: .82rem; }
.config-empty p { margin-top: .3rem; color: var(--sv-muted); font-size: .68rem; }
.config-empty button {
    margin-top: .8rem;
    border: 1px solid var(--sv-line);
    border-radius: .65rem;
    padding: .5rem .75rem;
    color: var(--app-primary);
    font-size: .65rem;
    font-weight: 800;
}
@media (max-width: 1080px) {
    .config-center__layout { grid-template-columns: minmax(0, 1fr); }
    .config-center__aside { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
    .config-overview { align-items: flex-start; flex-direction: column; }
    .config-progress { width: 100%; border-top: 1px solid var(--sv-line); border-left: 0; padding-top: 1rem; padding-left: 0; }
    .config-metrics { grid-template-columns: minmax(0, 1fr); }
    .config-center__aside { grid-template-columns: minmax(0, 1fr); }
}
</style>
