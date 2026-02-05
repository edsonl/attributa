<script setup>
    import { Head, useForm, usePage } from '@inertiajs/vue3'
    import { ref } from 'vue'

    // usa o usuário já compartilhado em usePage().props.auth.user
    const page = usePage()
    const user = page.props?.auth?.user ?? {}

    // form com valores atuais; senha é opcional
    const form = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
        password: '',
        password_confirmation: '',
    })

    const showPass = ref(false)
    const showConfirm = ref(false)

    function submit () {
        form.put(route('account.update'), { preserveScroll: true })
    }
    // regras simples de validação no client (backend também valida)
    const required = v => !!(v && String(v).trim()) || 'Campo obrigatório'
    const emailRule = v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || 'E-mail inválido'
</script>

<!-- Usa AppLayout e manda o título -->
<script>
   import AppLayout from '@/Layouts/AppLayout.vue'
    export default {
        layout: (h, page) => h(AppLayout, { gradientPage: true }, () => page)
    }
</script>

<template>
    <Head title="Editar conta" />

    <div class="tw-max-w-xl tw-mx-auto tw-mt-6">
        <q-card flat bordered class="tw-rounded-2xl">
            <q-card-section>
                <div class="tw-text-xl tw-font-semibold tw-mb-1">Editar conta</div>
                <div class="tw-text-slate-600 dark:tw-text-slate-300">
                    Atualize seus dados de perfil
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <!-- feedback de sucesso (opcional) -->
                <!--
                <q-banner
                    v-if="page.props.flash?.success"
                    dense class="tw-mb-4" rounded
                    inline-actions
                >
                    {{ page.props.flash.success }}
                </q-banner> -->

                <q-form @submit.prevent="submit" class="tw-space-y-4">
                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Nome</label>
                        <q-input
                            v-model="form.name"
                            type="text" dense outlined
                            :error="!!form.errors.name" :error-message="form.errors.name"
                            :rules="[required]"
                            placeholder="Seu nome"
                        >
                            <template #prepend><q-icon name="person" /></template>
                        </q-input>
                    </div>

                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">E-mail</label>
                        <q-input
                            v-model="form.email"
                            type="email" dense outlined clearable
                            autocomplete="email" inputmode="email"
                            :error="!!form.errors.email" :error-message="form.errors.email"
                            :rules="[required, emailRule]"
                            placeholder="voce@exemplo.com"
                        >
                            <template #prepend><q-icon name="mail" /></template>
                        </q-input>
                    </div>

                    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Nova senha (opcional)</label>
                            <q-input
                                v-model="form.password"
                                :type="showPass ? 'text' : 'password'"
                                dense outlined
                                :error="!!form.errors.password" :error-message="form.errors.password"
                                placeholder="Deixe em branco para manter"
                            >
                                <template #prepend><q-icon name="lock" /></template>
                                <template #append>
                                    <q-icon
                                        :name="showPass ? 'visibility_off' : 'visibility'"
                                        class="cursor-pointer"
                                        @click="showPass = !showPass"
                                    />
                                </template>
                            </q-input>
                        </div>

                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">Confirmar nova senha</label>
                            <q-input
                                v-model="form.password_confirmation"
                                :type="showConfirm ? 'text' : 'password'"
                                dense outlined
                                placeholder="Repita a nova senha"
                            >
                                <template #prepend><q-icon name="lock" /></template>
                                <template #append>
                                    <q-icon
                                        :name="showConfirm ? 'visibility_off' : 'visibility'"
                                        class="cursor-pointer"
                                        @click="showConfirm = !showConfirm"
                                    />
                                </template>
                            </q-input>
                        </div>
                    </div>

                    <div class="tw-flex tw-justify-end tw-gap-3 tw-pt-2">
                        <q-btn
                            type="submit" color="positive" unelevated
                            class="tw-font-semibold"
                            :loading="form.processing"
                            label="Salvar alterações"
                        />
                    </div>
                </q-form>
            </q-card-section>
        </q-card>
    </div>
</template>
