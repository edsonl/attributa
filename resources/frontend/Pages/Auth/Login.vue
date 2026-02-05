<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import AuthLayout from '@/Layouts/AuthLayout.vue'
    import {QBtn} from "quasar";

    defineOptions({
        layout: (h, page) => h(AuthLayout, null, {
            default: () => page,
            title: () => 'Entrar',
            subtitle: () => 'Acesse sua conta para continuar'
        })
    })

    const showPass = ref(false)

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    })

    function submit () {
        form.post(route('auth.login'), { preserveScroll: true })
    }
</script>

<template>
    <Head title="Login" />

    <q-form @submit.prevent="submit" class="tw-space-y-4">
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">E-mail</label>
            <q-input
                v-model="form.email"
                type="email"
                dense outlined clearable
                :error="!!form.errors.email"
                :error-message="form.errors.email"
                placeholder="voce@exemplo.com"
                :rules="[
          v => !!v || 'Informe o e-mail',
          v => /.+@.+\..+/.test(v) || 'E-mail inválido'
        ]"
            >
                <template #prepend><q-icon name="mail" /></template>
            </q-input>
        </div>

        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Senha</label>
            <q-input
                v-model="form.password"
                :type="showPass ? 'text' : 'password'"
                dense outlined
                :error="!!form.errors.password"
                :error-message="form.errors.password"
                placeholder="••••••••"
                :rules="[v => !!v || 'Informe a senha']"
            >
                <template #prepend><q-icon name="lock" /></template>
                <template #append>
                    <q-icon :name="showPass ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showPass = !showPass" />
                </template>
            </q-input>
        </div>

        <q-btn
            type="submit"
            color="positive"
            class="tw-w-full tw-font-semibold"
            :loading="form.processing"
            label="Entrar"
            unelevated
        />

        <div class="tw-flex tw-items-center tw-justify-between">
            <q-checkbox v-model="form.remember" label="Lembrar-me" dense />
            <Link :href="route('password.request')" as="button" type="button">
                <span class="tw-text-sm tw-text-slate-500">Esqueceu a senha?</span>
            </Link>
        </div>

        <q-separator />

        <div class="tw-text-sm tw-text-slate-600 dark:tw-text-slate-300 tw-text-center">
            Não tem conta?
            <Link :href="route('auth.register.show')" class="tw-text-brand-primary hover:tw-underline">Crie agora</Link>
        </div>
    </q-form>
</template>
