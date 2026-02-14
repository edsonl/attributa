<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'

const props = defineProps({
    conversionGoal: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
})

const isEdit = computed(() => !!props.conversionGoal)
const $q = useQuasar()
const timezoneOptions = ref(props.timezones)

const form = useForm({
    goal_code: props.conversionGoal?.goal_code ?? '',
    timezone_id: props.conversionGoal?.timezone_id ?? null,
    active: props.conversionGoal?.active ?? true,
})

function hasNonRecommendedChars(value) {
    const code = String(value ?? '')

    const hasWhitespace = /\s/.test(code)
    const hasAccented = /[^\u0000-\u007F]/.test(code)
    const hasSpecial = /[^A-Za-z0-9_-]/.test(code)

    return hasWhitespace || hasAccented || hasSpecial
}

function confirmRecommendedChars() {
    return new Promise((resolve) => {
        $q.dialog({
            title: 'Atenção',
            message: 'Não é recomendado usar espaços, acentos ou caracteres especiais no código da meta de conversão. Deseja continuar mesmo assim?',
            ok: {
                label: 'Continuar',
                color: 'warning',
                unelevated: true,
            },
            cancel: {
                label: 'Cancelar',
                flat: true,
            },
            persistent: true,
        })
            .onOk(() => resolve(true))
            .onCancel(() => resolve(false))
            .onDismiss(() => resolve(false))
    })
}

async function submit() {
    form.goal_code = String(form.goal_code ?? '').trim()

    if (hasNonRecommendedChars(form.goal_code)) {
        const shouldContinue = await confirmRecommendedChars()
        if (!shouldContinue) {
            return
        }
    }

    if (isEdit.value) {
        form.put(route('panel.conversion-goals.update', props.conversionGoal.id))
        return
    }

    form.post(route('panel.conversion-goals.store'))
}

function onTimezoneFilter(val, update) {
    update(() => {
        const needle = String(val ?? '').trim().toLowerCase()
        if (needle === '') {
            timezoneOptions.value = props.timezones
            return
        }

        timezoneOptions.value = props.timezones.filter((option) =>
            String(option?.label ?? '')
                .toLowerCase()
                .includes(needle)
        )
    })
}
</script>

<template>
    <form @submit.prevent="submit" class="tw-space-y-2 tw-pt-4">
        <q-input
            v-model="form.goal_code"
            label="Codigo da meta de conversao"
            outlined
            dense
            :error="Boolean(form.errors.goal_code)"
            :error-message="form.errors.goal_code"
        />

        <q-select
            v-model="form.timezone_id"
            :options="timezoneOptions"
            option-value="id"
            option-label="label"
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

        <q-toggle
            v-model="form.active"
            label="Meta ativa"
            :true-value="true"
            :false-value="false"
        />

        <div class="tw-flex tw-justify-end tw-gap-2">
            <q-btn flat label="Cancelar" @click="router.visit(route('panel.conversion-goals.index'))" />
            <q-btn color="primary" label="Salvar" type="submit" />
        </div>
    </form>
</template>
