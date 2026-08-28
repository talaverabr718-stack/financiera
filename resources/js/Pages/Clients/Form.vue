<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ClientCoordinates from '../../components/clients/ClientCoordinates.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ client: Object, sellers: Array, locations: Object, endpoints: Object, editing: Boolean });
const steps = [
    { id: 1, title: 'Datos básicos', hint: 'Nombre, cédula y ubicación', fields: ['full_name', 'identity_number', 'birth_date', 'department', 'municipality', 'neighborhood', 'address'] },
    { id: 2, title: 'Datos económicos', hint: 'Trabajo, ingresos y vendedor', fields: ['estimated_income', 'estimated_expenses'] },
    { id: 3, title: 'Bienes', hint: 'Pertenencias declaradas', fields: [] },
    { id: 4, title: 'Observaciones', hint: 'Notas y confirmaciones', fields: [] },
];
const assetTypes = { jewelry: 'Prenda / joya', vehicle: 'Vehículo', property: 'Propiedad', appliance: 'Electrodoméstico', machinery: 'Maquinaria', livestock: 'Ganado', inventory: 'Inventario', other: 'Otro' };
const housing = { owned: 'Propia', rented: 'Alquilada', family: 'Familiar', financed: 'Financiada', other: 'Otra' };
const reviewOpen = ref(false);
const storedEmploymentDuration = Number(props.client.employment_duration_months ?? 0);
const employmentDurationUnit = ref(storedEmploymentDuration > 0 && storedEmploymentDuration % 12 === 0 ? 'years' : 'months');
const employmentDurationValue = ref(
    storedEmploymentDuration > 0
        ? (employmentDurationUnit.value === 'years' ? storedEmploymentDuration / 12 : storedEmploymentDuration)
        : '',
);
const form = useForm({
    full_name: props.client.full_name ?? '',
    identity_number: props.client.identity_number ?? '',
    birth_date: props.client.birth_date ?? '',
    phone: props.client.phone ?? '',
    email: props.client.email ?? '',
    department: props.client.department || 'Estelí',
    municipality: props.client.municipality || 'Estelí',
    neighborhood: props.client.neighborhood ?? '',
    address: props.client.address ?? '',
    latitude: props.client.latitude ?? '',
    longitude: props.client.longitude ?? '',
    economic_activity: props.client.economic_activity ?? '',
    workplace: props.client.workplace ?? '',
    job_position: props.client.job_position ?? '',
    workplace_address: props.client.workplace_address ?? '',
    employment_duration_months: props.client.employment_duration_months ?? '',
    estimated_income: props.client.estimated_income ?? '',
    other_income: props.client.other_income ?? 0,
    estimated_expenses: props.client.estimated_expenses ?? '',
    housing_status: props.client.housing_status ?? '',
    dependents: props.client.dependents ?? 0,
    status: props.client.status ?? 'active',
    seller_id: props.client.seller_id ?? '',
    notes: props.client.notes ?? '',
    confirm_duplicate: false,
    assets: props.client.assets?.length ? props.client.assets : [],
});
const municipalities = computed(() => Object.keys(props.locations[form.department] ?? {}));
const neighborhoods = computed(() => props.locations[form.department]?.[form.municipality] ?? []);
const money = value => new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2 }).format(Number(value || 0));
const filled = value => String(value ?? '').trim() !== '';
const stepDone = step => {
    if (step.id === 1) return step.fields.every(field => filled(form[field]));
    if (step.id === 2) return step.fields.every(field => filled(form[field])) && (props.editing || filled(form.seller_id));
    if (step.id === 3) return form.assets.some(item => filled(item.description));
    return filled(form.notes) || form.confirm_duplicate;
};

const formatIdentityNumber = () => {
    const compact = String(form.identity_number).toUpperCase().replace(/[^0-9A-Z]/g, '');
    const digits = compact.replace(/\D/g, '').slice(0, 13);
    const letter = digits.length === 13 ? (compact.match(/[A-Z]/)?.[0] ?? '') : '';
    let formatted = digits.slice(0, 3);
    if (digits.length >= 3) formatted += `-${digits.slice(3, 9)}`;
    if (digits.length >= 9) formatted += `-${digits.slice(9, 13)}`;
    form.identity_number = formatted + letter;
};
const fillBirthDateFromIdentity = () => {
    const digits = String(form.identity_number).replace(/\D/g, '');
    if (digits.length < 9) return;
    const encodedDate = digits.slice(3, 9);
    const day = Number(encodedDate.slice(0, 2));
    const month = Number(encodedDate.slice(2, 4));
    const shortYear = Number(encodedDate.slice(4, 6));
    const now = new Date();
    let year = Math.floor(now.getFullYear() / 100) * 100 + shortYear;
    let candidate = new Date(year, month - 1, day);
    if (candidate > now) { year -= 100; candidate = new Date(year, month - 1, day); }
    if (candidate.getFullYear() !== year || candidate.getMonth() !== month - 1 || candidate.getDate() !== day) return;
    form.birth_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};
const syncIdentity = () => { formatIdentityNumber(); fillBirthDateFromIdentity(); };
const onIdentityKeydown = event => {
    const input = event.target;
    const start = input.selectionStart ?? 0;
    const end = input.selectionEnd ?? start;
    if (start !== end) return;
    if (event.key === 'Backspace' && form.identity_number[start - 1] === '-') {
        event.preventDefault();
        form.identity_number = form.identity_number.slice(0, Math.max(0, start - 2)) + form.identity_number.slice(start);
        syncIdentity();
    } else if (event.key === 'Delete' && form.identity_number[start] === '-') {
        event.preventDefault();
        form.identity_number = form.identity_number.slice(0, start) + form.identity_number.slice(start + 2);
        syncIdentity();
    }
};

watch(() => form.department, () => { form.municipality = municipalities.value[0] ?? ''; form.neighborhood = neighborhoods.value[0] ?? ''; });
watch(() => form.municipality, () => { if (!neighborhoods.value.includes(form.neighborhood)) form.neighborhood = neighborhoods.value[0] ?? ''; });
watch([employmentDurationValue, employmentDurationUnit], ([value, unit]) => {
    form.employment_duration_months = value === '' || value === null
        ? ''
        : Number(value) * (unit === 'years' ? 12 : 1);
}, { immediate: true });

const addAsset = () => form.assets.push({ type: 'jewelry', description: '', estimated_value: '', ownership_status: 'owned' });
const removeAsset = index => form.assets.splice(index, 1);
const openReview = () => {
    const missing = steps.find(step => (step.id === 1 || step.id === 2) && !stepDone(step));
    if (missing) {
        document.getElementById(`client-block-${missing.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }
    reviewOpen.value = true;
};
const submit = () => form.transform(data => ({ ...data, assets: data.assets.filter(item => item.description) }))[props.editing ? 'put' : 'post'](props.endpoints.save);
</script>
<template>
    <AppLayout hide-header :title="editing ? 'Editar cliente' : 'Nuevo cliente'">
        <section class="card client-form-hub overflow-hidden">
            <header class="client-form-hub-head">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-500">Expediente digital</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ editing ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
                    <p class="mt-1.5 max-w-xl text-sm text-slate-500">Un solo panel. Completa cada bloque y revisa antes de guardar.</p>
                </div>
                <a :href="endpoints.index" class="btn-secondary shrink-0">Volver a clientes</a>
            </header>
            <div v-if="Object.keys(form.errors).length" class="mx-5 mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-semibold">Revisa la información:</p>
                <ul class="mt-2 list-disc pl-5"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul>
            </div>

            <div class="client-form-blocks">
                <div id="client-block-1" class="client-form-block" :data-done="stepDone(steps[0])">
                    <header class="client-form-block-head">
                        <span>01</span>
                        <div>
                            <strong>Datos básicos</strong>
                            <small>Identificación, contacto y ubicación para cobranza.</small>
                        </div>
                        <em>{{ stepDone(steps[0]) ? 'Completado' : 'Requerido' }}</em>
                    </header>
                    <div class="client-form-grid">
                        <label class="field-label client-form-span-2"><span class="field-caption">Nombre *</span><input v-model="form.full_name" class="control" required></label>
                        <label class="field-label"><span class="field-caption">Cédula *</span><input v-model="form.identity_number" class="control" placeholder="000-000000-0000A" maxlength="16" required @input="syncIdentity" @blur="syncIdentity" @keydown="onIdentityKeydown"><span class="field-hint">Formato nicaragüense; se valida la letra de control.</span></label>
                        <label class="field-label"><span class="field-caption">Nacimiento *</span><input v-model="form.birth_date" id="birth-date" type="date" class="control" required><span class="field-hint">Se completa con los seis dígitos centrales de la cédula.</span></label>
                        <label class="field-label"><span class="field-caption">Teléfono</span><input v-model="form.phone" class="control"></label>
                        <label class="field-label"><span class="field-caption">Correo</span><input v-model="form.email" type="email" class="control"></label>
                        <label class="field-label"><span class="field-caption">Depto. *</span><select v-model="form.department" class="control" required><option v-for="(item, name) in locations" :key="name" :value="name">{{ name }}</option></select></label>
                        <label class="field-label"><span class="field-caption">Municipio *</span><select v-model="form.municipality" class="control" required><option v-for="name in municipalities" :key="name" :value="name">{{ name }}</option></select></label>
                        <label class="field-label client-form-span-2"><span class="field-caption">Barrio *</span><select v-model="form.neighborhood" class="control" required><option v-for="name in neighborhoods" :key="name" :value="name">{{ name }}</option></select></label>
                        <label class="field-label client-form-span-2"><span class="field-caption">Dirección *</span><textarea v-model="form.address" rows="2" class="control" required></textarea></label>
                    </div>
                    <ClientCoordinates v-model:latitude="form.latitude" v-model:longitude="form.longitude" />
                </div>

                <div id="client-block-2" class="client-form-block" :data-done="stepDone(steps[1])">
                    <header class="client-form-block-head">
                        <span>02</span>
                        <div>
                            <strong>Datos económicos</strong>
                            <small>Capacidad de pago y vendedor responsable.</small>
                        </div>
                        <em>{{ stepDone(steps[1]) ? 'Completado' : 'Requerido' }}</em>
                    </header>
                    <div class="client-form-grid">
                        <label class="field-label"><span class="field-caption">Ocupación</span><input v-model="form.economic_activity" class="control"></label>
                        <label class="field-label"><span class="field-caption">Empresa</span><input v-model="form.workplace" class="control"></label>
                        <label class="field-label"><span class="field-caption">Cargo</span><input v-model="form.job_position" class="control"></label>
                        <label class="field-label"><span class="field-caption">Dir. trabajo</span><input v-model="form.workplace_address" class="control"></label>
                        <div class="field-label">
                            <span class="field-caption">Antigüedad</span>
                            <div class="field-inline">
                                <input v-model.number="employmentDurationValue" type="number" min="0" step="1" inputmode="numeric" class="control" placeholder="Cantidad">
                                <select v-model="employmentDurationUnit" class="control" aria-label="Unidad de antigüedad laboral">
                                    <option value="months">Meses</option>
                                    <option value="years">Años</option>
                                </select>
                            </div>
                        </div>
                        <label class="field-label"><span class="field-caption">Ingresos *</span><input v-model="form.estimated_income" type="number" step="0.01" min="0" class="control" required></label>
                        <label class="field-label"><span class="field-caption">Otros ing.</span><input v-model="form.other_income" type="number" step="0.01" min="0" class="control"></label>
                        <label class="field-label"><span class="field-caption">Gastos *</span><input v-model="form.estimated_expenses" type="number" step="0.01" min="0" class="control" required></label>
                        <label class="field-label"><span class="field-caption">Vivienda</span><select v-model="form.housing_status" class="control"><option value="">Seleccionar</option><option v-for="(label, value) in housing" :key="value" :value="value">{{ label }}</option></select></label>
                        <label class="field-label"><span class="field-caption">Dependientes</span><input v-model="form.dependents" type="number" min="0" class="control"></label>
                        <label class="field-label"><span class="field-caption">Estado</span><select v-model="form.status" class="control"><option value="active">Activo</option><option value="inactive">Inactivo</option><option value="blocked">Bloqueado</option></select></label>
                        <label v-if="!editing" class="field-label client-form-span-2"><span class="field-caption">Vendedor *</span><select v-model="form.seller_id" name="seller_id" class="control" required><option value="">Seleccionar</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.display_name }} · {{ seller.code }}</option></select></label>
                    </div>
                </div>

                <div id="client-block-3" class="client-form-block" :data-done="stepDone(steps[2])">
                    <header class="client-form-block-head">
                        <span>03</span>
                        <div>
                            <strong>Bienes</strong>
                            <small>Prendas, vehículos u otros bienes declarados. Opcional.</small>
                        </div>
                        <em>{{ stepDone(steps[2]) ? 'Con bienes' : 'Opcional' }}</em>
                    </header>
                    <div class="client-form-asset-toolbar">
                        <p>Opcional. Puedes dejarlo vacío.</p>
                        <button type="button" class="btn-soft" @click="addAsset">Agregar bien</button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(asset, index) in form.assets" :key="index" class="client-form-asset">
                            <label class="field-label"><span class="field-caption">Tipo</span><select v-model="asset.type" class="control"><option v-for="(label, value) in assetTypes" :key="value" :value="value">{{ label }}</option></select></label>
                            <label class="field-label client-form-asset-desc"><span class="field-caption">Descripción</span><input v-model="asset.description" class="control"></label>
                            <label class="field-label"><span class="field-caption">Valor</span><input v-model="asset.estimated_value" type="number" min="0" step="0.01" class="control"></label>
                            <button type="button" class="client-form-asset-remove" @click="removeAsset(index)">Quitar</button>
                        </div>
                        <p v-if="!form.assets.length" class="client-form-empty">Sin bienes declarados.</p>
                    </div>
                </div>

                <div id="client-block-4" class="client-form-block" :data-done="stepDone(steps[3])">
                    <header class="client-form-block-head">
                        <span>04</span>
                        <div>
                            <strong>Observaciones</strong>
                            <small>Notas del expediente y confirmación de coincidencias.</small>
                        </div>
                        <em>{{ stepDone(steps[3]) ? 'Con notas' : 'Opcional' }}</em>
                    </header>
                    <div class="client-form-notes">
                        <label class="field-label"><span class="field-caption">Notas</span><textarea v-model="form.notes" rows="3" class="control"></textarea></label>
                        <label class="client-form-check"><input v-model="form.confirm_duplicate" type="checkbox">Confirmo que revisé posibles coincidencias de teléfono y deseo continuar.</label>
                    </div>
                </div>
            </div>

            <footer class="client-form-hub-foot">
                <a :href="endpoints.index" class="btn-secondary text-rose-600">Cancelar</a>
                <button type="button" class="btn-primary" @click="openReview">{{ editing ? 'Revisar cambios' : 'Revisar y registrar' }}</button>
            </footer>
        </section>

        <BaseModal :open="reviewOpen" title="Revisar expediente" description="Confirma los datos principales antes de guardar." size="client-form-modal" @close="reviewOpen = false">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Nombre completo</p><p class="mt-1 text-xs font-semibold">{{ form.full_name || '—' }}</p></div>
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Cédula</p><p class="mt-1 text-xs font-semibold">{{ form.identity_number || '—' }}</p></div>
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Teléfono</p><p class="mt-1 text-xs font-semibold">{{ form.phone || '—' }}</p></div>
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Ubicación</p><p class="mt-1 text-xs font-semibold">{{ [form.neighborhood, form.municipality].filter(Boolean).join(', ') || '—' }}</p></div>
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Ingresos mensuales</p><p class="mt-1 text-xs font-semibold">C$ {{ money(form.estimated_income) }}</p></div>
                <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase text-slate-400">Gastos mensuales</p><p class="mt-1 text-xs font-semibold">C$ {{ money(form.estimated_expenses) }}</p></div>
            </div>
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-800">Al confirmar se guardará el expediente. Podrás editar los datos posteriormente conservando el historial.</p>
            <template #footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary w-full sm:w-auto" @click="reviewOpen = false">Volver a editar</button>
                    <button type="button" class="btn-primary w-full sm:w-auto" :disabled="form.processing" @click="submit">{{ form.processing ? 'Guardando…' : 'Confirmar y guardar' }}</button>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
