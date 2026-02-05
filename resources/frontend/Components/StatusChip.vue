<script setup>
import { computed } from 'vue'

const props = defineProps({
    value: {
        type: [Boolean, Number],
        required: true,
    },
})

const label = computed(() => {
    return props.value ? 'Ativo' : 'Inativo'
})

const color = computed(() => {
    return props.value ? 'positive' : 'negative'
})
</script>

<template>
    <q-chip
        dense
        :color="color"
        text-color="white"
    >
        {{ label }}
    </q-chip>
</template>
