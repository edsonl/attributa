<script setup>
    import { Head, useForm, usePage } from '@inertiajs/vue3'
    import { computed, ref, watch } from 'vue'

    // usa o usuário já compartilhado em usePage().props.auth.user
    const page = usePage()
    const user = page.props?.auth?.user ?? {}
    const notificationOptions = computed(() => page.props?.notification_options || [])
    const notificationPreferences = computed(() => page.props?.notification_preferences || [])

    // form com valores atuais; senha é opcional
    const form = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
        notification_email: user.notification_email ?? '',
        password: '',
        password_confirmation: '',
        notification_preferences: [],
    })

    const showPass = ref(false)
    const showConfirm = ref(false)
    const preferenceMap = ref({})

    function submit () {
        form.notification_preferences = preferencesPayload()
        form.put(route('account.update'), { preserveScroll: true })
    }
    // regras simples de validação no client (backend também valida)
    const required = v => !!(v && String(v).trim()) || 'Campo obrigatório'
    const emailRule = v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || 'E-mail inválido'

    function buildPreferenceMap(options, existingPreferences) {
        const map = {}
        const existingByType = {}

        for (const pref of (existingPreferences || [])) {
            const typeId = Number(pref?.notification_type_id || 0)
            if (typeId <= 0) continue
            existingByType[typeId] = {
                enabled_in_app: Boolean(pref.enabled_in_app ?? true),
                enabled_email: Boolean(pref.enabled_email ?? false),
                enabled_push: Boolean(pref.enabled_push ?? false),
                frequency: pref.frequency ?? null,
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

    function ensurePreference(typeId) {
        const id = Number(typeId || 0)
        if (id <= 0) {
            return {
                enabled_in_app: true,
                enabled_email: false,
                enabled_push: false,
                frequency: null,
            }
        }

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

    function preferencesPayload() {
        const rows = []
        for (const category of (notificationOptions.value || [])) {
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

    watch(
        () => [notificationOptions.value, notificationPreferences.value],
        ([options, preferences]) => {
            preferenceMap.value = buildPreferenceMap(options, preferences)
        },
        { immediate: true, deep: true }
    )
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

                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-mb-1">E-mail para notificações</label>
                        <q-input
                            v-model="form.notification_email"
                            type="email" dense outlined clearable
                            autocomplete="email" inputmode="email"
                            :error="!!form.errors.notification_email" :error-message="form.errors.notification_email"
                            :rules="[v => !v || emailRule(v)]"
                            placeholder="Se vazio, usa o e-mail principal"
                            hint="Se vazio, usaremos o e-mail principal da conta."
                        >
                            <template #prepend><q-icon name="alternate_email" /></template>
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

                    <div class="tw-border tw-border-slate-200 tw-rounded-lg tw-p-3 tw-space-y-3">
                        <div class="tw-text-sm tw-font-semibold">Preferências de notificação</div>
                        <div v-if="!notificationOptions.length" class="tw-text-sm tw-text-slate-500">
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
