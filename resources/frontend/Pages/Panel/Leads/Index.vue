<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'
import PageviewDetailModal from '../Atividade/PageviewDetailModal.vue'

const rows = ref([])
const loading = ref(false)
const $q = useQuasar()

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'created_at',
    descending: true,
})

const campaigns = ref([])
const campaignOptions = ref([])
const platforms = ref([])
const campaignId = ref(null)
const platformId = ref(null)
const selectedStatuses = ref([])
const filtersReady = ref(false)
const dateRange = ref({ from: '', to: '' })
const tempDateRange = ref({ from: '', to: '' })
const datePopupOpen = ref(false)
const detailDialog = ref(false)
const detailLoading = ref(false)
const detailPayload = ref(null)
const payloadDialog = ref(false)
const payloadPretty = ref('')
const assetBaseUrl = (
    import.meta.env.VITE_ASSET_URL
        ?? (typeof window !== 'undefined' ? window.location.origin : 'http://attributa.site')
).replace(/\/$/, '')

const statusOptions = [
    { label: 'Processando', value: 'processing' },
    { label: 'Aprovado', value: 'approved' },
    { label: 'Rejeitado', value: 'rejected' },
    { label: 'Lixo', value: 'trash' },
    { label: 'Cancelado', value: 'cancelled' },
    { label: 'Reembolsado', value: 'refunded' },
    { label: 'Chargeback', value: 'chargeback' },
]

const columns = [
    { name: 'created_at', label: 'Data de criação', field: 'created_at_formatted', sortable: true, align: 'left' },
    { name: 'lead_status', label: 'Status', field: 'lead_status', sortable: true, align: 'left' },
    { name: 'updated_at', label: 'Data de atualização', field: 'updated_at_formatted', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'platform_name', label: 'Plataforma', field: 'platform_name', sortable: true, align: 'left' },
    { name: 'platform_lead_id', label: 'UUID Lead', field: 'platform_lead_id', sortable: true, align: 'left' },
    { name: 'offer_id', label: 'Offer ID', field: 'offer_id', sortable: true, align: 'left' },
    { name: 'payout_amount', label: 'Payout', field: 'payout_amount', sortable: true, align: 'left' },
    { name: 'currency_code', label: 'Moeda', field: 'currency_code', sortable: true, align: 'left' },
    { name: 'details', label: 'Detalhes', field: 'id', sortable: false, align: 'center' },
    { name: 'conversion', label: 'Conversão', field: 'has_conversion', sortable: false, align: 'left' },
    { name: 'callback_log', label: 'Log callback', field: 'payload_json', sortable: false, align: 'center' },
]
const defaultHiddenColumns = ['updated_at']
const visibleColumns = ref(
    columns
        .map(column => column.name)
        .filter(name => !defaultHiddenColumns.includes(name))
)

function isColumnVisible(name) {
    return visibleColumns.value.includes(name)
}

function setColumnVisibility(name, value) {
    const columnSet = new Set(visibleColumns.value)

    if (value) {
        columnSet.add(name)
    } else {
        columnSet.delete(name)
    }

    visibleColumns.value = columns
        .map(column => column.name)
        .filter(columnName => columnSet.has(columnName))
}

const dateRangeLabel = computed(() => {
    const from = String(dateRange.value?.from || '')
    const to = String(dateRange.value?.to || '')
    if (!from || !to) return ''
    return `${formatDateDots(from)}  -  ${formatDateDots(to)}`
})

function normalizeStatuses(value) {
    const allowed = statusOptions.map(item => item.value)
    if (!Array.isArray(value)) return []
    return value.filter(item => allowed.includes(String(item)))
}

function clearStatuses() {
    selectedStatuses.value = []
}

function isStatusSelected(value) {
    return selectedStatuses.value.includes(value)
}

function setStatusSelected(value, enabled) {
    const current = new Set(normalizeStatuses(selectedStatuses.value))
    if (enabled) {
        current.add(value)
    } else {
        current.delete(value)
    }
    selectedStatuses.value = Array.from(current)
}

function onCampaignFilter(val, update) {
    update(() => {
        const needle = String(val || '').trim().toLowerCase()
        if (needle === '') {
            campaignOptions.value = campaigns.value
            return
        }

        campaignOptions.value = campaigns.value.filter((campaign) => {
            const name = String(campaign?.name || '').toLowerCase()
            return name.includes(needle)
        })
    })
}

function toIsoDate(value) {
    const d = new Date(value)
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function formatDateDots(isoDate) {
    if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) return ''
    const [y, m, d] = isoDate.split('-')
    return `${d}.${m}.${y}`
}

function getDefaultLast7DaysRange() {
    const end = new Date()
    const start = new Date()
    start.setDate(end.getDate() - 6)
    return {
        from: toIsoDate(start),
        to: toIsoDate(end),
    }
}

function buildPresetRange(preset) {
    const now = new Date()
    const start = new Date(now)
    const end = new Date(now)

    if (preset === 'last_7') {
        start.setDate(now.getDate() - 6)
    } else if (preset === 'last_31') {
        start.setDate(now.getDate() - 30)
    } else if (preset === 'month_current') {
        start.setDate(1)
    } else if (preset === 'month_previous') {
        const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1)
        const prevEnd = new Date(now.getFullYear(), now.getMonth(), 0)
        start.setTime(prev.getTime())
        end.setTime(prevEnd.getTime())
    }

    return {
        from: toIsoDate(start),
        to: toIsoDate(end),
    }
}

function applyPresetRange(preset) {
    tempDateRange.value = buildPresetRange(preset)
}

function applyTodayRange() {
    const today = toIsoDate(new Date())
    tempDateRange.value = { from: today, to: today }
}

function applyYesterdayRange() {
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    const iso = toIsoDate(yesterday)
    tempDateRange.value = { from: iso, to: iso }
}

function openDatePopup() {
    tempDateRange.value = {
        from: String(dateRange.value?.from || ''),
        to: String(dateRange.value?.to || ''),
    }
}

function cancelDateRangeSelection() {
    datePopupOpen.value = false
}

function applyDateRangeSelection() {
    const from = String(tempDateRange.value?.from || '')
    const to = String(tempDateRange.value?.to || '')
    if (from && to) {
        dateRange.value = { from, to }
    }
    datePopupOpen.value = false
}

function formatPayout(value, currencyCode) {
    const amount = Number(value)
    if (Number.isNaN(amount)) return '-'

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: String(currencyCode || 'USD').toUpperCase(),
        }).format(amount)
    } catch {
        return amount.toFixed(2)
    }
}

function syncFiltersToUrl() {
    if (typeof window === 'undefined') return
    const url = new URL(window.location.href)

    if (campaignId.value) {
        url.searchParams.set('campaign_id', String(campaignId.value))
    } else {
        url.searchParams.delete('campaign_id')
    }

    if (platformId.value) {
        url.searchParams.set('platform_id', String(platformId.value))
    } else {
        url.searchParams.delete('platform_id')
    }

    const statusValues = Array.isArray(selectedStatuses.value) ? selectedStatuses.value : []
    if (statusValues.length > 0) {
        url.searchParams.set('lead_statuses', statusValues.join(','))
    } else {
        url.searchParams.delete('lead_statuses')
    }

    if (dateRange.value?.from && dateRange.value?.to) {
        url.searchParams.set('date_from', dateRange.value.from)
        url.searchParams.set('date_to', dateRange.value.to)
    } else {
        url.searchParams.delete('date_from')
        url.searchParams.delete('date_to')
    }

    window.history.replaceState({}, '', url.toString())
}

function hydrateFiltersFromUrl() {
    if (typeof window === 'undefined') {
        dateRange.value = getDefaultLast7DaysRange()
        return
    }

    const params = new URLSearchParams(window.location.search)
    campaignId.value = params.get('campaign_id') ? Number(params.get('campaign_id')) : null
    platformId.value = params.get('platform_id') ? Number(params.get('platform_id')) : null

    // Sempre inicia sem status selecionado ao acessar a página.
    selectedStatuses.value = []

    const dateFrom = params.get('date_from')
    const dateTo = params.get('date_to')
    const isValidDate = (v) => /^\d{4}-\d{2}-\d{2}$/.test(String(v || ''))

    if (isValidDate(dateFrom) && isValidDate(dateTo)) {
        dateRange.value = { from: dateFrom, to: dateTo }
        return
    }

    dateRange.value = getDefaultLast7DaysRange()
}

function fetchCampaigns() {
    axios.get(route('panel.leads.campaigns')).then((res) => {
        campaigns.value = res.data
        campaignOptions.value = res.data
    })
}

function fetchPlatforms() {
    axios.get(route('panel.leads.platforms')).then((res) => {
        platforms.value = res.data
    })
}

function fetchLeads(props) {
    loading.value = true

    const { page, rowsPerPage, sortBy, descending } = props.pagination

    return axios
        .get(route('panel.leads.data'), {
            params: {
                page,
                per_page: rowsPerPage,
                sortBy,
                descending,
                campaign_id: campaignId.value || undefined,
                platform_id: platformId.value || undefined,
                lead_statuses: Array.isArray(selectedStatuses.value) && selectedStatuses.value.length > 0 ? selectedStatuses.value : undefined,
                date_from: dateRange.value?.from || undefined,
                date_to: dateRange.value?.to || undefined,
            },
        })
        .then((res) => {
            rows.value = res.data.data
            pagination.value = {
                ...pagination.value,
                page: res.data.current_page,
                rowsPerPage: res.data.per_page,
                rowsNumber: res.data.total,
                sortBy,
                descending,
            }
        })
        .finally(() => {
            loading.value = false
        })
}

async function openPageviewDetails(pageviewId) {
    if (!pageviewId) {
        $q.notify({
            type: 'warning',
            message: 'Este lead não possui pageview associada.',
        })
        return
    }

    detailDialog.value = true
    detailLoading.value = true
    detailPayload.value = null

    try {
        const response = await axios.get(route('panel.atividade.pageviews.show', pageviewId))
        detailPayload.value = response.data
    } catch {
        detailDialog.value = false
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar os detalhes da visita.',
        })
    } finally {
        detailLoading.value = false
    }
}

function openPayloadLog(row) {
    const raw = row?.payload_json
    if (!raw) {
        $q.notify({
            type: 'warning',
            message: 'Sem payload salvo para este lead.',
        })
        return
    }

    try {
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw
        payloadPretty.value = JSON.stringify(parsed, null, 2)
    } catch {
        payloadPretty.value = String(raw)
    }

    payloadDialog.value = true
}

watch([campaignId, platformId], () => {
    if (!filtersReady.value) return
    pagination.value.page = 1
    syncFiltersToUrl()
    fetchLeads({ pagination: pagination.value })
})

watch(
    selectedStatuses,
    () => {
        if (!filtersReady.value) return
        pagination.value.page = 1
        syncFiltersToUrl()
        fetchLeads({ pagination: pagination.value })
    },
    { deep: true }
)

watch(
    () => [dateRange.value?.from, dateRange.value?.to],
    () => {
        if (!filtersReady.value) return
        pagination.value.page = 1
        syncFiltersToUrl()
        fetchLeads({ pagination: pagination.value })
    }
)

onMounted(() => {
    hydrateFiltersFromUrl()
    syncFiltersToUrl()
    filtersReady.value = true
    fetchCampaigns()
    fetchPlatforms()
    fetchLeads({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Leads" />
    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="row q-mb-md items-start q-col-gutter-md">
            <div class="col-12 col-sm-6 col-md-4 col-lg-2 q-mb-sm q-mb-md-0">
                <q-input
                    dense
                    outlined
                    readonly
                    class="filter-field"
                    label="Período"
                    :model-value="dateRangeLabel"
                    placeholder="Selecione um intervalo"
                >
                    <template #prepend>
                        <q-icon name="event" />
                    </template>
                    <template #append>
                        <q-icon name="expand_more" />
                    </template>
                    <q-popup-proxy
                        v-model="datePopupOpen"
                        anchor="bottom left"
                        self="top left"
                        :offset="[0, 6]"
                        transition-show="scale"
                        transition-hide="scale"
                        @before-show="openDatePopup"
                    >
                        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-[150px_1fr] tw-gap-3 tw-p-3">
                            <div class="tw-flex tw-flex-col tw-gap-2">
                                <q-btn flat no-caps dense label="Hoje" @click="applyTodayRange" />
                                <q-btn flat no-caps dense label="Ontem" @click="applyYesterdayRange" />
                                <q-btn flat no-caps dense label="Últimos 7 dias" @click="applyPresetRange('last_7')" />
                                <q-btn flat no-caps dense label="Últimos 31 dias" @click="applyPresetRange('last_31')" />
                                <q-btn flat no-caps dense label="Mês atual" @click="applyPresetRange('month_current')" />
                                <q-btn flat no-caps dense label="Mês anterior" @click="applyPresetRange('month_previous')" />
                            </div>
                            <q-date
                                v-model="tempDateRange"
                                range
                                mask="YYYY-MM-DD"
                                minimal
                                class="period-date-picker"
                            />
                        </div>
                        <div class="tw-flex tw-justify-end tw-gap-2 tw-px-3 tw-pb-3">
                            <q-btn flat no-caps label="Cancelar" @click="cancelDateRangeSelection" />
                            <q-btn color="primary" no-caps label="Aplicar" @click="applyDateRangeSelection" />
                        </div>
                    </q-popup-proxy>
                </q-input>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 q-mb-sm q-mb-md-0">
                <q-select
                    v-model="campaignId"
                    :options="campaignOptions"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    use-input
                    fill-input
                    hide-selected
                    input-debounce="0"
                    clearable
                    dense
                    outlined
                    hide-bottom-space
                    class="filter-field campaign-filter-field"
                    label="Filtrar por campanha"
                    @filter="onCampaignFilter"
                />
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2 q-mb-sm q-mb-md-0">
                <q-select
                    v-model="platformId"
                    :options="platforms"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    clearable
                    dense
                    outlined
                    class="filter-field"
                    label="Filtrar por plataforma"
                />
            </div>
            <div class="col-auto q-mb-sm q-mb-md-0">
                <q-btn
                    dense
                    flat
                    round
                    icon="filter_list"
                    color="grey-7"
                    aria-label="Filtrar status"
                >
                    <q-badge
                        v-if="selectedStatuses.length > 0"
                        color="primary"
                        floating
                    >
                        {{ selectedStatuses.length }}
                    </q-badge>
                    <q-tooltip>Filtrar status</q-tooltip>
                    <q-menu anchor="bottom right" self="top right">
                        <q-list dense style="min-width: 220px">
                            <q-item
                                v-for="status in statusOptions"
                                :key="status.value"
                                clickable
                                @click="setStatusSelected(status.value, !isStatusSelected(status.value))"
                            >
                                <q-item-section avatar>
                                    <q-checkbox
                                        dense
                                        :model-value="isStatusSelected(status.value)"
                                        @click.stop
                                        @update:model-value="value => setStatusSelected(status.value, value)"
                                    />
                                </q-item-section>
                                <q-item-section>{{ status.label }}</q-item-section>
                            </q-item>
                            <q-separator />
                            <q-item clickable @click="clearStatuses">
                                <q-item-section class="tw-text-primary">Limpar seleção</q-item-section>
                            </q-item>
                        </q-list>
                    </q-menu>
                </q-btn>
            </div>
            <div class="col-auto q-mb-sm q-mb-md-0">
                <q-btn
                    dense
                    flat
                    round
                    icon="view_column"
                    color="grey-7"
                    aria-label="Organizar colunas"
                >
                    <q-tooltip>Organizar colunas</q-tooltip>
                    <q-menu anchor="bottom right" self="top right">
                        <q-list dense style="min-width: 220px">
                            <q-item
                                v-for="column in columns"
                                :key="column.name"
                                clickable
                                @click="setColumnVisibility(column.name, !isColumnVisible(column.name))"
                            >
                                <q-item-section avatar>
                                    <q-checkbox
                                        dense
                                        :model-value="isColumnVisible(column.name)"
                                        @click.stop
                                        @update:model-value="value => setColumnVisibility(column.name, value)"
                                    />
                                </q-item-section>
                                <q-item-section>{{ column.label }}</q-item-section>
                            </q-item>
                        </q-list>
                    </q-menu>
                </q-btn>
            </div>
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            :visible-columns="visibleColumns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            :rows-per-page-options="[10, 15, 20, 25, 50]"
            @request="fetchLeads"
        >
            <template #body-cell-created_at="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-updated_at="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-campaign_name="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-platform_name="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-platform_lead_id="props">
                <q-td :props="props">
                    <span class="tw-font-mono">{{ props.value || '-' }}</span>
                </q-td>
            </template>

            <template #body-cell-offer_id="props">
                <q-td :props="props">{{ props.value ?? '-' }}</q-td>
            </template>

            <template #body-cell-lead_status="props">
                <q-td :props="props">
                    <q-badge
                        :color="props.row.lead_status_color || 'warning'"
                        :label="props.row.lead_status_label || props.row.lead_status || '-'"
                    />
                </q-td>
            </template>

            <template #body-cell-payout_amount="props">
                <q-td :props="props">{{ formatPayout(props.value, props.row.currency_code) }}</q-td>
            </template>

            <template #body-cell-currency_code="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-details="props">
                <q-td :props="props">
                    <q-btn
                        dense
                        flat
                        round
                        size="sm"
                        icon="manage_search"
                        color="primary"
                        :disable="!props.row.pageview_id"
                        @click="openPageviewDetails(props.row.pageview_id)"
                    />
                </q-td>
            </template>

            <template #body-cell-conversion="props">
                <q-td :props="props">
                    <q-badge
                        :color="props.row.has_conversion ? 'positive' : 'grey-6'"
                        :label="props.row.has_conversion ? 'Criada' : 'Pendente'"
                    />
                </q-td>
            </template>

            <template #body-cell-callback_log="props">
                <q-td :props="props">
                    <q-btn
                        dense
                        flat
                        round
                        size="sm"
                        icon="article"
                        color="primary"
                        :disable="!props.row.payload_json"
                        @click="openPayloadLog(props.row)"
                    >
                        <q-tooltip>Ver log do callback</q-tooltip>
                    </q-btn>
                </q-td>
            </template>
        </q-table>
    </q-card>

    <PageviewDetailModal
        v-model="detailDialog"
        :loading="detailLoading"
        :payload="detailPayload"
        :asset-base-url="assetBaseUrl"
    />

    <q-dialog v-model="payloadDialog">
        <q-card class="payload-log-card">
            <q-card-section class="tw-flex tw-items-center tw-justify-between">
                <div class="text-h6">Log do callback</div>
                <q-btn flat round dense icon="close" v-close-popup />
            </q-card-section>
            <q-separator />
            <q-card-section>
                <pre class="payload-log-pre">{{ payloadPretty }}</pre>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.filter-field :deep(.q-field__control) {
    min-height: 44px;
}

.campaign-filter-field :deep(.q-field__native) {
    align-items: center;
    line-height: 1.2;
}

.campaign-filter-field :deep(.q-field__control) {
    min-height: 44px !important;
    height: 44px !important;
}

.period-date-picker :deep(.q-date__today) {
    font-weight: 700;
    box-shadow: inset 0 0 0 1px #94a3b8;
    background: rgba(148, 163, 184, 0.12);
}

.period-date-picker :deep(.q-date__today.bg-primary) {
    box-shadow: inset 0 0 0 2px #ffffff;
    background: var(--q-primary);
}

.payload-log-card {
    width: min(920px, 92vw);
    max-width: 92vw;
}

.payload-log-pre {
    margin: 0;
    max-height: 65vh;
    overflow: auto;
    padding: 12px;
    border-radius: 8px;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 12px;
    line-height: 1.45;
}
</style>
