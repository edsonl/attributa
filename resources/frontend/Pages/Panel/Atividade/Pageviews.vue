<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'
import PageviewDetailModal from './PageviewDetailModal.vue'

const rows = ref([])
const loading = ref(false)
const tableRef = ref(null)
const $q = useQuasar()

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'created_at',
    descending: true,
})

const campaignId = ref(null)
const campaigns = ref([])

const FALLBACK_IP_CATEGORY = {
    label: 'Não determinado',
    color: '#FCE7F3',
    description: 'Categoria ainda não determinada.',
}

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailPayload = ref(null)
const selected = ref([])
const hasSelection = computed(() => selected.value.length > 0)
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
    { name: 'details', label: 'Detalhes', field: 'id', align: 'center' },
    { name: 'actions', label: 'Ações', field: 'id', align: 'right' },
]
/*
function formatDateBR(value) {
    if (!value) return '-'
    try {
        return new Date(value).toLocaleString('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'medium',
        })
    } catch {
        return value
    }
}*/

function resolveIpCategoryMeta(row) {
    return {
        label: row?.ip_category_name ?? FALLBACK_IP_CATEGORY.label,
        color: row?.ip_category_color ?? FALLBACK_IP_CATEGORY.color,
        description: row?.ip_category_description ?? FALLBACK_IP_CATEGORY.description,
    }
}

function resolveTrafficMeta(row) {
    return {
        label: row?.traffic_source_name ?? '-',
        icon: row?.traffic_source_icon || 'help_outline',
        color: row?.traffic_source_color || '#64748B',
    }
}

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

function resolveDeviceBrowserMeta(row) {
    return {
        deviceLabel: formatDeviceType(row?.device_type),
        deviceIcon: row?.device_icon || 'devices_other',
        deviceColor: row?.device_color || '#64748B',
        browserLabel: String(row?.browser_name || '').trim() || '-',
    }
}

async function copyText(value, label = 'Valor') {
    if (!value) return

    try {
        await navigator.clipboard.writeText(String(value))
        $q.notify({ type: 'positive', message: `${label} copiado.` })
    } catch {
        $q.notify({ type: 'negative', message: `Não foi possível copiar ${label.toLowerCase()}.` })
    }
}

function fetchCampaigns() {
    axios.get(route('panel.atividade.campaigns')).then(res => {
        campaigns.value = res.data
    })
}

function fetchPageviews(props) {
    loading.value = true

    const { page, rowsPerPage, sortBy, descending } = props.pagination

    return axios
        .get(route('panel.atividade.pageviews.data'), {
            params: {
                page,
                per_page: rowsPerPage,
                sortBy,
                descending,
                campaign_id: campaignId.value,
            },
        })
        .then(res => {
            rows.value = res.data.data
            selected.value = []

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

async function deletePageview(id) {
    if (!id) return

    let confirmed = true
    const hasWindow = typeof window !== 'undefined'
    const confirmDialog = hasWindow ? window.$confirm : null

    if (confirmDialog) {
        confirmed = await confirmDialog({
            title: 'Excluir pageview',
            message: 'Esta ação é permanente. Deseja realmente remover?',
            okLabel: 'Remover',
            okColor: 'negative',
        })
    } else if (hasWindow) {
        confirmed = window.confirm('Excluir este pageview?')
    }

    if (!confirmed) return

    loading.value = true

    try {
        const { data } = await axios.delete(route('panel.atividade.pageviews.destroy', id))

        if (data?.deleted === false) {
            $q.notify({
                type: 'warning',
                message: data?.message || 'Pageview convertido não pode ser excluído.',
            })
            return
        }

        $q.notify({
            type: 'positive',
            message: data?.message || 'Pageview excluído com sucesso.',
        })
        await fetchPageviews({ pagination: pagination.value })
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Falha ao excluir pageview.',
        })
    } finally {
        loading.value = false
    }
}

async function deleteSelected() {
    if (!hasSelection.value) return

    let confirmed = true
    const hasWindow = typeof window !== 'undefined'
    const confirmDialog = hasWindow ? window.$confirm : null
    const message = `Você selecionou ${selected.value.length} registro(s). Deseja remover?`

    if (confirmDialog) {
        confirmed = await confirmDialog({
            title: 'Remover pageviews',
            message,
            okLabel: 'Remover',
            okColor: 'negative',
        })
    } else if (hasWindow) {
        confirmed = window.confirm(message)
    }

    if (!confirmed) return

    loading.value = true

    try {
        const { data } = await axios.delete(route('panel.atividade.pageviews.bulk-destroy'), {
            data: { ids: selected.value.map(row => row.id) },
        })

        const deleted = Number(data?.deleted || 0)
        const ignored = Number(data?.ignored_converted || 0)

        if (deleted > 0 && ignored > 0) {
            $q.notify({
                type: 'warning',
                message: `${deleted} removido(s). ${ignored} convertido(s) ignorado(s).`,
            })
        } else if (deleted > 0) {
            $q.notify({
                type: 'positive',
                message: data?.message || 'Pageviews excluídos com sucesso.',
            })
        } else {
            $q.notify({
                type: 'warning',
                message: data?.message || 'Nenhum pageview elegível para exclusão.',
            })
        }

        await fetchPageviews({ pagination: pagination.value })
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Falha ao remover os pageviews selecionados.',
        })
    } finally {
        loading.value = false
    }
}

async function openDetails(row) {
    detailDialog.value = true
    detailLoading.value = true
    detailPayload.value = null

    try {
        const response = await axios.get(route('panel.atividade.pageviews.show', row.id))
        detailPayload.value = response.data
    } finally {
        detailLoading.value = false
    }
}

watch(campaignId, () => {
    pagination.value.page = 1
    fetchPageviews({ pagination: pagination.value })
})

onMounted(() => {
    fetchCampaigns()
    fetchPageviews({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Relatório de atividade" />
    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="row q-mb-md items-center q-col-gutter-md">
            <div class="col-12 col-md-4 q-mb-sm q-mb-md-0">
                <q-select
                    ref="tableRef"
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
            <div class="col-12 col-md-4 col-lg-3 q-mt-sm q-mt-md-0 q-pl-md-sm">
                <q-btn
                    color="negative"
                    label="Remover selecionados"
                    :disable="!hasSelection"
                    @click="deleteSelected"
                    class="tw-w-full lg:tw-w-auto bulk-delete-btn"
                />
            </div>
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            @request="fetchPageviews"
            selection="multiple"
            v-model:selected="selected"
        >
            <template #body-cell-created_at="props">
                <q-td :props="props">
                    {{ props.value }}
                </q-td>
            </template>

            <template #body-cell-ip="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-1">
                        <span
                            class="tw-inline-flex tw-h-3 tw-w-3 tw-rounded-full tw-border tw-border-white/20 tw-shadow-sm"
                            :style="{ backgroundColor: resolveIpCategoryMeta(props.row).color }"
                        />
                        <span>{{ props.value || '-' }}</span>
                        <q-btn
                            v-if="props.value"
                            dense
                            flat
                            round
                            size="sm"
                            icon="content_copy"
                            color="primary"
                            @click="copyText(props.value, 'IP')"
                        >
                            <q-tooltip>Copiar IP</q-tooltip>
                        </q-btn>
                        <q-tooltip class="tw-text-xs tw-max-w-xs tw-leading-snug">
                            {{ resolveIpCategoryMeta(props.row).description }}
                        </q-tooltip>
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
                            :color="'primary'"
                            :style="{ color: resolveTrafficMeta(props.row).color }"
                        />
                        <span>{{ resolveTrafficMeta(props.row).label }}</span>
                    </div>
                </q-td>
            </template>

            <template #body-cell-device_browser="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                        <div class="tw-inline-flex tw-items-center tw-gap-1">
                            <q-icon
                                :name="resolveDeviceBrowserMeta(props.row).deviceIcon"
                                size="18px"
                                :style="{ color: resolveDeviceBrowserMeta(props.row).deviceColor }"
                            />
                            <span>{{ resolveDeviceBrowserMeta(props.row).deviceLabel }}</span>
                        </div>
                        <span
                            v-if="resolveDeviceBrowserMeta(props.row).browserLabel && resolveDeviceBrowserMeta(props.row).browserLabel !== '-'"
                            class="tw-text-slate-400"
                        >
                            •
                        </span>
                        <div
                            v-if="resolveDeviceBrowserMeta(props.row).browserLabel && resolveDeviceBrowserMeta(props.row).browserLabel !== '-'"
                            class="tw-inline-flex tw-items-center tw-gap-1"
                        >
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

            <template #body-cell-details="props">
                <q-td :props="props">
                    <q-btn
                        dense
                        flat
                        round
                        size="sm"
                        icon="manage_search"
                        color="primary"
                        @click="openDetails(props.row)"
                    />
                </q-td>
            </template>

            <template #body-cell-actions="props">
                <q-td :props="props" class="tw-text-right">
                    <q-btn
                        dense
                        flat
                        icon="delete"
                        color="negative"
                        @click="deletePageview(props.row.id)"
                    />
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
</template>

<style scoped>
.bulk-delete-btn:disabled {
    background-color: #e2e8f0 !important;
    color: #94a3b8 !important;
    opacity: 0.4;
    box-shadow: none;
    border-color: transparent;
}
</style>
