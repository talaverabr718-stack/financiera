<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ loginUrl: String });
const page = usePage();
const form = useForm({ method: 'password', email: '', password: '', pin: '', remember: false });
const setMethod = method => { form.method = method; form.password = ''; form.pin = ''; form.clearErrors(); };
const submit = () => form.post(props.loginUrl, { preserveScroll: true, onFinish: () => form.reset('password', 'pin') });
</script>

<template>
    <Head title="Iniciar sesión" />
    <main class="login-shell" :class="`theme-${page.props.appearance?.theme === 'day' ? 'day' : 'night'}`">
        <section class="login-story">
            <div class="login-glow login-glow-one"></div><div class="login-glow login-glow-two"></div>
            <header class="relative z-10 flex items-center gap-3">
                <img v-if="page.props.brand?.logo_url" :src="page.props.brand.logo_url" alt="Logotipo" class="h-12 w-12 rounded-2xl bg-white object-contain p-1.5">
                <span v-else class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-500 text-lg font-black text-white shadow-lg shadow-blue-950/30">F</span>
                <div><strong class="block text-sm tracking-tight text-white">{{ page.props.brand?.system_name || 'Financiera' }}</strong><span class="text-[11px] text-slate-400">{{ page.props.brand?.system_tagline || 'Gestión integral' }}</span></div>
            </header>
            <div class="relative z-10 max-w-xl">
                <p class="premium-kicker"><i></i> Operación viva</p>
                <h1 class="mt-5 text-4xl font-semibold leading-[1.08] tracking-[-.04em] text-white sm:text-5xl">Crédito cercano.<br><span class="text-sky-300">Decisiones con visión.</span></h1>
                <p class="mt-5 max-w-lg text-sm leading-6 text-slate-400">Una plataforma diseñada para acompañar la operación financiera y el crecimiento de nuestra comunidad en el norte de Nicaragua.</p>
                <div class="mt-9 grid max-w-lg grid-cols-3 gap-3">
                    <div class="login-stat"><strong>360°</strong><span>Visión de cartera</span></div><div class="login-stat"><strong>En vivo</strong><span>Gestión territorial</span></div><div class="login-stat"><strong>Seguro</strong><span>Control y trazabilidad</span></div>
                </div>
            </div>
            <div class="cathedral-login" aria-hidden="true"><svg viewBox="0 0 760 170" fill="none" stroke="currentColor" stroke-width="1.15"><path d="M0 164h760M55 164V95h67v69M66 95V42l22-27 22 27v53M75 59h27M88 25V2M78 12h20M122 164V82h127v82M249 164V95h67v69M260 95V42l22-27 22 27v53M269 59h27M282 25V2M272 12h20M152 164v-55h67v55M173 164v-36h25v36M134 82l52-40 51 40M151 94h69M349 164c61-29 111-24 161 0s105 21 155-2 70-20 95-8"/><path d="M345 145l32-35 36 35 41-59 49 59 39-28 43 28M586 164v-43h38v43m8 0v-65h47v65"/></svg></div>
        </section>
        <section class="login-access">
            <div class="w-full max-w-md">
                <div class="mb-9"><p class="eyebrow">Acceso seguro</p><h2 class="mt-2 text-3xl font-semibold tracking-[-.035em] text-slate-950">Bienvenido de vuelta</h2><p class="mt-2 text-sm leading-6 text-slate-500">Ingresa tus credenciales para continuar con la operación.</p></div>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-xs font-semibold"><button type="button" class="rounded-lg px-3 py-2 transition" :class="form.method === 'password' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500'" @click="setMethod('password')">Contraseña</button><button type="button" class="rounded-lg px-3 py-2 transition" :class="form.method === 'pin' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500'" @click="setMethod('pin')">PIN</button></div>
                    <label class="field-label">Correo electrónico<input v-model="form.email" type="email" autocomplete="username" autofocus class="control login-control" placeholder="nombre@financiera.com" required></label>
                    <label v-if="form.method === 'password'" class="field-label">Contraseña<input v-model="form.password" type="password" autocomplete="current-password" class="control login-control" placeholder="••••••••" required></label>
                    <label v-else class="field-label">PIN de 4 dígitos<input v-model="form.pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" class="control login-control text-center tracking-[.45em]" placeholder="••••" required></label>
                    <div class="flex items-center justify-between gap-4"><label class="flex items-center gap-2 text-xs font-medium text-slate-600"><input v-model="form.remember" type="checkbox" class="accent-blue-600"> Mantener sesión</label><span class="text-xs font-semibold text-blue-700">Acceso institucional</span></div>
                    <p v-if="form.errors.email" class="rounded-xl border border-rose-400/20 bg-rose-500/10 p-3 text-xs font-medium text-rose-300">{{ form.errors.email }}</p>
                    <button class="btn-primary h-12 w-full rounded-xl text-sm" :disabled="form.processing"><span v-if="form.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ form.processing ? 'Verificando…' : 'Ingresar al sistema' }}<span v-if="!form.processing">→</span></button>
                </form>
                <footer class="mt-10 border-t border-slate-200 pt-5 text-[11px] leading-5 text-slate-400">Acceso exclusivo para personal autorizado. Todas las operaciones quedan registradas para seguridad y auditoría.</footer>
            </div>
        </section>
    </main>
</template>
