<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({
    layout: (h, page) => h(AuthLayout, null, {
        default: () => page,
        title: () => 'Recuperar senha',
        subtitle: () => 'Informe seu e-mail. Enviaremos um link para redefinir sua senha.'
    })
})

const form = ref({ email: '' })
const loading = ref(false)

function submit() {
    loading.value = true
    router.post(route('password.email'), form.value, {
        onFinish: () => { loading.value = false }
    })
}
</script>

<template>
    <Head title="Recuperar senha" />
    <q-form @submit.prevent="submit" class="tw-space-y-4">
        <q-input v-model="form.email" type="email" label="E-mail" outlined dense
                 :rules="[v => !!v || 'Informe o e-mail']" />

        <div class="tw-flex tw-gap-2 tw-justify-between">
            <Link :href="route('auth.login')" as="button" type="button">
                <q-btn flat color="primary" label="Voltar ao login" />
            </Link>
            <q-btn type="submit" color="positive" unelevated :loading="loading" label="Enviar link" />
        </div>
    </q-form>
</template>
