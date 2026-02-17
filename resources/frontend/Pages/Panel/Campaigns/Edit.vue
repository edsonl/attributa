<script setup>
import { Head, Link } from '@inertiajs/vue3'

import FormCampaign from './FormCampaign.vue'

const props = defineProps({
    campaign: {
        type: Object,
        required: true,
    },
    channels: {
        type: Array,
        required: true,
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
})
</script>

<template>
    <Head title="Editar Campanha" />

    <div class="tw-space-y-3">
        <!-- Header -->
        <div class="tw-flex tw-items-center tw-justify-between">
            <h1 class="tw-text-lg tw-font-semibold">
                Editar Campanha
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
                    :campaign="campaign"
                    :channels="channels"
                    :countries="countries"
                    :googleAdsAccounts="googleAdsAccounts"
                    :affiliate_platforms="affiliate_platforms"
                    :conversion-goals="conversionGoals"
                    :campaign-statuses="campaignStatuses"
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
