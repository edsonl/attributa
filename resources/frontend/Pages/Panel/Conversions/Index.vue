<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'
import ConversionPageviewDetailModal from './ConversionPageviewDetailModal.vue'

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
const tableRef = ref(null)
const listFilters = ref({
    include_manual: true,
    include_automatic: true,
})
const dateRange = ref({ from: '', to: '' })
const tempDateRange = ref({ from: '', to: '' })
const datePopupOpen = ref(false)
const filtersReady = ref(false)
const campaigns = ref([])
const timezones = ref([])
const timezoneOptions = ref([])
const detailDialog = ref(false)
const detailLoading = ref(false)
const detailPayload = ref(null)
const manualDialog = ref(false)
const manualSaving = ref(false)
const manualErrors = ref({})
const deleteDialog = ref(false)
const deletingConversion = ref(false)
const deletingRow = ref(null)
const manualForm = ref({
    campaign_id: null,
    conversion_event_time: '',
    conversion_timezone: '',
    gclid: '',
    conversion_value: 1,
})
const exportDialog = ref(false)
const exportLoadingRange = ref(false)
const exportLoadingCampaigns = ref(false)
const exportDownloading = ref(false)
const exportCampaigns = ref([])
const exportRange = ref({
    has_rows: false,
    timezone: 'UTC',
    min_datetime_local: '',
    max_datetime_local: '',
})
const exportForm = ref({
    campaign_id: null,
    date_from: '',
    date_to: '',
    include_manual: true,
    include_automatic: false,
})
const exportFromDraft = ref('')
const exportToDraft = ref('')
const assetBaseUrl = (
    import.meta.env.VITE_ASSET_URL
        ?? (typeof window !== 'undefined' ? window.location.origin : 'http://attributa.site')
).replace(/\/$/, '')

const columns = [
    { name: 'conversion_event_time', label: 'Data', field: 'conversion_event_time_formatted', sortable: true, align: 'left' },
    { name: 'type', label: 'Tipo', field: 'type', sortable: false, align: 'left' },
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
    { name: 'actions', label: 'Ações', field: 'actions', sortable: false, align: 'left' },
]
const deletingIsManual = computed(() => Boolean(deletingRow.value?.is_manual))
const dateRangeLabel = computed(() => {
    const from = String(dateRange.value?.from || '')
    const to = String(dateRange.value?.to || '')
    if (!from || !to) return ''
    return `${formatDateDots(from)}  -  ${formatDateDots(to)}`
})

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
    const raw = String(value ?? '').trim().toLowerCase()
    const byCode = {
        '0': 'pending',
        '1': 'processing',
        '2': 'processing_export',
        '3': 'success',
        '4': 'exported',
        '5': 'error',
    }

    return byCode[raw] ?? raw
}

function statusBadgeColor(value) {
    const status = normalizeStatus(value)

    if (status === 'exported' || status === 'success') return 'positive'
    if (status === 'processing_export' || status === 'processing') return 'warning'
    if (status === 'pending') return 'grey-6'
    if (status === 'error' || status === 'failed') return 'negative'
    return 'primary'
}

function statusBadgeLabel(value) {
    const status = normalizeStatus(value)

    if (status === 'exported') return 'Exportado'
    if (status === 'processing_export' || status === 'processing') return 'Processando'
    if (status === 'pending') return 'Pendente'
    if (status === 'error' || status === 'failed') return 'Erro'
    if (status === 'success') return 'Sucesso'
    return value || '-'
}

function onTypeFilterToggle(type, value) {
    const nextManual = type === 'manual' ? Boolean(value) : listFilters.value.include_manual
    const nextAutomatic = type === 'automatic' ? Boolean(value) : listFilters.value.include_automatic

    listFilters.value = {
        include_manual: nextManual,
        include_automatic: nextAutomatic,
    }
    pagination.value.page = 1
    fetchConversions({ pagination: pagination.value })
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

function openDeleteConversionDialog(row) {
    deletingRow.value = row
    deleteDialog.value = true
}

function closeDeleteConversionDialog() {
    deleteDialog.value = false
    deletingRow.value = null
}

async function confirmDeleteConversion() {
    if (!deletingRow.value || deletingConversion.value) return

    deletingConversion.value = true
    try {
        await axios.delete(route('panel.conversoes.destroy', deletingRow.value.id))
        $q.notify({
            type: 'positive',
            message: 'Conversão excluída com sucesso.',
        })
        closeDeleteConversionDialog()
        await fetchConversions({ pagination: pagination.value })
    } catch (error) {
        const message = error?.response?.data?.message || 'Não foi possível excluir a conversão.'
        $q.notify({
            type: 'negative',
            message,
        })
    } finally {
        deletingConversion.value = false
    }
}

async function openPageviewDetails(pageviewId) {
    if (!pageviewId) {
        $q.notify({
            type: 'warning',
            message: 'Pageview não encontrada para esta conversão.',
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

function fetchTimezones() {
    axios.get(route('panel.conversoes.timezones')).then(res => {
        timezones.value = res.data
        timezoneOptions.value = res.data
    })
}

function getNowDateTimeLocal() {
    const now = new Date()
    const offset = now.getTimezoneOffset()
    const local = new Date(now.getTime() - offset * 60 * 1000)
    return local.toISOString().slice(0, 16)
}

function resolveDefaultManualTimezone(campaignIdValue = null) {
    const selectedCampaign = campaigns.value.find(item => Number(item.id) === Number(campaignIdValue))
    if (selectedCampaign?.timezone_identifier) {
        return selectedCampaign.timezone_identifier
    }

    if (timezones.value.length > 0) {
        const saoPaulo = timezones.value.find(item => item.identifier === 'America/Sao_Paulo')
        return saoPaulo?.identifier || timezones.value[0].identifier
    }

    return 'UTC'
}

function openManualDialog() {
    manualErrors.value = {}
    manualForm.value = {
        campaign_id: campaignId.value ?? null,
        conversion_event_time: getNowDateTimeLocal(),
        conversion_timezone: resolveDefaultManualTimezone(campaignId.value),
        gclid: '',
        conversion_value: 1,
    }
    manualDialog.value = true
}

function onManualCampaignChanged(value) {
    manualForm.value.conversion_timezone = resolveDefaultManualTimezone(value)
}

function onTimezoneFilter(val, update) {
    update(() => {
        const needle = String(val || '').trim().toLowerCase()
        if (needle === '') {
            timezoneOptions.value = timezones.value
            return
        }

        timezoneOptions.value = timezones.value.filter((tz) => {
            const label = String(tz.label || '').toLowerCase()
            const identifier = String(tz.identifier || '').toLowerCase()
            return label.includes(needle) || identifier.includes(needle)
        })
    })
}

function toPickerFormat(value) {
    return String(value || '').replace('T', ' ')
}

function toBackendDateTime(value) {
    return String(value || '').replace(' ', 'T')
}

function resetExportState() {
    exportForm.value = {
        campaign_id: null,
        date_from: '',
        date_to: '',
        include_manual: true,
        include_automatic: false,
    }
    exportFromDraft.value = ''
    exportToDraft.value = ''
    exportRange.value = {
        has_rows: false,
        timezone: 'UTC',
        min_datetime_local: '',
        max_datetime_local: '',
    }
}

function closeManualDialog() {
    manualDialog.value = false
    manualErrors.value = {}
}

async function submitManualConversion() {
    if (manualSaving.value) return

    manualSaving.value = true
    manualErrors.value = {}

    if (!String(manualForm.value.gclid || '').trim()) {
        manualErrors.value = {
            ...manualErrors.value,
            gclid: ['Informe o GCLID.'],
        }
        manualSaving.value = false
        return
    }

    try {
        await axios.post(route('panel.conversoes.store-manual'), {
            campaign_id: manualForm.value.campaign_id,
            conversion_event_time: manualForm.value.conversion_event_time,
            conversion_timezone: manualForm.value.conversion_timezone,
            gclid: manualForm.value.gclid,
            conversion_value: manualForm.value.conversion_value || 1,
        })

        manualDialog.value = false
        $q.notify({
            type: 'positive',
            message: 'Conversão manual cadastrada com sucesso.',
        })
        await fetchConversions({ pagination: pagination.value })
    } catch (error) {
        if (error.response?.status === 422) {
            manualErrors.value = error.response.data.errors || {}
            return
        }

        $q.notify({
            type: 'negative',
            message: 'Não foi possível cadastrar a conversão manual.',
        })
    } finally {
        manualSaving.value = false
    }
}

async function openExportDialog() {
    resetExportState()
    exportDialog.value = true
    exportLoadingCampaigns.value = true

    try {
        const { data } = await axios.get(route('panel.conversoes.campaigns'))
        exportCampaigns.value = data
    } catch {
        exportDialog.value = false
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar as campanhas para exportação.',
        })
    } finally {
        exportLoadingCampaigns.value = false
    }
}

async function onExportCampaignChanged(value) {
    exportForm.value.campaign_id = value
    exportForm.value.date_from = ''
    exportForm.value.date_to = ''
    exportRange.value = {
        has_rows: false,
        timezone: 'UTC',
        min_datetime_local: '',
        max_datetime_local: '',
    }

    if (!value) return

    exportLoadingRange.value = true
    try {
        const { data } = await axios.get(route('panel.conversoes.export-range'), {
            params: { campaign_id: value },
        })

        exportRange.value = data
        exportForm.value.date_from = toPickerFormat(data.min_datetime_local || '')
        exportForm.value.date_to = toPickerFormat(data.max_datetime_local || '')
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar as datas para exportação.',
        })
    } finally {
        exportLoadingRange.value = false
    }
}

function prepareFromPicker() {
    exportFromDraft.value = exportForm.value.date_from || toPickerFormat(exportRange.value.min_datetime_local || '')
}

function applyFromPicker() {
    exportForm.value.date_from = exportFromDraft.value || exportForm.value.date_from
}

function prepareToPicker() {
    exportToDraft.value = exportForm.value.date_to || toPickerFormat(exportRange.value.max_datetime_local || '')
}

function applyToPicker() {
    exportForm.value.date_to = exportToDraft.value || exportForm.value.date_to
}

function closeExportDialog() {
    exportDialog.value = false
    resetExportState()
}

function parseFilenameFromDisposition(disposition) {
    if (!disposition) return null
    const match = /filename=\"?([^\";]+)\"?/i.exec(disposition)
    return match?.[1] || null
}

async function parseBlobErrorMessage(error) {
    const contentType = error?.response?.headers?.['content-type'] || ''
    if (!contentType.includes('application/json')) {
        return null
    }

    try {
        const text = await error.response.data.text()
        const json = JSON.parse(text)
        return json?.message || null
    } catch {
        return null
    }
}

async function exportCsv() {
    if (exportDownloading.value) return
    if (!exportForm.value.campaign_id) {
        $q.notify({
            type: 'warning',
            message: 'Selecione uma campanha para exportar.',
        })
        return
    }
    if (!exportForm.value.include_manual && !exportForm.value.include_automatic) {
        $q.notify({
            type: 'warning',
            message: 'Selecione pelo menos um tipo de conversão para exportar.',
        })
        return
    }

    exportDownloading.value = true

    try {
        const response = await axios.get(route('panel.conversoes.export-csv'), {
            params: {
                campaign_id: exportForm.value.campaign_id,
                date_from: toBackendDateTime(exportForm.value.date_from),
                date_to: toBackendDateTime(exportForm.value.date_to),
                include_manual: exportForm.value.include_manual,
                include_automatic: exportForm.value.include_automatic,
            },
            responseType: 'blob',
        })

        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        const url = window.URL.createObjectURL(blob)
        link.href = url
        link.download = parseFilenameFromDisposition(response.headers['content-disposition']) || 'google_ads_conversions.csv'
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)

        $q.notify({
            type: 'positive',
            message: 'CSV gerado com sucesso.',
        })
        closeExportDialog()
    } catch (error) {
        const message = await parseBlobErrorMessage(error)
        $q.notify({
            type: 'negative',
            message: message || 'Não foi possível gerar o CSV.',
        })
    } finally {
        exportDownloading.value = false
    }
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
            campaign_id: campaignId.value,
            date_from: dateRange.value?.from || undefined,
            date_to: dateRange.value?.to || undefined,
            include_manual: listFilters.value.include_manual,
            include_automatic: listFilters.value.include_automatic,
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
    if (!filtersReady.value) return
    pagination.value.page = 1
    fetchConversions({ pagination: pagination.value })
})

watch(
    () => [dateRange.value?.from, dateRange.value?.to],
    () => {
        if (!filtersReady.value) return
        pagination.value.page = 1
        fetchConversions({ pagination: pagination.value })
    }
)

onMounted(() => {
    dateRange.value = getDefaultLast7DaysRange()
    filtersReady.value = true
    fetchCampaigns()
    fetchTimezones()
    fetchConversions({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Conversões" />

    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="q-mb-md tw-flex tw-flex-wrap md:tw-flex-nowrap tw-items-start tw-gap-3">
            <div class="tw-w-full sm:tw-w-[320px] md:tw-w-[300px]">
                <q-select
                    ref="tableRef"
                    v-model="campaignId"
                    :options="campaigns"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    clearable
                    dense
                    outlined
                    class="filter-field campaign-filter-field"
                    label="Filtrar por campanha"
                />
            </div>
            <div class="tw-w-full sm:tw-w-[320px] md:tw-w-[300px]">
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
            <div class="tw-w-full md:tw-flex-1 tw-mt-1 md:tw-mt-2">
                <div class="tw-flex tw-flex-wrap tw-gap-3">
                    <q-checkbox
                        :model-value="listFilters.include_manual"
                        label="Manual"
                        @update:model-value="onTypeFilterToggle('manual', $event)"
                    />
                    <q-checkbox
                        :model-value="listFilters.include_automatic"
                        label="Automática"
                        @update:model-value="onTypeFilterToggle('automatic', $event)"
                    />
                </div>
            </div>
            <div class="tw-w-full md:tw-w-auto md:tw-ml-auto tw-flex tw-gap-2 md:tw-self-start">
                <q-btn
                    color="primary"
                    unelevated
                    icon="download"
                    label="Exportar"
                    :loading="exportLoadingCampaigns"
                    @click="openExportDialog"
                >
                    <q-tooltip anchor="top middle" self="bottom middle">
                        Exportar conversões por campanha e período.
                    </q-tooltip>
                </q-btn>
                <q-btn
                    color="positive"
                    unelevated
                    icon="add"
                    label="Conversão"
                    @click="openManualDialog"
                >
                    <q-tooltip anchor="top middle" self="bottom middle">
                        Adicionar uma nova conversão manual.
                    </q-tooltip>
                </q-btn>
            </div>
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            :rows-per-page-options="[5, 10, 15, 20, 25, 50]"
            @request="fetchConversions"
        >
            <template #body-cell-conversion_event_time="props">
                <q-td :props="props">{{ props.value || '-' }}</q-td>
            </template>

            <template #body-cell-type="props">
                <q-td :props="props">
                    <q-badge
                        :color="props.row.is_manual ? 'info' : 'positive'"
                        :label="props.row.is_manual ? 'Manual' : 'Automática'"
                    />
                </q-td>
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
                    <span v-if="props.row.is_manual">-</span>
                    <q-badge
                        v-else
                        :color="statusBadgeColor(props.row.google_upload_status_slug ?? props.value)"
                        :label="props.row.google_upload_status_label ?? statusBadgeLabel(props.row.google_upload_status_slug ?? props.value)"
                    />
                </q-td>
            </template>

            <template #body-cell-actions="props">
                <q-td :props="props">
                    <q-btn
                        dense
                        flat
                        round
                        size="sm"
                        icon="delete"
                        class="qtable-delete-btn"
                        @click="openDeleteConversionDialog(props.row)"
                    />
                </q-td>
            </template>
        </q-table>
    </q-card>

    <q-dialog v-model="exportDialog" persistent @hide="resetExportState">
        <q-card class="tw-w-full tw-max-w-lg">
            <q-card-section>
                <div class="text-h6">Exportar CSV (Google)</div>
            </q-card-section>

            <q-separator />

            <q-card-section class="tw-space-y-3">
                <q-select
                    v-model="exportForm.campaign_id"
                    :options="exportCampaigns"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    label="Campanha"
                    outlined
                    dense
                    :loading="exportLoadingCampaigns"
                    @update:model-value="onExportCampaignChanged"
                />

                <q-input
                    v-model="exportForm.date_from"
                    label="Data inicial"
                    outlined
                    dense
                    readonly
                    :disable="!exportForm.campaign_id || !exportRange.has_rows || exportLoadingRange"
                >
                    <template #append>
                        <q-icon name="event" class="cursor-pointer">
                            <q-popup-proxy cover transition-show="scale" transition-hide="scale" @before-show="prepareFromPicker">
                                <q-card>
                                    <q-date
                                        v-model="exportFromDraft"
                                        mask="YYYY-MM-DD HH:mm"
                                    />
                                    <q-time
                                        v-model="exportFromDraft"
                                        mask="YYYY-MM-DD HH:mm"
                                        format24h
                                    />
                                    <q-card-actions align="right">
                                        <q-btn v-close-popup flat label="Cancelar" />
                                        <q-btn v-close-popup flat label="Aplicar" color="primary" @click="applyFromPicker" />
                                    </q-card-actions>
                                </q-card>
                            </q-popup-proxy>
                        </q-icon>
                    </template>
                </q-input>

                <q-input
                    v-model="exportForm.date_to"
                    label="Data final"
                    outlined
                    dense
                    readonly
                    :disable="!exportForm.campaign_id || !exportRange.has_rows || exportLoadingRange"
                >
                    <template #append>
                        <q-icon name="event" class="cursor-pointer">
                            <q-popup-proxy cover transition-show="scale" transition-hide="scale" @before-show="prepareToPicker">
                                <q-card>
                                    <q-date
                                        v-model="exportToDraft"
                                        mask="YYYY-MM-DD HH:mm"
                                    />
                                    <q-time
                                        v-model="exportToDraft"
                                        mask="YYYY-MM-DD HH:mm"
                                        format24h
                                    />
                                    <q-card-actions align="right">
                                        <q-btn v-close-popup flat label="Cancelar" />
                                        <q-btn v-close-popup flat label="Aplicar" color="primary" @click="applyToPicker" />
                                    </q-card-actions>
                                </q-card>
                            </q-popup-proxy>
                        </q-icon>
                    </template>
                </q-input>

                <div class="tw-flex tw-flex-col tw-gap-1">
                    <q-checkbox
                        v-model="exportForm.include_manual"
                        label="Conversão manual"
                    />
                    <q-checkbox
                        v-model="exportForm.include_automatic"
                        label="Conversão automática"
                    />
                </div>

                <q-banner v-if="exportForm.campaign_id && !exportRange.has_rows && !exportLoadingRange" dense rounded class="bg-orange-1 text-warning">
                    Não existem conversões com identificadores de clique para exportar nesta campanha.
                </q-banner>
            </q-card-section>

            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Fechar" @click="closeExportDialog" />
                <q-btn
                    color="primary"
                    unelevated
                    label="Gerar CSV"
                    :disable="!exportForm.campaign_id || !exportRange.has_rows || !exportForm.date_from || !exportForm.date_to || (!exportForm.include_manual && !exportForm.include_automatic)"
                    :loading="exportDownloading"
                    @click="exportCsv"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-dialog v-model="manualDialog" persistent>
        <q-card class="tw-w-full tw-max-w-xl">
            <q-card-section>
                <div class="text-h6">Cadastrar conversão manual</div>
            </q-card-section>

            <q-separator />

            <q-card-section class="tw-space-y-3">
                <q-select
                    v-model="manualForm.campaign_id"
                    :options="campaigns"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    label="Campanha"
                    outlined
                    dense
                    @update:model-value="onManualCampaignChanged"
                    :error="Boolean(manualErrors.campaign_id)"
                    :error-message="manualErrors.campaign_id?.[0]"
                />

                <q-input
                    v-model="manualForm.conversion_event_time"
                    type="datetime-local"
                    label="Data/hora da conversão"
                    outlined
                    dense
                    :error="Boolean(manualErrors.conversion_event_time)"
                    :error-message="manualErrors.conversion_event_time?.[0]"
                />

                <q-input
                    v-model.number="manualForm.conversion_value"
                    type="number"
                    step="0.01"
                    min="0"
                    label="Valor (USD)"
                    outlined
                    dense
                    :error="Boolean(manualErrors.conversion_value)"
                    :error-message="manualErrors.conversion_value?.[0]"
                    hint="Padrão: 1. Se não alterar, será salvo como 1."
                />

                <q-select
                    v-model="manualForm.conversion_timezone"
                    :options="timezoneOptions"
                    option-label="label"
                    option-value="identifier"
                    emit-value
                    map-options
                    use-input
                    fill-input
                    hide-selected
                    input-debounce="0"
                    @filter="onTimezoneFilter"
                    label="Timezone do evento"
                    outlined
                    dense
                    :error="Boolean(manualErrors.conversion_timezone)"
                    :error-message="manualErrors.conversion_timezone?.[0]"
                />

                <q-input
                    v-model.trim="manualForm.gclid"
                    label="GCLID"
                    outlined
                    dense
                    :rules="[v => !!String(v || '').trim() || 'Informe o GCLID']"
                    :error="Boolean(manualErrors.gclid)"
                    :error-message="manualErrors.gclid?.[0]"
                    hint="Obrigatório."
                />

            </q-card-section>

            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="closeManualDialog" />
                <q-btn
                    color="primary"
                    unelevated
                    label="Salvar"
                    :loading="manualSaving"
                    @click="submitManualConversion"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-dialog v-model="deleteDialog" persistent>
        <q-card class="tw-w-full tw-max-w-xl">
            <q-card-section>
                <div class="text-h6">
                    {{ deletingIsManual ? 'Excluir conversão manual?' : 'Excluir conversão automática?' }}
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section class="tw-space-y-3">
                <div v-if="deletingIsManual" class="text-body1">
                    Esta conversão foi criada manualmente. Deseja realmente removê-la? Esta ação não poderá ser desfeita.
                </div>
                <div v-else class="text-body1">
                    Esta conversão foi gerada automaticamente com base em dados de rastreamento.
                    A exclusão não é recomendada, pois pode impactar relatórios históricos, análises de desempenho e reconciliação com plataformas externas.
                    Esta ação não poderá ser desfeita.
                </div>
            </q-card-section>

            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="closeDeleteConversionDialog" />
                <q-btn
                    color="negative"
                    unelevated
                    label="Excluir conversão"
                    :loading="deletingConversion"
                    @click="confirmDeleteConversion"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <ConversionPageviewDetailModal
        v-model="detailDialog"
        :loading="detailLoading"
        :payload="detailPayload"
        :asset-base-url="assetBaseUrl"
    />
</template>

<style scoped>
.filter-field :deep(.q-field__control) {
    min-height: 44px;
}

.campaign-filter-field :deep(.q-field__native) {
    align-items: center;
    line-height: 1.35;
    padding-top: 2px !important;
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
</style>
