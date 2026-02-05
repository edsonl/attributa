<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({
    layout: (h, page) => h(AuthLayout, null, {
        default: () => page,
        title: () => 'Definir nova senha',
        subtitle: () => ''
    })
})

const page = usePage()
const token = computed(() => page.props.token)
const email = computed(() => page.props.email || '')

const form = ref({
    token: token.value,
    email: email.value,
    password: '',
    password_confirmation: '',
})
const loading = ref(false)

function submit() {
    loading.value = true
    router.post(route('password.update'), form.value, {
        onFinish: () => { loading.value = false }
    })
}
</script>

<template>
    <Head title="Definir nova senha" />
    <q-form @submit.prevent="submit" class="tw-space-y-4">
        <q-input v-model="form.email" type="email" label="E-mail" outlined dense
                 :rules="[v => !!v || 'Informe o e-mail']" />

        <q-input v-model="form.password" type="password" label="Nova senha" outlined dense
                 :rules="[(v)=>!!v||'Informe a senha',(v)=>(v?.length??0)>=8||'Mínimo 8 caracteres']" />

        <q-input v-model="form.password_confirmation" type="password" label="Confirmar senha" outlined dense
                 :rules="[(v)=>v===form.password||'Confirmação não confere']" />

        <div class="tw-flex tw-gap-2 tw-justify-between">

            <Link :href="route('auth.login')" as="button">
                <q-btn flat color="primary" label="Voltar ao login" />
            </Link>

            <q-btn type="submit" color="positive" unelevated :loading="loading" label="Redefinir senha" />

        </div>
    </q-form>
</template>
