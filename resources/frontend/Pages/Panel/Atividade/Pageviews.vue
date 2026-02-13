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

const FALLBACK_IP_CATEGORY = {
    label: 'Não determinado',
    color: '#FCE7F3',
}

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailPayload = ref(null)
const detailPageview = computed(() => detailPayload.value?.pageview ?? {})
const detailUrl = computed(() => detailPayload.value?.url ?? { full: null, origin: null, path: null, query_params: {} })
const detailGeo = computed(() => detailPayload.value?.geo ?? {})
const detailNetwork = computed(() => detailPayload.value?.network ?? {})
const detailUrlParams = computed(() => {
    const params = detailUrl.value?.query_params ?? {}
    return Object.entries(params)
})
const hasUrlParams = computed(() => detailUrlParams.value.length > 0)
const detailCampaignName = computed(() => detailPageview.value?.campaign?.name ?? '-')
const detailNetworkFlags = computed(() => detailNetwork.value?.flags ?? {})
const detailNetworkCategoryName = computed(
    () => detailNetwork.value?.ip_category?.name ?? '-'
)

const columns = [
    { name: 'created_at', label: 'Data', field: 'created_at', sortable: true, align: 'left' },
    { name: 'ip_category', label: 'Classificação IP', field: row => row?.ip_category_name, sortable: true, align: 'left' },
    { name: 'ip', label: 'IP', field: 'ip', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'country_code', label: 'País', field: 'country_code', sortable: true, align: 'left' },
    { name: 'region_name', label: 'Região', field: 'region_name', sortable: true, align: 'left' },
    { name: 'city', label: 'Cidade', field: 'city', sortable: true, align: 'left' },
    { name: 'url', label: 'URL', field: 'url', sortable: true, align: 'left' },
    { name: 'gclid', label: 'GCLID', field: 'gclid', sortable: true, align: 'left' },
    { name: 'conversion', label: 'Conversão', field: 'conversion', sortable: true, align: 'left' },
    { name: 'details', label: 'Detalhes', field: 'id', align: 'center' },
    { name: 'actions', label: 'Ações', field: 'id', align: 'right' },
]

function formatDateBR(date) {
    if (!date) return '-'
    return new Date(date).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'medium',
    })
}

function stripQueryString(value) {
    if (!value) return '-'

    try {
        const origin = typeof window !== 'undefined' ? window.location.origin : 'https://placeholder.local'
        const parsedUrl = new URL(value, origin)
        return `${parsedUrl.origin}${parsedUrl.pathname}`
    } catch (error) {
        return value.split('?')[0] ?? value
    }
}

function resolveIpCategoryMeta(row) {
    return {
        label: row?.ip_category_name ?? FALLBACK_IP_CATEGORY.label,
        color: row?.ip_category_color ?? FALLBACK_IP_CATEGORY.color,
    }
}

function hasGclid(value) {
    if (value === null || value === undefined) return false
    return String(value).trim().length > 0
}

function isConverted(value) {
    return value === true || value === 1 || value === '1'
}

function formatFlag(value) {
    if (value === null || value === undefined) return '-'
    return value ? 'Sim' : 'Não'
}

function formatParamValue(value) {
    if (value === null || value === undefined) return '-'
    if (Array.isArray(value) || typeof value === 'object') {
        try {
            return JSON.stringify(value)
        } catch (error) {
            return String(value)
        }
    }
    return String(value)
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
        await axios.delete(route('panel.atividade.pageviews.destroy', id))
        await fetchPageviews({ pagination: pagination.value })
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
        <div class="row q-mb-md items-center">
            <div class="col-12 col-md-4">
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
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            @request="fetchPageviews"
        >
            <template #body-cell-created_at="props">
                <q-td :props="props">
                    {{ formatDateBR(props.value) }}
                </q-td>
            </template>

            <template #body-cell-ip_category="props">
                <q-td :props="props">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <span
                            class="tw-inline-flex tw-h-3 tw-w-3 tw-rounded-full tw-border tw-border-white/20 tw-shadow-sm"
                            :style="{ backgroundColor: resolveIpCategoryMeta(props.row).color }"
                        />
                        <span class="tw-text-sm tw-font-medium">
                            {{ resolveIpCategoryMeta(props.row).label }}
                        </span>
                    </div>
                </q-td>
            </template>

            <template #body-cell-ip="props">
                <q-td :props="props">
                    {{ props.value || '-' }}
                </q-td>
            </template>

            <template #body-cell-campaign_name="props">
                <q-td :props="props">
                    {{ props.value || '-' }}
                </q-td>
            </template>

            <template #body-cell-country_code="props">
                <q-td :props="props">
                    {{ props.value ? props.value.toUpperCase() : '-' }}
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

            <template #body-cell-url="props">
                <q-td :props="props">
                    {{ stripQueryString(props.value) }}
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

    <q-dialog v-model="detailDialog" transition-show="scale" transition-hide="scale">
        <q-card class="pageview-detail-card">
            <q-card-section class="tw-flex tw-justify-end">
                <q-btn icon="close" flat round dense @click="detailDialog = false" />
            </q-card-section>

            <q-linear-progress v-if="detailLoading" indeterminate color="primary" />

            <q-card-section v-if="!detailLoading" class="tw-space-y-6">
                <section>
                    <div class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-3 tw-py-1 tw-text-[11px] tw-font-semibold tw-tracking-wide tw-text-slate-600 tw-uppercase">VISITA</div>
                    <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-mt-2">
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Data/Horário</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ formatDateBR(detailPageview.created_at) }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Campanha</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailCampaignName }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">URL completa</div>
                            <div class="tw-text-sm tw-font-medium tw-break-all">
                                {{ detailUrl.full || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">IP</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailPageview.ip || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">GCLID</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailPageview.gclid || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Conversão</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ isConverted(detailPageview.conversion) ? 'Sim' : 'Não' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-3 tw-py-1 tw-text-[11px] tw-font-semibold tw-tracking-wide tw-text-slate-600 tw-uppercase">GEOLOCALIZAÇÃO</div>
                    <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-mt-2">
                        <div v-for="field in [{ label: 'País', key: 'country_name' }, { label: 'Código', key: 'country_code' }, { label: 'Região', key: 'region_name' }, { label: 'Cidade', key: 'city' }, { label: 'Latitude', key: 'latitude' }, { label: 'Longitude', key: 'longitude' }, { label: 'Timezone', key: 'timezone' }]" :key="field.key">
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">{{ field.label }}</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailGeo[field.key] ?? '-' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-3 tw-py-1 tw-text-[11px] tw-font-semibold tw-tracking-wide tw-text-slate-600 tw-uppercase">PARÂMETROS DA URL</div>
                    <div class="tw-mt-2">
                        <q-list v-if="hasUrlParams" bordered class="rounded-borders">
                            <q-item v-for="([key, value]) in detailUrlParams" :key="`${key}-${value}`">
                                <q-item-section>
                                    <div class="tw-text-xs tw-uppercase tw-text-slate-500">{{ key }}</div>
                                    <div class="tw-text-sm tw-font-medium tw-break-all">{{ formatParamValue(value) }}</div>
                                </q-item-section>
                            </q-item>
                        </q-list>
                        <div v-else class="tw-text-sm tw-text-slate-500">Sem parâmetros na URL.</div>
                    </div>
                </section>

                <section>
                    <div class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-3 tw-py-1 tw-text-[11px] tw-font-semibold tw-tracking-wide tw-text-slate-600 tw-uppercase">REDE &amp; SEGURANÇA</div>
                    <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-mt-2">
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">ISP</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailNetwork.isp || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Organização</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailNetwork.organization || '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Categoria IP (Lookup)</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailNetworkCategoryName }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Fraud Score</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailNetwork.fraud_score ?? '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">Última verificação</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ detailNetwork.last_checked ? formatDateBR(detailNetwork.last_checked) : '-' }}
                            </div>
                        </div>
                    </div>
                    <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-mt-4">
                        <div v-for="flag in [{ key: 'is_proxy', label: 'Proxy' }, { key: 'is_vpn', label: 'VPN' }, { key: 'is_tor', label: 'Tor' }, { key: 'is_datacenter', label: 'Datacenter' }, { key: 'is_bot', label: 'Bot' }]" :key="flag.key">
                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">{{ flag.label }}</div>
                            <div class="tw-text-sm tw-font-medium">
                                {{ formatFlag(detailNetworkFlags[flag.key]) }}
                            </div>
                        </div>
                    </div>
                </section>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.pageview-detail-card {
    width: 100vw;
    max-width: 100vw;
}

@media (min-width: 1024px) {
    .pageview-detail-card {
        width: 80vw;
        max-width: 80vw;
    }
}
</style>
