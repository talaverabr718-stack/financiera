<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';

const props = defineProps({ endpoint: String, csrf: String, selectId: { type: String, default: 'product' } });
const emit = defineEmits(['created']);
const open = ref(false);
const saving = ref(false);
const errors = ref([]);
const saved = ref(false);
const blank = () => ({ name: '', currency: 'NIO', default_interest_rate: '' });
const product = reactive(blank());
const canSave = computed(() => product.name.trim() && product.currency && product.default_interest_rate !== '');
let toastTimer;

const close = () => { open.value = false; errors.value = []; };
const show = () => { open.value = true; saved.value = false; };
const reset = () => Object.assign(product, blank());
const save = async () => {
    if (!canSave.value || saving.value) return;
    saving.value = true; errors.value = [];
    try {
        const response = await fetch(props.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf },
            body: JSON.stringify({
                quick: true,
                name: product.name.trim(),
                currency: product.currency,
                default_interest_rate: product.default_interest_rate,
            }),
        });
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
        emit('created', data.product);
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
    <BaseModal :open="open" title="Nuevo producto" description="El código se asigna al guardar." size="max-w-md" @close="close">
        <div v-if="errors.length" class="mb-3 rounded-xl border border-rose-400/20 bg-rose-500/10 p-3 text-xs text-rose-300"><strong>Revisa los datos:</strong><ul class="mt-1 list-disc pl-5"><li v-for="error in errors" :key="error">{{ error }}</li></ul></div>
        <div class="grid gap-4">
            <label class="field">Código<input value="Se genera automáticamente" disabled></label>
            <label class="field">Nombre *<input v-model.trim="product.name" maxlength="150" required></label>
            <label class="field">Moneda *<select v-model="product.currency"><option value="NIO">Córdobas</option><option value="USD">Dólares</option></select></label>
            <label class="field">Tasa (%) *<input v-model="product.default_interest_rate" type="number" min="0" step="0.000001" required></label>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="close">Cancelar</button>
                <button type="button" class="btn-primary" :disabled="!canSave || saving" @click="save">{{ saving ? 'Guardando…' : 'Guardar y seleccionar' }}</button>
            </div>
        </template>
    </BaseModal>
    <Teleport to="body"><Transition name="toast"><div v-if="saved" class="fixed bottom-5 right-5 z-[120] flex items-center gap-3 rounded-xl border border-emerald-400/20 bg-[#101522] px-4 py-3 text-xs font-semibold text-emerald-300 shadow-xl"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-400/15">✓</span>Producto creado y seleccionado.</div></Transition></Teleport>
</template>

<style scoped>
.field{display:flex;flex-direction:column;color:#9aa8c7;font-size:.7rem;font-weight:700}
.field input,.field select{margin-top:.35rem;min-height:2.4rem;border:1px solid rgba(255,255,255,.1);border-radius:.75rem;background:rgba(255,255,255,.05);padding:.55rem .75rem;color:#f4f7ff;font-size:.8rem;font-weight:500;outline:none}
.field input:focus,.field select:focus{border-color:#5b8cff;box-shadow:0 0 0 4px rgba(91,140,255,.16)}
.field input:disabled{background:rgba(255,255,255,.03);color:#6b7388}
.toast-enter-active,.toast-leave-active{transition:.2s}.toast-enter-from,.toast-leave-to{opacity:0;transform:translateY(8px)}
</style>
