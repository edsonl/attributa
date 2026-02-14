<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FormConversionGoal from './FormConversionGoal.vue'

defineProps({
    timezones: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <Head title="Nova Meta de Conversão" />

    <div class="tw-space-y-3">
        <div class="tw-flex tw-items-center tw-justify-between">
            <h1 class="tw-text-lg tw-font-semibold">
                Nova Meta de Conversão
            </h1>

            <Link :href="route('panel.conversion-goals.index')">
                <q-btn
                    flat
                    icon="arrow_back"
                    label="Voltar"
                />
            </Link>
        </div>

        <q-card flat bordered>
            <q-card-section>
                <FormConversionGoal :timezones="timezones" />
            </q-card-section>
        </q-card>
    </div>
</template>
