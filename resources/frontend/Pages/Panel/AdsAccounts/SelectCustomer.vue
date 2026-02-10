<script setup>
import { router } from '@inertiajs/vue3'
defineProps({
    customers: Array
})
function selectAccount(customerId) {
    router.post(route('panel.ads-accounts.store'), {
        google_ads_customer_id: customerId
    })
}
</script>
<template>
    <q-page padding>
        <h5>Selecionar Conta Google Ads</h5>

        <q-list bordered separator>
            <q-item
                v-for="id in customers"
                :key="id"
                clickable
                @click="selectAccount(id)"
            >
                <q-item-section>
                    {{ id }}
                </q-item-section>
            </q-item>
        </q-list>
    </q-page>
</template>
