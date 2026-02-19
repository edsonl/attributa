<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FormConversionGoal from './FormConversionGoal.vue'

defineProps({
    conversionGoal: {
        type: Object,
        required: true,
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
</script>

<template>
    <Head title="Editar Meta de Conversão" />

    <div class="tw-space-y-3">
        <div class="tw-flex tw-items-center tw-justify-between">
            <h1 class="tw-text-lg tw-font-semibold">
                Editar Meta de Conversão
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
                <FormConversionGoal :conversion-goal="conversionGoal" :timezones="timezones" :default-timezone-id="defaultTimezoneId" />
            </q-card-section>
        </q-card>

        <q-card flat bordered>
            <q-card-section>
                <div class="tw-text-sm tw-font-medium tw-mb-2">
                    Campanhas que usam esta meta
                </div>

                <div v-if="(conversionGoal.campaigns ?? []).length" class="tw-flex tw-flex-wrap tw-gap-2">
                    <q-chip
                        v-for="campaign in conversionGoal.campaigns"
                        :key="campaign.id"
                        dense
                        size="sm"
                        class="tw-text-sm"
                    >
                        {{ campaign.name }}
                    </q-chip>
                </div>

                <div v-else class="tw-text-sm tw-text-slate-500">
                    Nenhuma campanha vinculada.
                </div>
            </q-card-section>
        </q-card>
    </div>
</template>
