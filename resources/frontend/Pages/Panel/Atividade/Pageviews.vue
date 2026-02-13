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

const FALLBACK_IP_CATEGORY_DETAIL = {
    name: 'Não determinado',
    color_hex: '#FCE7F3',
    description: 'Categoria ainda não determinada.',
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
const detailPageviewCategory = computed(() => detailPageview.value?.ip_category ?? FALLBACK_IP_CATEGORY_DETAIL)
const detailNetworkCategory = computed(() => detailNetwork.value?.ip_category ?? null)
const detailNetworkCategoryColor = computed(() => detailNetworkCategory.value?.color_hex ?? '#475569')
const detailNetworkCategoryName = computed(() => detailNetworkCategory.value?.name ?? '-')
const detailNetworkCategoryDescription = computed(() => detailNetworkCategory.value?.description ?? 'Sem descrição.')
const detailCleanUrl = computed(() => stripQueryString(detailUrl.value?.full || ''))

const columns = [
    { name: 'created_at', label: 'Data', field: 'created_at', sortable: true, align: 'left' },
    { name: 'ip_category', label: 'Classificação IP', field: row => row?.ip_category_name, sortable: true, align: 'left' },
    { name: 'ip', label: 'IP', field: 'ip', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'country_code', label: 'País', field: 'country_code', sortable: true, align: 'left' },
    { name: 'region_name', label: 'Região', field: 'region_name', sortable: true, align: 'left' },
    { name: 'city', label: 'Cidade', field: 'city', sortable: true, align: 'left' },
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
                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">VISITA</div>
                        <div class="section-card__body">
                        <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                            <div>
                                <div class="detail-label">Data/Horário</div>
                                <div class="detail-value">
                                    {{ formatDateBR(detailPageview.created_at) }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Campanha</div>
                                <div class="detail-value">
                                    {{ detailCampaignName }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">IP</div>
                                <div class="detail-value">
                                    {{ detailPageview.ip || '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Categoria de IP</div>
                                <div class="tw-mt-1">
                                    <div
                                        class="ip-category-label"
                                        :style="{ color: detailPageviewCategory.color_hex }"
                                    >
                                        {{ detailPageviewCategory.name }}
                                    </div>
                                    <div class="ip-category-description">
                                        {{ detailPageviewCategory.description || 'Sem descrição.' }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Conversão</div>
                                <div class="tw-mt-1">
                                    <q-badge
                                        v-if="isConverted(detailPageview.conversion)"
                                        color="green"
                                        label="Convertido"
                                    />
                                    <q-badge
                                        v-else
                                        color="grey-4"
                                        text-color="dark"
                                        label="Não convertido"
                                    />
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">GEOLOCALIZAÇÃO</div>
                        <div class="section-card__body">
                        <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                            <div v-for="field in [{ label: 'País', key: 'country_name' }, { label: 'Código', key: 'country_code' }, { label: 'Região', key: 'region_name' }, { label: 'Cidade', key: 'city' }, { label: 'Latitude', key: 'latitude' }, { label: 'Longitude', key: 'longitude' }, { label: 'Timezone', key: 'timezone' }]" :key="field.key">
                                <div class="detail-label">{{ field.label }}</div>
                                <div class="detail-value">
                                    {{ detailGeo[field.key] ?? '-' }}
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">ORIGEM DA URL</div>
                        <div class="section-card__body section-body--stack">
                        <div>
                            <div class="detail-label">Página (sem parâmetros)</div>
                            <div class="detail-value tw-break-all">
                                {{ detailCleanUrl }}
                            </div>
                        </div>
                        <div>
                            <div class="detail-label">Parâmetros</div>
                            <div class="tw-mt-2">
                                <q-list v-if="hasUrlParams" class="rounded-borders">
                                    <q-item v-for="([key, value]) in detailUrlParams" :key="`${key}-${value}`">
                                        <q-item-section>
                                            <div class="tw-text-xs tw-uppercase tw-text-slate-500">{{ key }}</div>
                                            <div class="tw-text-sm tw-font-medium tw-break-all">{{ formatParamValue(value) }}</div>
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                                <div v-else class="tw-text-sm tw-text-slate-500">Sem parâmetros na URL.</div>
                            </div>
                        </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">REDE &amp; SEGURANÇA</div>
                        <div class="section-card__body">
                        <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                            <div>
                                <div class="detail-label">ISP</div>
                                <div class="detail-value">
                                    {{ detailNetwork.isp || '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Organização</div>
                                <div class="detail-value">
                                    {{ detailNetwork.organization || '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Categoria IP (Lookup)</div>
                                <div class="tw-mt-1">
                                    <div
                                        class="ip-category-label"
                                        :style="{ color: detailNetworkCategoryColor }"
                                    >
                                        {{ detailNetworkCategoryName }}
                                    </div>
                                    <div class="ip-category-description">
                                        {{ detailNetworkCategoryDescription }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Fraud Score</div>
                                <div class="detail-value">
                                    {{ detailNetwork.fraud_score ?? '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Última verificação</div>
                                <div class="detail-value">
                                    {{ detailNetwork.last_checked ? formatDateBR(detailNetwork.last_checked) : '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-mt-4">
                            <div v-for="flag in [{ key: 'is_proxy', label: 'Proxy' }, { key: 'is_vpn', label: 'VPN' }, { key: 'is_tor', label: 'Tor' }, { key: 'is_datacenter', label: 'Datacenter' }, { key: 'is_bot', label: 'Bot' }]" :key="flag.key">
                                <div class="detail-label">{{ flag.label }}</div>
                                <div class="detail-value">
                                    {{ formatFlag(detailNetworkFlags[flag.key]) }}
                                </div>
                            </div>
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

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.section-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    overflow: hidden;
}

.section-card__header {
    background: #f8fafc;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #475569;
}

.section-card__body {
    padding: 1rem;
}

.section-body--stack > * + * {
    margin-top: 1rem;
}

.detail-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.detail-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #0f172a;
}

.ip-category-label {
    font-size: 0.95rem;
    font-weight: 600;
}

.ip-category-description {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.15rem;
}
</style>
