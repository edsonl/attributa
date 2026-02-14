<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'
import ConversionPageviewDetailModal from './ConversionPageviewDetailModal.vue'
import ConversionPageviewDetailDrawer from './ConversionPageviewDetailDrawer.vue'

const rows = ref([])
const loading = ref(false)
const $q = useQuasar()

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'conversion_event_time',
    descending: true
})

const campaignId = ref(null)
const campaigns = ref([])
const detailDialog = ref(false)
const detailDrawer = ref(false)
const detailLoading = ref(false)
const detailPayload = ref(null)
const usePopup = ref(true)
const assetBaseUrl = (
    import.meta.env.VITE_ASSET_URL
        ?? (typeof window !== 'undefined' ? window.location.origin : 'http://attributa.site')
).replace(/\/$/, '')

const columns = [
    { name: 'conversion_event_time', label: 'Data', field: 'conversion_event_time_formatted', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'campaign_code', label: 'Cód/Campanha', field: 'campaign_code', sortable: true, align: 'left' },
    { name: 'conversion_name', label: 'Pixel/Conversão', field: 'conversion_name', sortable: true, align: 'left' },
    { name: 'pageview_id', label: 'Pageview ID', field: 'pageview_id', sortable: true, align: 'left' },
    { name: 'country_code', label: 'País', field: 'country_code', sortable: true, align: 'left' },
    { name: 'region_name', label: 'Região', field: 'region_name', sortable: true, align: 'left' },
    { name: 'city', label: 'Cidade', field: 'city', sortable: true, align: 'left' },
    { name: 'conversion_value', label: 'Valor (USD)', field: 'conversion_value', sortable: true, align: 'left' },
    { name: 'currency_code', label: 'Moeda', field: 'currency_code', sortable: true, align: 'left' },
    { name: 'google_upload_status', label: 'Status Google', field: 'google_upload_status', sortable: true, align: 'left' },
]

function formatUSD(value) {
    if (value === null || value === undefined || value === '') return '-'
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

function resolveCountryFlag(row) {
    const code = row?.country_code
    if (!code) return null

    const lowerCode = String(code).toLowerCase()
    return `${assetBaseUrl}/assets/country-flags/${lowerCode}.svg`
}

function normalizeStatus(value) {
    return String(value ?? '').trim().toLowerCase()
}

function statusBadgeColor(value) {
    const status = normalizeStatus(value)

    if (status === 'exported' || status === 'success') return 'positive'
    if (status === 'prossecing' || status === 'processing') return 'warning'
    if (status === 'pending') return 'grey-6'
    if (status === 'error' || status === 'failed') return 'negative'
    return 'primary'
}

function statusBadgeLabel(value) {
    const status = normalizeStatus(value)

    if (status === 'exported') return 'Exportado'
    if (status === 'prossecing' || status === 'processing') return 'Processando'
    if (status === 'pending') return 'Pendente'
    if (status === 'error' || status === 'failed') return 'Erro'
    if (status === 'success') return 'Sucesso'
    return value || '-'
}

async function openPageviewDetails(pageviewId) {
    if (!pageviewId) {
        $q.notify({
            type: 'warning',
            message: 'Pageview não encontrada para esta conversão.',
        })
        return
    }

    detailDialog.value = usePopup.value
    detailDrawer.value = !usePopup.value
    detailLoading.value = true
    detailPayload.value = null

    try {
        const response = await axios.get(route('panel.atividade.pageviews.show', pageviewId))
        detailPayload.value = response.data
    } catch {
        detailDialog.value = false
        detailDrawer.value = false
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar os detalhes da pageview.',
        })
    } finally {
        detailLoading.value = false
    }
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
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-conversion_value="props">
                <q-td :props="props">{{ formatUSD(props.value) }}</q-td>
            </template>

            <template #body-cell-pageview_id="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-1">
                        <span>{{ props.value ?? '-' }}</span>
                        <q-btn
                            v-if="props.value"
                            dense
                            flat
                            round
                            size="sm"
                            icon="manage_search"
                            color="primary"
                            @click="openPageviewDetails(props.value)"
                        />
                    </div>
                </q-td>
            </template>

            <template #body-cell-country_code="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <img
                            v-if="resolveCountryFlag(props.row)"
                            :src="resolveCountryFlag(props.row)"
                            :alt="props.value"
                            class="tw-w-6 tw-h-4 tw-rounded-sm tw-object-cover tw-border tw-border-gray-200"
                        />
                        <span>{{ props.value ? props.value.toUpperCase() : '-' }}</span>
                    </div>
                </q-td>
            </template>

            <template #body-cell-region_name="props">
                <q-td :props="props">
                    {{ props.value || '-' }}
                </q-td>
            </template>

            <template #body-cell-city="props">
                <q-td :props="props">
                    {{ props.value || '-' }}
                </q-td>
            </template>

            <template #body-cell-google_upload_status="props">
                <q-td :props="props">
                    <q-badge :color="statusBadgeColor(props.value)" :label="statusBadgeLabel(props.value)" />
                </q-td>
            </template>
        </q-table>
    </q-card>

    <ConversionPageviewDetailModal
        v-model="detailDialog"
        :loading="detailLoading"
        :payload="detailPayload"
        :asset-base-url="assetBaseUrl"
    />
    <ConversionPageviewDetailDrawer
        v-model="detailDrawer"
        :loading="detailLoading"
        :payload="detailPayload"
        :asset-base-url="assetBaseUrl"
    />
</template>
