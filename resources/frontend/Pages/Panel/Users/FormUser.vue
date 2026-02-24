<script setup>
import { ref, watch, computed } from 'vue'
import { QForm, QInput, QBtn, QCard, QCardSection, QCardActions } from 'quasar'

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    user: {
        type: Object,
        default: () => ({ id: null, name: '', email: '', notification_email: '' }),
    },
    notificationOptions: {
        type: Array,
        default: () => [],
    },
    notificationPreferences: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['submit'])

const form = ref({
    name: '',
    email: '',
    notification_email: '',
    password: '',
    password_confirmation: '',
})
const preferenceMap = ref({})

watch(
    () => props.user,
    (u) => {
        if (!u) return
        form.value.name  = u.name  ?? ''
        form.value.email = u.email ?? ''
        form.value.notification_email = u.notification_email ?? ''
        form.value.password = ''
        form.value.password_confirmation = ''
    },
    { immediate: true }
)

const isEdit = computed(() => props.mode === 'edit')
const hasNotificationOptions = computed(() => Array.isArray(props.notificationOptions) && props.notificationOptions.length > 0)

function buildPreferenceMap(options, existingPreferences) {
    const map = {}
    const existingByType = {}
    for (const pref of (existingPreferences || [])) {
        const typeId = Number(pref?.notification_type_id || 0)
        if (typeId > 0) {
            existingByType[typeId] = {
                enabled_in_app: Boolean(pref.enabled_in_app ?? true),
                enabled_email: Boolean(pref.enabled_email ?? false),
                enabled_push: Boolean(pref.enabled_push ?? false),
                frequency: pref.frequency ?? null,
            }
        }
    }

    for (const category of (options || [])) {
        for (const type of (category?.types || [])) {
            const typeId = Number(type?.id || 0)
            if (typeId <= 0) continue
            map[typeId] = existingByType[typeId] || {
                enabled_in_app: true,
                enabled_email: false,
                enabled_push: false,
                frequency: null,
            }
        }
    }

    return map
}

function preferencesPayload() {
    const rows = []
    for (const category of (props.notificationOptions || [])) {
        for (const type of (category?.types || [])) {
            const typeId = Number(type?.id || 0)
            if (typeId <= 0) continue
            const pref = preferenceMap.value[typeId] || {
                enabled_in_app: true,
                enabled_email: false,
                enabled_push: false,
                frequency: null,
            }
            rows.push({
                notification_type_id: typeId,
                enabled_in_app: Boolean(pref.enabled_in_app),
                enabled_email: Boolean(pref.enabled_email),
                enabled_push: Boolean(pref.enabled_push),
                frequency: pref.frequency ?? null,
            })
        }
    }
    return rows
}

function ensurePreference(typeId) {
    const id = Number(typeId || 0)
    if (id <= 0) return { enabled_in_app: true, enabled_email: false, enabled_push: false, frequency: null }
    if (!preferenceMap.value[id]) {
        preferenceMap.value[id] = {
            enabled_in_app: true,
            enabled_email: false,
            enabled_push: false,
            frequency: null,
        }
    }
    return preferenceMap.value[id]
}

function handleSubmit() {
    emit('submit', {
        ...form.value,
        notification_preferences: preferencesPayload(),
    })
}

watch(
    () => [props.notificationOptions, props.notificationPreferences],
    ([options, preferences]) => {
        preferenceMap.value = buildPreferenceMap(options, preferences)
    },
    { immediate: true, deep: true }
)
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
                                v-model="form.notification_email"
                                label="E-mail para notificações"
                                type="email"
                                outlined
                                dense
                                hint="Se vazio, usaremos o e-mail principal da conta."
                                :rules="[v => !v || /^[^\\s@]+@[^\\s@]+\\.[^\\s@]{2,}$/.test(v) || 'E-mail inválido']"
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

                            <div class="md:tw-col-span-2 tw-border tw-border-slate-200 tw-rounded-lg tw-p-3 tw-space-y-3">
                                <div class="tw-text-sm tw-font-semibold">Preferências de notificação</div>
                                <div v-if="!hasNotificationOptions" class="tw-text-sm tw-text-slate-500">
                                    Nenhum tipo de notificação ativo no catálogo.
                                </div>
                                <div v-else class="tw-space-y-3">
                                    <q-expansion-item
                                        v-for="category in notificationOptions"
                                        :key="`cat-${category.id}`"
                                        :label="category.name"
                                        dense
                                        default-opened
                                        class="tw-border tw-border-slate-200 tw-rounded"
                                    >
                                        <div class="tw-px-3 tw-pb-3 tw-space-y-2">
                                            <div
                                                v-for="type in category.types || []"
                                                :key="`type-${type.id}`"
                                                class="tw-grid tw-grid-cols-1 md:tw-grid-cols-[1fr_auto_auto] tw-gap-2 tw-items-center tw-border-b tw-border-slate-100 tw-py-2 last:tw-border-b-0"
                                            >
                                                <div>
                                                    <div class="tw-text-sm tw-font-medium">{{ type.name }}</div>
                                                    <div class="tw-text-xs tw-text-slate-500">{{ type.slug }}</div>
                                                </div>
                                                <q-checkbox
                                                    v-model="ensurePreference(type.id).enabled_in_app"
                                                    dense
                                                    label="No sistema"
                                                />
                                                <q-checkbox
                                                    v-model="ensurePreference(type.id).enabled_email"
                                                    dense
                                                    label="Por e-mail"
                                                />
                                            </div>
                                        </div>
                                    </q-expansion-item>
                                </div>
                            </div>

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
