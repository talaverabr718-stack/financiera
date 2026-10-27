<script setup>
import { computed } from 'vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Revisa la información ingresada' },
});

const labels = {
    full_name: 'Nombre completo', identity_number: 'Cédula', birth_date: 'Fecha de nacimiento',
    phone: 'Teléfono', email: 'Correo electrónico', address: 'Dirección', department: 'Departamento',
    municipality: 'Municipio', neighborhood: 'Barrio o comunidad', seller_id: 'Gestor asignado',
    requested_amount: 'Monto solicitado', approved_amount: 'Monto aprobado', term_value: 'Plazo',
    credit_product_id: 'Producto de crédito', password: 'Contraseña', password_confirmation: 'Confirmación de contraseña',
    date: 'Fecha', concept: 'Concepto', reference: 'Referencia', amount: 'Monto', reason: 'Motivo',
    client_ids: 'Clientes seleccionados', account_id: 'Cuenta contable', cost_center_id: 'Centro de costo',
    lines: 'Detalle del asiento', entry: 'Asiento contable', period: 'Período contable', status: 'Estado',
};

const fieldLabel = field => {
    const clean = field.replace(/\.\d+\./g, '.').replace(/\.\d+$/g, '').split('.').pop();
    return labels[field] || labels[clean] || clean.replaceAll('_', ' ').replace(/^./, value => value.toUpperCase());
};

const items = computed(() => Object.entries(props.errors || {}).flatMap(([field, value]) => {
    const messages = Array.isArray(value) ? value : [value];
    return messages.filter(Boolean).map(message => ({ field, label: fieldLabel(field), message: String(message) }));
}));
</script>

<template>
    <section v-if="items.length" class="validation-summary mb-5" role="alert" aria-live="assertive" tabindex="-1">
        <div class="validation-summary__icon" aria-hidden="true">!</div>
        <div class="min-w-0 flex-1">
            <h2>{{ title }}</h2>
            <p>Encontramos {{ items.length }} {{ items.length === 1 ? 'dato que necesita corrección' : 'datos que necesitan corrección' }}.</p>
            <ul>
                <li v-for="item in items" :key="`${item.field}-${item.message}`">
                    <strong>{{ item.label }}:</strong> {{ item.message }}
                </li>
            </ul>
        </div>
    </section>
</template>
