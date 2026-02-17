<script setup>
import { Head, Link } from '@inertiajs/vue3'

import FormCampaign from './FormCampaign.vue'

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    countries: {
        type: Array,
        required: true,
    },
    affiliate_platforms: {
        type: Array,
        required: true,
    },
    googleAdsAccounts: {
        type: Array,
        required: true,
    },
    conversionGoals: {
        type: Array,
        required: true,
    },
    campaignStatuses: {
        type: Array,
        required: true,
    },
    defaults: {
        type: Object,
        default: () => ({}),
    },
})
</script>
<template>
    <Head title="Nova Campanha" />

    <div class="tw-space-y-3">
        <!-- Header -->
        <div class="tw-flex tw-items-center tw-justify-between">
            <h1 class="tw-text-lg tw-font-semibold">
                Nova Campanha
            </h1>

            <Link :href="route('panel.campaigns.index')">
                <q-btn
                    flat
                    icon="arrow_back"
                    label="Voltar"
                />
            </Link>
        </div>
        <!-- Form -->
        <q-card flat bordered>
            <q-card-section>
                <FormCampaign
                    :channels="channels"
                    :countries="countries"
                    :googleAdsAccounts="googleAdsAccounts"
                    :affiliate_platforms="affiliate_platforms"
                    :conversion-goals="conversionGoals"
                    :campaign-statuses="campaignStatuses"
                    :defaults="defaults"
                />
            </q-card-section>
        </q-card>
    </div>
</template>
<style lang="css" scoped>
  .bg-white-campanha {
    background-color: #fff;
  }
</style>
