<script setup>
const props = defineProps({
    ticket: { type: Object, required: true },
    brand: { type: Object, default: () => ({}) },
});

const money = (value, currency = props.ticket.currency || 'NIO') =>
    new Intl.NumberFormat('es-NI', { style: 'currency', currency }).format(Number(value || 0));
const methodLabel = value => ({ cash: 'Efectivo', transfer: 'Transferencia', deposit: 'Depósito' }[value] || value || 'Pago');
const partLabel = value => ({
    principal: 'Capital',
    interest: 'Interés',
    fees: 'Cargos',
    delinquency: 'Mora',
}[value] || value);
</script>

<template>
    <article class="collection-ticket">
        <header class="collection-ticket-brand">
            <img v-if="brand.logo_url" :src="brand.logo_url" alt="">
            <strong>{{ brand.system_name || 'Financiera' }}</strong>
            <small>Comprobante de pago</small>
        </header>
        <p class="collection-ticket-number">{{ ticket.receipt_number }}</p>
        <p class="collection-ticket-meta">{{ ticket.received_at }}</p>
        <dl>
            <div><dt>Cliente</dt><dd>{{ ticket.client }}</dd></div>
            <div v-if="ticket.phone"><dt>Teléfono</dt><dd>{{ ticket.phone }}</dd></div>
            <div><dt>Crédito</dt><dd>{{ ticket.loan_number }}</dd></div>
            <div><dt>Forma</dt><dd>{{ methodLabel(ticket.payment_method) }}</dd></div>
            <div v-if="ticket.reference"><dt>Referencia</dt><dd>{{ ticket.reference }}</dd></div>
            <div v-if="ticket.collector"><dt>Cobrador</dt><dd>{{ ticket.collector }}</dd></div>
        </dl>
        <table>
            <thead>
                <tr><th>Cuota</th><th>Importe</th></tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in ticket.installments" :key="index">
                    <td>
                        <strong>{{ line.label }}</strong>
                        <small v-if="line.due_date">Vence {{ line.due_date }}</small>
                        <small v-for="part in line.parts" :key="part.component">{{ partLabel(part.component) }} {{ money(part.amount) }}</small>
                    </td>
                    <td>{{ money(line.amount) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="collection-ticket-total"><span>Total pagado</span><b>{{ money(ticket.amount) }}</b></p>
        <p class="collection-ticket-balance"><span>Saldo anterior</span><span>{{ money(ticket.previous_balance) }}</span></p>
        <p class="collection-ticket-balance"><span>Saldo pendiente</span><span>{{ money(ticket.new_balance) }}</span></p>
        <footer>Conserve este ticket como registro del abono aplicado a las cuotas.</footer>
    </article>
</template>
