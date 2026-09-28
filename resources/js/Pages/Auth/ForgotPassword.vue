<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ submitUrl: String, loginUrl: String });
const form = useForm({ email: '' });
const submit = () => form.post(props.submitUrl);
</script>
<template><Head title="Recuperar acceso"/><main class="login-shell"><section class="login-access mx-auto"><div class="w-full max-w-md"><p class="eyebrow">Recuperación segura</p><h1 class="mt-2 text-3xl font-semibold text-slate-950">Restablecer contraseña</h1><p class="mt-2 text-sm text-slate-500">Te enviaremos un enlace si existe una cuenta con ese correo.</p><form class="mt-7 space-y-5" @submit.prevent="submit"><label class="field-label">Correo electrónico<input v-model="form.email" type="email" class="control login-control" required autofocus></label><p v-if="form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p><button class="btn-primary h-12 w-full" :disabled="form.processing">Enviar enlace</button></form><Link :href="loginUrl" class="mt-6 inline-block text-xs font-semibold text-blue-700">Volver a iniciar sesión</Link></div></section></main></template>