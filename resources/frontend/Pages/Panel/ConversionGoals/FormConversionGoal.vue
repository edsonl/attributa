<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    conversionGoal: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    defaultTimezoneId: {
        type: Number,
        default: null,
    },
})

const isEdit = computed(() => !!props.conversionGoal)
const timezoneOptions = ref(props.timezones)

const form = useForm({
    goal_code: props.conversionGoal?.goal_code ?? '',
    timezone_id: props.conversionGoal?.timezone_id ?? props.defaultTimezoneId ?? null,
    active: props.conversionGoal?.active ?? true,
})

function normalizeForSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
}

function submit() {
    form.goal_code = String(form.goal_code ?? '').trim()
    // Enforce no spaces/special chars and max 30 before submit.
    form.goal_code = form.goal_code.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 30)

    if (isEdit.value) {
        form.put(route('panel.conversion-goals.update', props.conversionGoal.hashid ?? props.conversionGoal.id))
        return
    }

    form.post(route('panel.conversion-goals.store'))
}

function onTimezoneFilter(val, update) {
    update(() => {
        const needle = normalizeForSearch(String(val ?? '').trim())
        if (needle === '') {
            timezoneOptions.value = props.timezones
            return
        }

        timezoneOptions.value = props.timezones.filter((option) =>
            normalizeForSearch(option?.label ?? '').includes(needle) ||
            normalizeForSearch(option?.identifier ?? '').includes(needle)
        )
    })
}

function onGoalCodeInput(value) {
    const sanitized = String(value ?? '')
        .replace(/[^A-Za-z0-9_-]/g, '')
        .slice(0, 30)

    if (sanitized !== value) {
        form.goal_code = sanitized
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="tw-space-y-2 tw-pt-4">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
            <div class="tw-space-y-2">
                <q-input
                    v-model="form.goal_code"
                    label="Codigo da meta de conversao"
                    outlined
                    dense
                    maxlength="30"
                    counter
                    :error="Boolean(form.errors.goal_code)"
                    :error-message="form.errors.goal_code"
                    @update:model-value="onGoalCodeInput"
                />
                <q-banner dense class="tw-bg-sky-50 tw-text-sky-800 tw-rounded-md">
                    Use apenas letras, números, <code>-</code> e <code>_</code>. Sem espaços ou caracteres especiais.
                    Limite: <strong>30 caracteres</strong>.
                </q-banner>
            </div>

            <div class="tw-space-y-2">
                <q-select
                    v-model="form.timezone_id"
                    :options="timezoneOptions"
                    option-value="id"
                    :option-label="(option) => option?.label ?? option?.identifier ?? `ID ${option?.id ?? ''}`"
                    emit-value
                    map-options
                    use-input
                    input-debounce="0"
                    @filter="onTimezoneFilter"
                    label="Timezone"
                    outlined
                    dense
                    :clearable="false"
                    :error="Boolean(form.errors.timezone_id)"
                    :error-message="form.errors.timezone_id"
                    hint="Deve corresponder ao fuso horario configurado na sua conta do Google Ads."
                />

                <div class="tw-flex tw-items-center tw-px-1 tw-pt-1">
                    <q-toggle
                        v-model="form.active"
                        label="Meta ativa"
                        :true-value="true"
                        :false-value="false"
                    />
                </div>
            </div>
        </div>

        <div class="tw-flex tw-justify-end tw-gap-2">
            <q-btn flat label="Cancelar" @click="router.visit(route('panel.conversion-goals.index'))" />
            <q-btn color="primary" label="Salvar" type="submit" />
        </div>
    </form>
</template>
