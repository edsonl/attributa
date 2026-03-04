<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: 'Payload Redis' },
    subtitle: { type: String, default: '' },
    payload: { type: [Object, Array, String, Number, Boolean, null], default: null },
    rawText: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const dialogModel = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const formattedPayload = computed(() => {
    if (props.rawText) {
        return props.rawText
    }

    if (props.payload === null || props.payload === undefined) {
        return ''
    }

    if (typeof props.payload === 'string') {
        return props.payload
    }

    try {
        return JSON.stringify(props.payload, null, 2)
    } catch {
        return String(props.payload)
    }
})
</script>

<template>
    <q-dialog v-model="dialogModel">
        <q-card class="payload-dialog">
            <q-card-section class="tw-flex tw-items-center tw-justify-between">
                <div>
                    <div class="tw-text-lg tw-font-semibold">{{ props.title }}</div>
                    <div v-if="props.subtitle" class="tw-text-sm tw-text-slate-500">
                        {{ props.subtitle }}
                    </div>
                </div>
                <q-btn flat round dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <pre class="payload-pre">{{ formattedPayload }}</pre>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.payload-dialog {
    width: min(960px, 94vw);
    max-width: 94vw;
}

.payload-pre {
    margin: 0;
    max-height: 70vh;
    overflow: auto;
    padding: 14px;
    border-radius: 12px;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 12px;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
