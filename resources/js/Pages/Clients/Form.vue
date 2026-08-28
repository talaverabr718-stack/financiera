<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ClientCoordinates from '../../components/clients/ClientCoordinates.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ client: Object, sellers: Array, locations: Object, endpoints: Object, editing: Boolean });
const steps = [['Datos básicos'], ['Datos Económicos'], ['Bienes'], ['Observaciones']];
const assetTypes = { jewelry: 'Prenda / joya', vehicle: 'Vehículo', property: 'Propiedad', appliance: 'Electrodoméstico', machinery: 'Maquinaria', livestock: 'Ganado', inventory: 'Inventario', other: 'Otro' };
const housing = { owned: 'Propia', rented: 'Alquilada', family: 'Familiar', financed: 'Financiada', other: 'Otra' };
const step = ref(1);
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
const go = target => { if (target >= 1 && target <= 4) step.value = target; };
const next = () => { if (step.value < 4) step.value += 1; };
const openReview = () => { reviewOpen.value = true; };
const submit = () => form.transform(data => ({ ...data, assets: data.assets.filter(item => item.description) }))[props.editing ? 'put' : 'post'](props.endpoints.save);
</script>
<template>
    <AppLayout :title="editing ? 'Editar cliente' : 'Nuevo cliente'" eyebrow="Expediente digital" :description="editing ? 'Actualiza el expediente conservando el historial.' : 'Completa la información personal, ubicación, capacidad económica y bienes.'">
        <template #header-actions><a :href="endpoints.index" class="btn-secondary">Volver a clientes</a></template>
        <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><p class="font-semibold">Revisa la información:</p><ul class="mt-2 list-disc pl-5"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul></div>
        <div class="card mb-5 overflow-hidden p-3 sm:p-4">
            <div class="grid grid-cols-4 gap-1">
                <button v-for="(label, index) in steps" :key="label[0]" type="button" class="rounded-xl px-2 py-2.5 text-[10px] font-semibold sm:text-xs" :class="step === index + 1 ? 'bg-indigo-50 text-indigo-600' : index + 1 < step ? 'text-indigo-600' : 'text-slate-400'" @click="go(index + 1)">{{ label[0] }}</button>
            </div>
            <div class="mx-2 mt-2 h-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500 transition-all" :style="{ width: `${step * 25}%` }"></div></div>
        </div>
        <form class="space-y-5" @submit.prevent="step === 4 ? openReview() : next()">
            <section v-show="step === 1" class="card space-y-6 p-5">
                <div><h2 class="text-sm font-semibold">Información personal</h2><p class="text-[11px] text-slate-400">Identificación y datos de contacto</p></div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="field-label lg:col-span-2">Nombre completo *<input v-model="form.full_name" class="control" required></label>
                    <label class="field-label">Cédula *<input v-model="form.identity_number" class="control" placeholder="000-000000-0000A" maxlength="16" required @input="syncIdentity" @blur="syncIdentity" @keydown="onIdentityKeydown"><span class="mt-1 block text-[10px] text-slate-400">Formato nicaragüense; se valida la letra de control.</span></label>
                    <label class="field-label">Fecha de nacimiento *<input v-model="form.birth_date" id="birth-date" type="date" class="control" required><span class="mt-1 block text-[10px] text-slate-400">Se completa con los seis dígitos centrales de la cédula.</span></label>
                    <label class="field-label">Teléfono<input v-model="form.phone" class="control"></label>
                    <label class="field-label">Correo electrónico<input v-model="form.email" type="email" class="control"></label>
                </div>
                <div><h2 class="text-sm font-semibold">Ubicación</h2><p class="text-[11px] text-slate-400">Dirección para visitas y cobranza</p></div>
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="field-label">Departamento *<select v-model="form.department" class="control" required><option v-for="(item, name) in locations" :key="name" :value="name">{{ name }}</option></select></label>
                    <label class="field-label">Municipio *<select v-model="form.municipality" class="control" required><option v-for="name in municipalities" :key="name" :value="name">{{ name }}</option></select></label>
                    <label class="field-label">Barrio o comunidad *<select v-model="form.neighborhood" class="control" required><option v-for="name in neighborhoods" :key="name" :value="name">{{ name }}</option></select></label>
                    <label class="field-label md:col-span-3">Dirección detallada *<textarea v-model="form.address" rows="3" class="control" required></textarea></label>
                </div>
                <ClientCoordinates v-model:latitude="form.latitude" v-model:longitude="form.longitude" />
            </section>
            <section v-show="step === 2" class="card p-5">
                <h2 class="text-sm font-semibold">Trabajo y capacidad económica</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="field-label">Ocupación<input v-model="form.economic_activity" class="control"></label>
                    <label class="field-label">Empresa, trabajo o negocio<input v-model="form.workplace" class="control"></label>
                    <label class="field-label">Cargo u ocupación<input v-model="form.job_position" class="control"></label>
                    <label class="field-label lg:col-span-2">Dirección del trabajo o negocio<input v-model="form.workplace_address" class="control"></label>
                    <div class="field-label">
                        <span>Antigüedad laboral</span>
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                            <input v-model.number="employmentDurationValue" type="number" min="0" step="1" inputmode="numeric" class="control" placeholder="Cantidad">
                            <select v-model="employmentDurationUnit" class="control min-w-28" aria-label="Unidad de antigüedad laboral">
                                <option value="months">Meses</option>
                                <option value="years">Años</option>
                            </select>
                        </div>
                    </div>
                    <label class="field-label">Ingresos mensuales *<input v-model="form.estimated_income" type="number" step="0.01" min="0" class="control" required></label>
                    <label class="field-label">Otros ingresos<input v-model="form.other_income" type="number" step="0.01" min="0" class="control"></label>
                    <label class="field-label">Gastos mensuales *<input v-model="form.estimated_expenses" type="number" step="0.01" min="0" class="control" required></label>
                    <label class="field-label">Vivienda<select v-model="form.housing_status" class="control"><option value="">Seleccionar</option><option v-for="(label, value) in housing" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="field-label">Personas dependientes<input v-model="form.dependents" type="number" min="0" class="control"></label>
                    <label class="field-label">Estado<select v-model="form.status" class="control"><option value="active">Activo</option><option value="inactive">Inactivo</option><option value="blocked">Bloqueado</option></select></label>
                    <label v-if="!editing" class="field-label">Vendedor responsable *<select v-model="form.seller_id" name="seller_id" class="control" required><option value="">Seleccionar</option><option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.display_name }} · {{ seller.code }}</option></select></label>
                </div>
            </section>
            <section v-show="step === 3" class="card overflow-hidden">
                <div class="flex items-center justify-between border-b p-5"><div><h2 class="text-sm font-semibold">Pertenencias y bienes</h2><p class="mt-1 text-[11px] text-slate-400">Prendas, vehículos, propiedades u otros bienes declarados.</p></div><button type="button" class="rounded-lg bg-indigo-50 px-3 py-2 text-[11px] font-semibold text-indigo-600" @click="addAsset">Agregar bien</button></div>
                <div class="divide-y">
                    <div v-for="(asset, index) in form.assets" :key="index" class="grid gap-4 p-5 md:grid-cols-5">
                        <label class="field-label">Tipo<select v-model="asset.type" class="control"><option v-for="(label, value) in assetTypes" :key="value" :value="value">{{ label }}</option></select></label>
                        <label class="field-label md:col-span-2">Descripción<input v-model="asset.description" class="control"></label>
                        <label class="field-label">Valor estimado<input v-model="asset.estimated_value" type="number" min="0" step="0.01" class="control"></label>
                        <div class="flex items-end"><button type="button" class="text-[11px] font-semibold text-rose-500" @click="removeAsset(index)">Eliminar</button></div>
                    </div>
                    <p v-if="!form.assets.length" class="p-5 text-xs text-slate-400">Sin bienes declarados. Puedes continuar.</p>
                </div>
            </section>
            <section v-show="step === 4" class="card p-5">
                <label class="field-label">Observaciones generales<textarea v-model="form.notes" rows="3" class="control"></textarea></label>
                <label class="mt-4 flex items-start gap-2 text-xs text-slate-500"><input v-model="form.confirm_duplicate" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-500">Confirmo que revisé posibles coincidencias de teléfono y deseo continuar.</label>
            </section>
            <div class="sticky bottom-3 z-20 flex items-center justify-between rounded-2xl border bg-white/95 p-3 shadow-xl">
                <a :href="endpoints.index" class="btn-secondary text-rose-600">Cancelar</a>
                <div class="flex gap-2">
                    <button v-if="step > 1" type="button" class="btn-secondary" @click="go(step - 1)">Anterior</button>
                    <button v-if="step < 4" type="submit" class="btn-primary">Continuar</button>
                    <button v-else type="submit" class="btn-primary">{{ editing ? 'Revisar cambios' : 'Revisar y registrar' }}</button>
                </div>
            </div>
        </form>
        <BaseModal :open="reviewOpen" title="Revisar expediente" description="Confirma los datos principales antes de guardar." size="max-w-2xl" @close="reviewOpen = false">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Nombre completo</p><p class="mt-1 text-xs font-semibold">{{ form.full_name || '—' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Cédula</p><p class="mt-1 text-xs font-semibold">{{ form.identity_number || '—' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Teléfono</p><p class="mt-1 text-xs font-semibold">{{ form.phone || '—' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Ubicación</p><p class="mt-1 text-xs font-semibold">{{ [form.neighborhood, form.municipality].filter(Boolean).join(', ') || '—' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Ingresos mensuales</p><p class="mt-1 text-xs font-semibold">C$ {{ money(form.estimated_income) }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] uppercase text-slate-400">Gastos mensuales</p><p class="mt-1 text-xs font-semibold">C$ {{ money(form.estimated_expenses) }}</p></div>
            </div>
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-800">Al confirmar se guardará el expediente. Podrás editar los datos posteriormente conservando el historial.</p>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="reviewOpen = false">Volver a editar</button>
                    <button type="button" class="btn-primary" :disabled="form.processing" @click="submit">{{ form.processing ? 'Guardando…' : 'Confirmar y guardar' }}</button>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
