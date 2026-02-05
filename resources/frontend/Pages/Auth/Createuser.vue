<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import AuthLayout from '@/Layouts/AuthLayout.vue'

    defineOptions({
        layout: (h, page) => h(AuthLayout, null, {
            default: () => page,
            title: () => 'Criar conta',
            subtitle: () => 'Preencha seus dados para começar'
        })
    })

    const showPass = ref(false)
    const showConfirm = ref(false)

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    })

    function submit () {
        form.post(route('auth.register'), { preserveScroll: true })
    }
</script>

<template>
    <Head title="Criar conta" />

    <q-form @submit.prevent="submit" class="tw-space-y-4">
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Nome</label>
            <q-input
                v-model="form.name"
                type="text"
                dense outlined
                :error="!!form.errors.name"
                :error-message="form.errors.name"
                placeholder="Seu nome"
                :rules="[v => !!v || 'Informe o nome']"
            >
                <template #prepend><q-icon name="person" /></template>
            </q-input>
        </div>

        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">E-mail</label>
            <q-input
                v-model="form.email"
                type="email"
                dense outlined clearable
                autocomplete="email"
                inputmode="email"
                :error="!!form.errors.email"
                :error-message="form.errors.email"
                placeholder="voce@exemplo.com"
                :rules="[
                     v => !!v || 'Informe o e-mail',
                     v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || 'E-mail inválido'
                  ]"
                >
                <template #prepend><q-icon name="mail" /></template>
            </q-input>
        </div>

        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
            <div>
                <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Senha</label>
                <q-input
                    v-model="form.password"
                    :type="showPass ? 'text' : 'password'"
                    dense outlined
                    :error="!!form.errors.password"
                    :error-message="form.errors.password"
                    placeholder="Mínimo 6 caracteres"
                    :rules="[
            v => !!v || 'Informe a senha',
            v => String(v).length >= 6 || 'Mínimo 6 caracteres'
          ]"
                >
                    <template #prepend><q-icon name="lock" /></template>
                    <template #append>
                        <q-icon :name="showPass ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showPass = !showPass" />
                    </template>
                </q-input>
            </div>

            <div>
                <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Confirmar senha</label>
                <q-input
                    v-model="form.password_confirmation"
                    :type="showConfirm ? 'text' : 'password'"
                    dense outlined
                    placeholder="Repita a senha"
                    :rules="[
            v => !!v || 'Confirme a senha',
            v => v === form.password || 'Senhas diferentes'
          ]"
                >
                    <template #prepend><q-icon name="lock" /></template>
                    <template #append>
                        <q-icon :name="showConfirm ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showConfirm = !showConfirm" />
                    </template>
                </q-input>
            </div>
        </div>

        <q-btn
            type="submit"
            color="positive"
            class="tw-w-full tw-font-semibold"
            :loading="form.processing"
            label="Criar conta"
            unelevated
        />

        <q-separator />

        <div class="tw-text-sm tw-text-slate-600 dark:tw-text-slate-300 tw-text-center">
            Já tem conta?
            <Link :href="route('auth.login.show')" class="tw-text-brand-primary hover:tw-underline">Entrar</Link>
        </div>
    </q-form>
</template>
