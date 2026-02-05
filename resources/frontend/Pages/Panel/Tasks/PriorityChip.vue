<script setup>
import { computed } from 'vue'

const props = defineProps({
    value:   { type: String, required: true },
    // [{ value, label, color, css }]
    options: { type: Array,  default: () => [] },
})
/** Lookup O(1) mesmo com muitas linhas */
const map = computed(() => {
    const m = new Map()
    for (const o of props.options) m.set(o.value, o)
    return m
})

const current  = computed(() => map.value.get(props.value))
const label    = computed(() => current.value?.label ?? '—')
const color    = computed(() => current.value?.color ?? 'grey')
const cssClass = computed(() => current.value?.css   ?? 'tw-bg-gray-200 tw-text-gray-800')

</script>
<template>
    <q-badge
        :color="color"
        :label="label"
        :class="['tw-font-medium', 'tw-rounded-md','tw-p-2','tw-pe-3','tw-ps-3','tw-rounded-sm', cssClass]"
        align="middle"
    />
</template>
