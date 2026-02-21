<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'

const rows = ref([])
const loading = ref(false)
const tableRef = ref(null)

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'created_at',
    descending: true,
})

const campaignId = ref(null)
const campaigns = ref([])
const dateRange = ref({ from: '', to: '' })
const tempDateRange = ref({ from: '', to: '' })
const datePopupOpen = ref(false)
const filtersReady = ref(false)

const dateRangeLabel = computed(() => {
    const from = String(dateRange.value?.from || '')
    const to = String(dateRange.value?.to || '')
    if (!from || !to) return ''
    return `${formatDateDots(from)}  -  ${formatDateDots(to)}`
})

const assetBaseUrl = (
    import.meta.env.VITE_ASSET_URL
    ?? (typeof window !== 'undefined' ? window.location.origin : 'http://attributa.site')
).replace(/\/$/, '')

const columns = [
    { name: 'created_at', label: 'Data', field: 'created_at_formatted', sortable: true, align: 'left' },
    { name: 'ip', label: 'Classificação / IP', field: 'ip', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'traffic_source', label: 'Origem do Tráfego', field: 'traffic_source_name', sortable: true, align: 'left' },
    { name: 'device_browser', label: 'Dispositivo/Navegador', field: row => resolveDeviceBrowser(row), sortable: true, align: 'left' },
    { name: 'country_code', label: 'País', field: 'country_code', sortable: true, align: 'left' },
    { name: 'region_name', label: 'Região', field: 'region_name', sortable: true, align: 'left' },
    { name: 'city', label: 'Cidade', field: 'city', sortable: true, align: 'left' },
    { name: 'gclid', label: 'GCLID', field: 'has_gclid', sortable: true, align: 'left' },
    { name: 'conversion', label: 'Conversão', field: 'conversion', sortable: true, align: 'left' },
]

function resolveCountryFlag(row) {
    const code = row?.country_code
    if (!code) return null

    const lowerCode = String(code).toLowerCase()
    return `${assetBaseUrl}/assets/country-flags/${lowerCode}.svg`
}

function isConverted(value) {
    return value === true || value === 1 || value === '1'
}

function hasGclid(value) {
    if (value === true || value === 1 || value === '1') return true
    if (typeof value === 'string') return value.trim().length > 0 && value !== '0'
    return false
}

function formatDeviceType(value) {
    const raw = String(value || '').trim()
    if (!raw) return '-'

    return raw
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

function resolveDeviceBrowser(row) {
    const device = formatDeviceType(row?.device_type)
    const browser = String(row?.browser_name || '').trim() || '-'

    if (device === '-' && browser === '-') return '-'
    if (device === '-') return browser
    if (browser === '-') return device
    return `${device} • ${browser}`
}

function resolveTrafficMeta(row) {
    return {
        label: row?.traffic_source_name ?? '-',
        icon: row?.traffic_source_icon || 'help_outline',
        color: row?.traffic_source_color || '#64748B',
    }
}

function resolveIpCategoryMeta(row) {
    return {
        label: row?.ip_category_name ?? 'Não determinado',
        color: row?.ip_category_color ?? '#FCE7F3',
        description: row?.ip_category_description ?? 'Categoria ainda não determinada.',
    }
}

function resolveDeviceBrowserMeta(row) {
    return {
        deviceLabel: formatDeviceType(row?.device_type),
        deviceIcon: row?.device_icon || 'devices_other',
        deviceColor: row?.device_color || '#64748B',
        browserLabel: String(row?.browser_name || '').trim() || '-',
        browserIcon: row?.browser_icon || null,
        browserColor: row?.browser_color || '#64748B',
    }
}

function shouldShowDeviceIcon(row) {
    return resolveDeviceBrowserMeta(row).deviceLabel !== '-'
}

function fetchCampaigns() {
    axios.get(route('panel.estatisticas.campaigns')).then(res => {
        campaigns.value = res.data
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
    if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(String(isoDate || ''))) return ''
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

function syncFiltersToUrl() {
    if (typeof window === 'undefined') return
    const url = new URL(window.location.href)

    if (campaignId.value) {
        url.searchParams.set('campaign_id', String(campaignId.value))
    } else {
        url.searchParams.delete('campaign_id')
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

    const dateFrom = params.get('date_from')
    const dateTo = params.get('date_to')
    const isValidDate = (v) => /^\d{4}-\d{2}-\d{2}$/.test(String(v || ''))

    if (isValidDate(dateFrom) && isValidDate(dateTo)) {
        dateRange.value = { from: dateFrom, to: dateTo }
        return
    }

    dateRange.value = getDefaultLast7DaysRange()
}

function fetchPageviews(props) {
    loading.value = true

    const { page, rowsPerPage, sortBy, descending } = props.pagination

    return axios
        .get(route('panel.estatisticas.data'), {
            params: {
                page,
                per_page: rowsPerPage,
                sortBy,
                descending,
                campaign_id: campaignId.value,
                date_from: dateRange.value?.from || undefined,
                date_to: dateRange.value?.to || undefined,
            },
        })
        .then(res => {
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

watch(campaignId, () => {
    if (!filtersReady.value) return
    pagination.value.page = 1
    syncFiltersToUrl()
    fetchPageviews({ pagination: pagination.value })
})

watch(
    () => [dateRange.value?.from, dateRange.value?.to],
    () => {
        if (!filtersReady.value) return
        pagination.value.page = 1
        syncFiltersToUrl()
        fetchPageviews({ pagination: pagination.value })
    }
)

onMounted(() => {
    hydrateFiltersFromUrl()
    syncFiltersToUrl()
    filtersReady.value = true
    fetchCampaigns()
    fetchPageviews({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Estatísticas" />
    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="row q-mb-md items-center q-col-gutter-md">
            <div class="col-12 col-sm-8 col-md-3 q-mb-sm q-mb-md-0">
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
            <div class="col-12 col-sm-6 col-md-2 q-mb-sm q-mb-md-0">
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
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            :rows-per-page-options="[10, 15, 20, 25, 50]"
            @request="fetchPageviews"
        >
            <template #body-cell-created_at="props">
                <q-td :props="props">
                    {{ props.value }}
                </q-td>
            </template>

            <template #body-cell-ip="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                        <span
                            class="tw-inline-flex tw-h-3 tw-w-3 tw-rounded-full tw-border tw-border-white/20 tw-shadow-sm"
                            :style="{ backgroundColor: resolveIpCategoryMeta(props.row).color }"
                        >
                            <q-tooltip class="tw-text-xs tw-max-w-xs tw-leading-snug">
                                {{ resolveIpCategoryMeta(props.row).description }}
                            </q-tooltip>
                        </span>
                        <span class="tw-text-slate-600">{{ resolveIpCategoryMeta(props.row).label }}</span>
                        <span class="tw-text-slate-400">•</span>
                        <span>{{ props.value || '-' }}</span>
                    </div>
                </q-td>
            </template>

            <template #body-cell-campaign_name="props">
                <q-td :props="props">
                    {{ props.value || '-' }}
                </q-td>
            </template>

            <template #body-cell-traffic_source="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <q-icon
                            :name="resolveTrafficMeta(props.row).icon"
                            size="18px"
                            :style="{ color: resolveTrafficMeta(props.row).color }"
                        />
                        <span>{{ resolveTrafficMeta(props.row).label }}</span>
                    </div>
                </q-td>
            </template>

            <template #body-cell-device_browser="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                        <div
                            v-if="shouldShowDeviceIcon(props.row)"
                            class="tw-inline-flex tw-items-center tw-gap-1"
                        >
                            <q-icon
                                :name="resolveDeviceBrowserMeta(props.row).deviceIcon"
                                size="18px"
                                :style="{ color: resolveDeviceBrowserMeta(props.row).deviceColor }"
                            />
                            <span>{{ resolveDeviceBrowserMeta(props.row).deviceLabel }}</span>
                        </div>
                        <span v-else-if="resolveDeviceBrowserMeta(props.row).browserLabel === '-'">-</span>
                        <span v-else>{{ resolveDeviceBrowserMeta(props.row).browserLabel }}</span>
                        <span
                            v-if="shouldShowDeviceIcon(props.row) && resolveDeviceBrowserMeta(props.row).browserLabel && resolveDeviceBrowserMeta(props.row).browserLabel !== '-'"
                            class="tw-text-slate-400"
                        >
                            •
                        </span>
                        <div
                            v-if="shouldShowDeviceIcon(props.row) && resolveDeviceBrowserMeta(props.row).browserLabel && resolveDeviceBrowserMeta(props.row).browserLabel !== '-'"
                            class="tw-inline-flex tw-items-center tw-gap-1"
                        >
                            <q-icon
                                v-if="resolveDeviceBrowserMeta(props.row).browserIcon"
                                :name="resolveDeviceBrowserMeta(props.row).browserIcon"
                                size="18px"
                                :style="{ color: resolveDeviceBrowserMeta(props.row).browserColor }"
                            />
                            <span>{{ resolveDeviceBrowserMeta(props.row).browserLabel }}</span>
                        </div>
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
                            class="country-flag"
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

            <template #body-cell-gclid="props">
                <q-td :props="props">
                    <q-badge
                        v-if="hasGclid(props.value)"
                        color="primary"
                        label="Sim"
                    />
                    <q-badge
                        v-else
                        color="grey-4"
                        text-color="dark"
                        label="Não"
                    />
                </q-td>
            </template>

            <template #body-cell-conversion="props">
                <q-td :props="props">
                    <q-badge
                        v-if="isConverted(props.value)"
                        color="green"
                        label="Convertido"
                    />
                    <q-badge
                        v-else
                        color="grey-4"
                        text-color="dark"
                        label="Não convertido"
                    />
                </q-td>
            </template>
        </q-table>
    </q-card>
</template>

<style scoped>
.filter-field :deep(.q-field__control) {
    min-height: 44px;
}

.campaign-filter-field :deep(.q-field__native) {
    align-items: center;
    line-height: 1.2;
}

.country-flag {
    width: 20px;
    height: 13px;
    border-radius: 2px;
    object-fit: cover;
    border: 1px solid #e5e7eb;
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
