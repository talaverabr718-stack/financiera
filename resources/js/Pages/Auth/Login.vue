<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ loginUrl: String });
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(props.loginUrl, { preserveScroll: true, onFinish: () => form.reset('password') });
</script>
<template>
    <Head title="Iniciar sesión" />
    <main class="relative grid min-h-screen place-items-center overflow-hidden bg-slate-950 px-4 py-10">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl"></div><div class="absolute -bottom-40 -right-32 h-96 w-96 rounded-full bg-cyan-500/15 blur-3xl"></div>
        <section class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-white p-7 shadow-2xl shadow-black/40">
            <div class="mb-7"><div class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-400 font-bold text-white">F</div><p class="mt-5 text-[10px] font-bold uppercase tracking-[.18em] text-indigo-600">Acceso seguro</p><h1 class="mt-1 text-2xl font-bold">Bienvenido</h1><p class="mt-2 text-xs text-slate-500">Ingresa a la plataforma financiera.</p></div>
            <form class="space-y-4" @submit.prevent="submit">
                <label class="field-label">Correo electrónico<input v-model="form.email" type="email" autocomplete="username" autofocus class="control" required></label>
                <label class="field-label">Contraseña<input v-model="form.password" type="password" autocomplete="current-password" class="control" required></label>
                <label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.remember" type="checkbox"> Mantener sesión iniciada</label>
                <p v-if="form.errors.email" class="rounded-lg bg-rose-50 p-2 text-xs text-rose-700">{{ form.errors.email }}</p>
                <button class="btn-primary w-full" :disabled="form.processing"><span v-if="form.processing" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ form.processing ? 'Verificando…' : 'Iniciar sesión' }}</button>
            </form>
        </section>
    </main>
</template>
