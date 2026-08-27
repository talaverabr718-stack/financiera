<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';

const props = defineProps({ endpoint: String, csrf: String, selectId: { type: String, default: 'product' } });
const open = ref(false);
const saving = ref(false);
const errors = ref([]);
const saved = ref(false);
const blank = () => ({ code: '', name: '', currency: 'NIO', minimum_term: '', maximum_term: '', allowed_frequencies: [], allowed_interest_methods: [], default_interest_rate: '', default_interest_method: '', default_administrative_fee: '', delinquency_method: '', delinquency_rate: '', payment_allocation_order: ['', '', '', ''], is_active: true });
const product = reactive(blank());
const allocationOptions = [{ value: 'delinquency', label: 'Mora' }, { value: 'fees', label: 'Cargos' }, { value: 'interest', label: 'Interés' }, { value: 'principal', label: 'Principal' }];
const canSave = computed(() => product.code.trim() && product.name.trim() && product.minimum_term && product.maximum_term && product.allowed_frequencies.length && product.allowed_interest_methods.length && product.default_administrative_fee !== '' && product.payment_allocation_order.every(Boolean) && new Set(product.payment_allocation_order).size === 4);
let toastTimer;

const close = () => { open.value = false; errors.value = []; };
const show = () => { open.value = true; saved.value = false; };
const reset = () => Object.assign(product, blank());
const save = async () => {
    if (!canSave.value || saving.value) return;
    saving.value = true; errors.value = [];
    try {
        const response = await fetch(props.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf }, body: JSON.stringify({ ...product, default_interest_rate: product.default_interest_rate || null, default_interest_method: product.default_interest_method || null, delinquency_method: product.delinquency_method || null, delinquency_rate: product.delinquency_rate || null }) });
        const data = await response.json();
        if (!response.ok) throw data;
        close();
        const select = document.getElementById(props.selectId);
        const option = new Option(data.product.name, data.product.id, true, true);
        option.dataset.currency = data.product.currency || '';
        option.dataset.rate = data.product.default_interest_rate || '';
        option.dataset.method = data.product.default_interest_method || '';
        option.dataset.fee = data.product.default_administrative_fee || '0';
        select?.add(option); select?.dispatchEvent(new Event('change'));
        reset(); saved.value = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => saved.value = false, 2800);
    } catch (error) {
        errors.value = Object.values(error.errors ?? { error: [error.message ?? 'No fue posible guardar el producto.'] }).flat();
    } finally { saving.value = false; }
};

watch(open, value => { document.body.style.overflow = value ? 'hidden' : ''; });
onBeforeUnmount(() => { clearTimeout(toastTimer); document.body.style.overflow = ''; });
</script>

<template>
    <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-indigo-600 text-lg font-bold text-white shadow-md shadow-indigo-200 transition hover:-translate-y-0.5 hover:bg-indigo-700" title="Agregar producto" aria-label="Agregar producto" @click="show">+</button>
    <BaseModal :open="open" title="Configurar producto" description="Completa identidad, condiciones y orden de aplicación." size="max-w-2xl" @close="close">
        <div v-if="errors.length" class="mb-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700"><strong>Revisa la configuración:</strong><ul class="mt-1 list-disc pl-5"><li v-for="error in errors" :key="error">{{ error }}</li></ul></div>
        <div class="max-h-[62vh] space-y-4 overflow-y-auto pr-1">
            <section><h3 class="section-label">Identidad y vigencia</h3><div class="form-grid"><label>Código *<input v-model.trim="product.code" maxlength="30"></label><label class="sm:col-span-2">Nombre *<input v-model.trim="product.name" maxlength="150"></label><label>Moneda *<select v-model="product.currency"><option value="NIO">NIO</option><option value="USD">USD</option></select></label><label>Plazo mínimo *<input v-model="product.minimum_term" type="number" min="1"></label><label>Plazo máximo *<input v-model="product.maximum_term" type="number" min="1"></label></div></section>
            <section><h3 class="section-label">Condiciones permitidas</h3><div class="grid gap-3 sm:grid-cols-2"><fieldset><legend>Frecuencias *</legend><div class="option-grid"><label v-for="item in [{v:'daily',l:'Diaria'},{v:'weekly',l:'Semanal'},{v:'biweekly',l:'Quincenal'},{v:'monthly',l:'Mensual'}]" :key="item.v" class="choice"><input v-model="product.allowed_frequencies" type="checkbox" :value="item.v">{{ item.l }}</label></div></fieldset><fieldset><legend>Métodos de interés *</legend><div class="option-grid"><label v-for="item in [{v:'flat',l:'Plano'},{v:'declining_balance',l:'Saldo decreciente'},{v:'french',l:'Cuota nivelada'}]" :key="item.v" class="choice"><input v-model="product.allowed_interest_methods" type="checkbox" :value="item.v">{{ item.l }}</label></div></fieldset></div></section>
            <section><h3 class="section-label">Valores predeterminados opcionales</h3><div class="form-grid"><label>Tasa (%)<input v-model="product.default_interest_rate" type="number" min="0" step="0.000001"></label><label>Método<select v-model="product.default_interest_method"><option value="">Sin definir</option><option value="flat">Plano</option><option value="declining_balance">Saldo decreciente</option><option value="french">Cuota nivelada</option></select></label><label>Gasto administrativo *<input v-model="product.default_administrative_fee" type="number" min="0" step="0.01"></label><label>Método de mora<select v-model="product.delinquency_method"><option value="">Sin definir</option><option value="none">Sin mora</option><option value="daily_percentage">Porcentaje diario</option><option value="fixed">Monto fijo</option></select></label><label>Tasa / monto de mora<input v-model="product.delinquency_rate" type="number" min="0" step="0.000001"></label></div></section>
            <section><h3 class="section-label">Prioridad de aplicación de pagos *</h3><div class="grid grid-cols-2 gap-2 sm:grid-cols-4"><label v-for="(_, index) in product.payment_allocation_order" :key="index">Prioridad {{ index + 1 }}<select v-model="product.payment_allocation_order[index]"><option value="">Seleccionar</option><option v-for="option in allocationOptions" :key="option.value" :value="option.value" :disabled="product.payment_allocation_order.includes(option.value) && product.payment_allocation_order[index] !== option.value">{{ option.label }}</option></select></label></div></section>
        </div>
        <template #footer><div class="flex items-center justify-between gap-3"><p class="hidden text-[9px] text-slate-400 sm:block">No se asignan políticas financieras automáticamente.</p><div class="ml-auto flex gap-2"><button type="button" class="btn-secondary" @click="close">Cancelar</button><button type="button" class="btn-primary" :disabled="!canSave || saving" @click="save">{{ saving ? 'Guardando…' : 'Guardar y seleccionar' }}</button></div></div></template>
    </BaseModal>
    <Teleport to="body"><Transition name="toast"><div v-if="saved" class="fixed bottom-5 right-5 z-[120] flex items-center gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-xs font-semibold text-emerald-700 shadow-xl"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100">✓</span>Producto creado y seleccionado.</div></Transition></Teleport>
</template>

<style scoped>
.section-label{margin-bottom:.65rem;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#6366f1}.form-grid{display:grid;gap:.85rem 1rem}@media(min-width:640px){.form-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}label,legend{display:flex;min-width:0;flex-direction:column;justify-content:flex-end;color:#64748b;font-size:.62rem;font-weight:700;letter-spacing:.01em;transition:color .18s ease}label:focus-within{color:#0f9f98}input:not([type=checkbox]),select{display:block;width:100%;min-height:2.15rem;margin-top:.12rem;border:0;border-bottom:1px solid #aeb8c5;border-radius:0;background:transparent;padding:.32rem .1rem .38rem;color:#172033;font-size:.74rem;line-height:1.2rem;outline:none;box-shadow:none;transition:border-color .18s ease,box-shadow .18s ease,background-color .18s ease}select{padding-right:1.4rem;cursor:pointer}input:not([type=checkbox]):hover,select:hover{border-bottom-color:#7f8b9a}input:not([type=checkbox]):focus,select:focus{border-bottom:2px solid #0f9f98;background:linear-gradient(to bottom,transparent 75%,rgba(15,159,152,.035));box-shadow:0 1px 0 rgba(15,159,152,.08)}fieldset{border:1px solid #e2e8f0;border-radius:.75rem;background:#fbfdff;padding:.65rem}.option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem}.choice{display:flex;min-height:2rem;flex-direction:row;align-items:center;justify-content:flex-start;gap:.4rem;border-radius:.5rem;background:#f8fafc;padding:.4rem .55rem}.choice:has(input:checked){background:#eef2ff;color:#4338ca}.choice input{accent-color:#6366f1}.toast-enter-active,.toast-leave-active{transition:.2s}.toast-enter-from,.toast-leave-to{opacity:0;transform:translateY(8px)}
</style>
