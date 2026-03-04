<script setup>
const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    caption: { type: String, default: '' },
    icon: { type: String, default: 'info' },
    color: { type: String, default: 'primary' },
})
</script>

<template>
    <q-card flat bordered class="metric-card">
        <q-card-section class="tw-flex tw-items-start tw-justify-between tw-gap-3">
            <div class="tw-min-w-0">
                <div class="tw-text-xs tw-uppercase tw-tracking-wide tw-text-slate-500">
                    {{ props.title }}
                </div>
                <div class="tw-mt-1 tw-text-2xl tw-font-semibold tw-text-slate-900">
                    {{ props.value }}
                </div>
                <div v-if="props.caption" class="tw-mt-2 tw-text-sm tw-text-slate-500">
                    {{ props.caption }}
                </div>
            </div>

            <q-avatar :color="props.color" text-color="white" size="40px">
                <q-icon :name="props.icon" />
            </q-avatar>
        </q-card-section>
    </q-card>
</template>

<style scoped>
.metric-card {
    border-radius: 18px;
}
</style>
