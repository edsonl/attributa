<script setup>
import { ref } from 'vue'

const props = defineProps({
    defaultTitle: { type: String, default: 'Confirmar ação' },
    defaultOkLabel: { type: String, default: 'Confirmar' },
    defaultCancelLabel: { type: String, default: 'Cancelar' },
    defaultOkColor: { type: String, default: 'negative' },
    defaultCancelColor: { type: String, default: 'primary' },
})

const show = ref(false)
const state = ref({
    title: '', message: '', okLabel: '', cancelLabel: '',
    okColor: '', cancelColor: '', persistent: true,
})

let resolver = null
function open (opts = {}) {
    state.value = {
        title: opts.title ?? props.defaultTitle,
        message: opts.message ?? '',
        okLabel: opts.okLabel ?? props.defaultOkLabel,
        cancelLabel: opts.cancelLabel ?? props.defaultCancelLabel,
        okColor: opts.okColor ?? props.defaultOkColor,
        cancelColor: opts.cancelColor ?? props.defaultCancelColor,
        persistent: opts.persistent ?? true,
    }
    show.value = true
    return new Promise(resolve => { resolver = resolve })
}
function confirm() { show.value = false; resolver?.(true);  resolver = null }
function cancel()  { show.value = false; resolver?.(false); resolver = null }

defineExpose({ open })

</script>

<template>
    <q-dialog v-model="show" :persistent="state.persistent">
        <q-card class="tw-w-full tw-max-w-md">
            <q-card-section class="tw-text-lg tw-font-semibold">
                {{ state.title }}
            </q-card-section>
            <q-card-section class="tw-text-sm tw-text-gray-700">
                <div v-html="state.message" />
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2">
                <q-btn flat :color="state.cancelColor" :label="state.cancelLabel" @click="cancel" />
                <q-btn unelevated :color="state.okColor" :label="state.okLabel" @click="confirm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
