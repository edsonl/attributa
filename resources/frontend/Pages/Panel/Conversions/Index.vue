<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'

const rows = ref([])
const loading = ref(false)

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'conversion_event_time',
    descending: true
})

const campaignId = ref(null)
const campaigns = ref([])

const columns = [
    { name: 'conversion_event_time', label: 'Data', field: 'conversion_event_time', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'campaign_code', label: 'Cód/Campanha', field: 'campaign_code', sortable: true, align: 'left' },
    { name: 'conversion_name', label: 'Pixel/Conversão', field: 'conversion_name', sortable: true, align: 'left' },
    { name: 'conversion_value', label: 'Valor (USD)', field: 'conversion_value', sortable: true, align: 'left' },
    { name: 'currency_code', label: 'Moeda', field: 'currency_code', sortable: true, align: 'left' },
    { name: 'gclid', label: 'GCLID', field: 'gclid', sortable: true, align: 'left' },
    { name: 'pageview_url', label: 'URL', field: 'pageview_url', sortable: true, align: 'left' },
    { name: 'pageview_ip', label: 'IP', field: 'pageview_ip', sortable: true, align: 'left' },
]

function formatDateBR(date) {
    if (!date) return '-'
    return new Date(date).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })
}

function formatUSD(value) {
    if (value === null || value === undefined || value === '') return '-'
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

function fetchCampaigns() {
    axios.get(route('panel.conversoes.campaigns')).then(res => {
        campaigns.value = res.data
    })
}

function fetchConversions(props = null) {
    loading.value = true

    const p = props?.pagination ?? pagination.value
    const { page, rowsPerPage, sortBy, descending } = p

    axios.get(route('panel.conversoes.data'), {
        params: {
            page,
            per_page: rowsPerPage,
            sortBy,
            descending,
            campaign_id: campaignId.value
        }
    })
        .then(res => {
            rows.value = res.data.data
            pagination.value = {
                ...pagination.value,
                page: res.data.current_page,
                rowsPerPage: res.data.per_page,
                rowsNumber: res.data.total,
                sortBy,
                descending
            }
        })
        .finally(() => { loading.value = false })
}

watch(campaignId, () => {
    pagination.value.page = 1
    fetchConversions({ pagination: pagination.value })
})

onMounted(() => {
    fetchCampaigns()
    fetchConversions({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Conversões" />

    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="row q-mb-md items-center">
            <div class="col-12 col-md-4">
                <q-select
                    v-model="campaignId"
                    :options="campaigns"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    clearable
                    label="Filtrar por campanha"
                />
            </div>
        </div>

        <q-table
            title="Conversões"
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            @request="fetchConversions"
        >
            <template #body-cell-conversion_event_time="props">
                <q-td :props="props">{{ formatDateBR(props.value) }}</q-td>
            </template>

            <template #body-cell-conversion_value="props">
                <q-td :props="props">{{ formatUSD(props.value) }}</q-td>
            </template>

            <template #body-cell-pageview_url="props">
                <q-td :props="props">
                    <a :href="props.value" target="_blank" rel="noopener" class="tw-text-blue-600 tw-underline">
                        {{ props.value }}
                    </a>
                </q-td>
            </template>
        </q-table>
    </q-card>
</template>
