<script setup>
import { ref, watch, computed } from 'vue'
import { QForm, QInput, QBtn, QCard, QCardSection, QCardActions } from 'quasar'

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    user: {
        type: Object,
        default: () => ({ id: null, name: '', email: '' }),
    },
})

const emit = defineEmits(['submit'])

const form = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

watch(
    () => props.user,
    (u) => {
        if (!u) return
        form.value.name  = u.name  ?? ''
        form.value.email = u.email ?? ''
        form.value.password = ''
        form.value.password_confirmation = ''
    },
    { immediate: true }
)

const isEdit = computed(() => props.mode === 'edit')

function handleSubmit() {
    emit('submit', { ...form.value })
}
</script>

<template>
        <div class="tw-flex tw-justify-center tw-mt-8">
            <div class="tw-w-full md:tw-max-w-xl lg:tw-max-w-2xl">
                <q-card flat bordered>
                    <q-card-section>
                        <div class="tw-text-lg tw-font-semibold tw-mb-2">
                            {{ isEdit ? 'Editar usuário' : 'Cadastrar usuário' }}
                        </div>

                        <q-form @submit.prevent="handleSubmit" class="tw-grid tw-gap-4 md:tw-grid-cols-2">
                            <q-input
                                v-model="form.name"
                                label="Nome"
                                outlined
                                dense
                                :rules="[val => !!val || 'Informe o nome']"
                                class="md:tw-col-span-2"
                            />

                            <q-input
                                v-model="form.email"
                                label="E-mail"
                                type="email"
                                outlined
                                dense
                                :rules="[val => !!val || 'Informe o e-mail']"
                                class="md:tw-col-span-2"
                            />

                            <q-input
                                v-model="form.password"
                                :type="'password'"
                                outlined
                                dense
                                :label="isEdit ? 'Senha (opcional)' : 'Senha'"
                                :rules="isEdit
                        ? []
                        : [(v) => !!v || 'Informe a senha', (v) => (v?.length ?? 0) >= 8 || 'Mínimo 8 caracteres']"
                            />

                            <q-input
                                v-model="form.password_confirmation"
                                :type="'password'"
                                outlined
                                dense
                                :label="isEdit ? 'Confirmar senha (opcional)' : 'Confirmar senha'"
                                :rules="isEdit
                        ? []
                        : [(v) => v === form.password || 'Confirmação não confere']"
                            />

                            <div class="md:tw-col-span-2 tw-flex tw-gap-2 tw-justify-between">
                                <Link :href="route('panel.users.index')" as="button" type="button">
                                    <q-btn flat color="primary" label="Voltar" />
                                </Link>
                                <q-btn type="submit" color="positive" unelevated :label="isEdit ? 'Salvar alterações' : 'Cadastrar'" />
                            </div>
                        </q-form>
                    </q-card-section>
                </q-card>
            </div>
        </div>
</template>
