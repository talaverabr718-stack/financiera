<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ status: Number, requestId: String, homeUrl: { type: String, default: '/' } });
const details = computed(() => ({
    403: ['Acceso restringido', 'Tu cuenta no tiene permiso para realizar esta acción. Si consideras que necesitas acceso, consulta al administrador.'],
    404: ['No encontramos esta página', 'La dirección puede haber cambiado o el elemento solicitado ya no está disponible.'],
    500: ['No pudimos completar la operación', 'El incidente fue registrado. Puedes intentar nuevamente sin repetir pagos, desembolsos ni cierres que estén en proceso.'],
    503: ['Servicio temporalmente no disponible', 'Estamos realizando una tarea de mantenimiento. Intenta nuevamente en unos minutos.'],
}[props.status] || ['No pudimos completar la solicitud', 'Intenta nuevamente o comunícate con el administrador.']));
</script>

<template>
    <Head :title="details[0]" />
    <main class="grid min-h-screen place-items-center bg-slate-950 px-5 py-10 text-white">
        <section class="w-full max-w-xl border border-white/10 bg-slate-900 p-7 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[.18em] text-cyan-300">ERROR {{ status }}</p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{{ details[0] }}</h1>
            <p class="mt-4 text-base leading-7 text-slate-300">{{ details[1] }}</p>
            <div v-if="requestId" class="mt-6 border border-white/10 bg-black/20 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Código de seguimiento</p>
                <code class="mt-2 block break-all text-sm text-amber-300">{{ requestId }}</code>
                <p class="mt-2 text-xs leading-5 text-slate-400">Comparte este código al reportar el problema. Permite localizar el detalle técnico sin mostrar información sensible.</p>
            </div>
            <div class="mt-7 flex flex-wrap gap-3">
                <button type="button" class="btn-primary" @click="history.back()">Volver</button>
                <Link :href="homeUrl" class="btn-secondary">Ir al inicio</Link>
            </div>
        </section>
    </main>
</template>
