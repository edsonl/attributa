<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { useQuasar } from 'quasar'

import MetricCard from '@/Components/TrackingMaintenance/MetricCard.vue'
import RedisKeysTable from '@/Components/TrackingMaintenance/RedisKeysTable.vue'
import RedisPayloadDialog from '@/Components/TrackingMaintenance/RedisPayloadDialog.vue'

const $q = useQuasar()

const summaryLoading = ref(false)
const scriptLoading = ref(false)
const campaignsLoading = ref(false)
const pageviewsLoading = ref(false)

const summary = ref({
    counts: {
        all: 0,
        campaigns: 0,
        pageviews: 0,
        last_collects: 0,
        hit_gates: 0,
        script_templates: 0,
    },
    memory: {
        total_memory_label: '0 B',
        total_payload_label: '0 B',
        memory_command_available: true,
    },
    prefix: '',
    connection: '',
})

const scriptItem = ref({
    exists: false,
    key: '',
    ttl_label: '-',
    memory_label: '-',
    payload_size_label: '-',
    content: '',
    content_preview: '',
})

const campaignRows = ref([])
const campaignSelected = ref([])
const campaignSearch = ref('')
const campaignPagination = ref({
    page: 1,
    rowsPerPage: 10,
    rowsNumber: 0,
    sortBy: 'campaign_id',
    descending: false,
})

const pageviewRows = ref([])
const pageviewSelected = ref([])
const pageviewSearch = ref('')
const pageviewPagination = ref({
    page: 1,
    rowsPerPage: 10,
    rowsNumber: 0,
    sortBy: 'pageview_id',
    descending: true,
})

const payloadDialog = ref(false)
const payloadTitle = ref('')
const payloadSubtitle = ref('')
const payloadData = ref(null)
const payloadRawText = ref('')
const activeTab = ref('overview')

let campaignSearchTimer = null
let pageviewSearchTimer = null

const summaryCards = computed(() => ([
    {
        title: 'Chaves tracking',
        value: summary.value.counts.all,
        caption: `Prefixo ${summary.value.prefix || '-'}`,
        icon: 'dns',
        color: 'primary',
    },
    {
        title: 'Campanhas em cache',
        value: summary.value.counts.campaigns,
        caption: `Chaves ${summary.value.prefix || 'tc'}:campaign:*`,
        icon: 'campaign',
        color: 'secondary',
    },
    {
        title: 'Pageviews em cache',
        value: summary.value.counts.pageviews,
        caption: `Chaves ${summary.value.prefix || 'tc'}:pv:*`,
        icon: 'visibility',
        color: 'positive',
    },
    {
        title: 'Bridges last',
        value: summary.value.counts.last_collects,
        caption: `Chaves ${summary.value.prefix || 'tc'}:last:*`,
        icon: 'link',
        color: 'warning',
    },
    {
        title: 'Hit gate',
        value: summary.value.counts.hit_gates,
        caption: `Chaves ${summary.value.prefix || 'tc'}:hit_gate:*`,
        icon: 'hourglass_top',
        color: 'deep-orange',
    },
    {
        title: 'Script em cache',
        value: summary.value.counts.script_templates,
        caption: `Conexão ${summary.value.connection || '-'}`,
        icon: 'code',
        color: 'indigo',
    },
]))

const campaignColumns = [
    { name: 'campaign_id', label: 'ID', field: 'campaign_id', align: 'left', sortable: true },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', align: 'left', sortable: true },
    { name: 'campaign_code', label: 'Código', field: 'campaign_code', align: 'left', sortable: true },
    { name: 'allowed_origin', label: 'Origem', field: 'allowed_origin', align: 'left', sortable: true },
    { name: 'ttl_seconds', label: 'TTL', field: 'ttl_label', align: 'left', sortable: true },
    { name: 'memory_bytes', label: 'Memória', field: 'memory_label', align: 'left', sortable: true },
    { name: 'payload_size_bytes', label: 'Payload', field: 'payload_size_label', align: 'left', sortable: true },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

const pageviewColumns = [
    { name: 'pageview_id', label: 'Pageview ID', field: 'pageview_id', align: 'left', sortable: true },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', align: 'left', sortable: true },
    { name: 'visitor_id', label: 'Visitante', field: 'visitor_id', align: 'left', sortable: true },
    { name: 'occurred_at', label: 'Data', field: 'occurred_at', align: 'left', sortable: true },
    { name: 'page_url', label: 'URL', field: 'page_url', align: 'left' },
    { name: 'ttl_seconds', label: 'TTL', field: 'ttl_label', align: 'left', sortable: true },
    { name: 'memory_bytes', label: 'Memória', field: 'memory_label', align: 'left', sortable: true },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

async function fetchSummary() {
    summaryLoading.value = true

    try {
        const { data } = await axios.get(route('panel.tracking-maintenance.summary'))
        summary.value = data
    } catch {
        notifyError('Não foi possível carregar o resumo do Redis.')
    } finally {
        summaryLoading.value = false
    }
}

async function fetchScript() {
    scriptLoading.value = true

    try {
        const { data } = await axios.get(route('panel.tracking-maintenance.script.show'))
        scriptItem.value = data?.item || scriptItem.value
    } catch {
        notifyError('Não foi possível carregar o cache do script.')
    } finally {
        scriptLoading.value = false
    }
}

async function fetchCampaigns() {
    campaignsLoading.value = true

    try {
        const { data } = await axios.get(route('panel.tracking-maintenance.campaigns.data'), {
            params: {
                page: campaignPagination.value.page,
                per_page: campaignPagination.value.rowsPerPage,
                sortBy: campaignPagination.value.sortBy,
                descending: campaignPagination.value.descending,
                search: campaignSearch.value || undefined,
            },
        })

        campaignRows.value = Array.isArray(data?.data) ? data.data : []
        campaignSelected.value = []
        campaignPagination.value = {
            ...campaignPagination.value,
            page: data?.current_page || 1,
            rowsPerPage: data?.per_page || 10,
            rowsNumber: data?.total || 0,
        }
    } catch {
        notifyError('Não foi possível carregar os caches de campanha.')
    } finally {
        campaignsLoading.value = false
    }
}

async function fetchPageviews() {
    pageviewsLoading.value = true

    try {
        const { data } = await axios.get(route('panel.tracking-maintenance.pageviews.data'), {
            params: {
                page: pageviewPagination.value.page,
                per_page: pageviewPagination.value.rowsPerPage,
                sortBy: pageviewPagination.value.sortBy,
                descending: pageviewPagination.value.descending,
                search: pageviewSearch.value || undefined,
            },
        })

        pageviewRows.value = Array.isArray(data?.data) ? data.data : []
        pageviewSelected.value = []
        pageviewPagination.value = {
            ...pageviewPagination.value,
            page: data?.current_page || 1,
            rowsPerPage: data?.per_page || 10,
            rowsNumber: data?.total || 0,
        }
    } catch {
        notifyError('Não foi possível carregar os caches de pageview.')
    } finally {
        pageviewsLoading.value = false
    }
}

function updateCampaignPagination(value) {
    campaignPagination.value = value
}

function updateCampaignSelected(value) {
    campaignSelected.value = value
}

function updateCampaignSearch(value) {
    campaignSearch.value = value
}

function updatePageviewPagination(value) {
    pageviewPagination.value = value
}

function updatePageviewSelected(value) {
    pageviewSelected.value = value
}

function updatePageviewSearch(value) {
    pageviewSearch.value = value
}

function onCampaignRequest({ pagination }) {
    campaignPagination.value = { ...campaignPagination.value, ...pagination }
    fetchCampaigns()
}

function onPageviewRequest({ pagination }) {
    pageviewPagination.value = { ...pageviewPagination.value, ...pagination }
    fetchPageviews()
}

function openPayload(title, subtitle, payload, rawText = '') {
    payloadTitle.value = title
    payloadSubtitle.value = subtitle
    payloadData.value = payload
    payloadRawText.value = rawText
    payloadDialog.value = true
}

function viewCampaignPayload(row) {
    openPayload(
        `Campanha em cache #${row.campaign_id || '-'}`,
        row.key,
        row.payload
    )
}

function viewPageviewPayload(row) {
    openPayload(
        `Pageview em cache #${row.pageview_id || '-'}`,
        row.key,
        row.payload
    )
}

function viewScriptContent() {
    openPayload(
        'Template script.js em cache',
        scriptItem.value.key,
        null,
        String(scriptItem.value.content || '')
    )
}

async function removeScript() {
    const confirmed = await confirmAction(
        'Remover cache do script',
        'Deseja remover o template do script.js salvo no Redis?'
    )

    if (!confirmed) {
        return
    }

    scriptLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.script.destroy'))
        notifySuccess(data?.message || 'Cache do script removido com sucesso.')
        await refreshAll()
    } catch {
        notifyError('Falha ao remover o cache do script.')
    } finally {
        scriptLoading.value = false
    }
}

async function removeCampaign(row) {
    const confirmed = await confirmAction(
        'Remover cache da campanha',
        `Deseja remover a chave Redis da campanha "${row.campaign_name || row.campaign_code || ''}"?`
    )

    if (!confirmed) {
        return
    }

    campaignsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.campaigns.destroy', row.cache_id))
        notifySuccess(data?.message || 'Cache da campanha removido com sucesso.')
        await Promise.all([fetchCampaigns(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover o cache da campanha.')
    } finally {
        campaignsLoading.value = false
    }
}

async function removeSelectedCampaigns() {
    if (!campaignSelected.value.length) {
        return
    }

    const confirmed = await confirmAction(
        'Remover campanhas selecionadas',
        `Deseja remover ${campaignSelected.value.length} chave(s) de campanha do Redis?`
    )

    if (!confirmed) {
        return
    }

    campaignsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.campaigns.bulk-destroy'), {
            data: {
                mode: 'selected',
                cache_ids: campaignSelected.value.map(item => item.cache_id),
            },
        })
        notifySuccess(data?.message || 'Caches de campanha removidos com sucesso.')
        await Promise.all([fetchCampaigns(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover as campanhas selecionadas.')
    } finally {
        campaignsLoading.value = false
    }
}

async function removeAllCampaigns() {
    const confirmed = await confirmAction(
        'Remover todos os caches de campanha',
        'Deseja remover todas as chaves de campanha do namespace de tracking?'
    )

    if (!confirmed) {
        return
    }

    campaignsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.campaigns.bulk-destroy'), {
            data: { mode: 'all' },
        })
        notifySuccess(data?.message || 'Caches de campanha removidos com sucesso.')
        await Promise.all([fetchCampaigns(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover todos os caches de campanha.')
    } finally {
        campaignsLoading.value = false
    }
}

async function removePageview(row) {
    const confirmed = await confirmAction(
        'Remover cache da pageview',
        `Deseja remover a pageview ${row.pageview_id || ''} e as chaves relacionadas no Redis?`
    )

    if (!confirmed) {
        return
    }

    pageviewsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.pageviews.destroy', row.cache_id))
        notifySuccess(data?.message || 'Cache da pageview removido com sucesso.')
        await Promise.all([fetchPageviews(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover o cache da pageview.')
    } finally {
        pageviewsLoading.value = false
    }
}

async function removeSelectedPageviews() {
    if (!pageviewSelected.value.length) {
        return
    }

    const confirmed = await confirmAction(
        'Remover pageviews selecionadas',
        `Deseja remover ${pageviewSelected.value.length} pageview(s) e suas chaves relacionadas no Redis?`
    )

    if (!confirmed) {
        return
    }

    pageviewsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.pageviews.bulk-destroy'), {
            data: {
                mode: 'selected',
                cache_ids: pageviewSelected.value.map(item => item.cache_id),
            },
        })
        notifySuccess(data?.message || 'Caches de pageview removidos com sucesso.')
        await Promise.all([fetchPageviews(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover as pageviews selecionadas.')
    } finally {
        pageviewsLoading.value = false
    }
}

async function removeAllPageviews() {
    const confirmed = await confirmAction(
        'Remover todas as pageviews',
        'Deseja remover todas as chaves de pageview, last e hit_gate do namespace de tracking?'
    )

    if (!confirmed) {
        return
    }

    pageviewsLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.pageviews.bulk-destroy'), {
            data: { mode: 'all' },
        })
        notifySuccess(data?.message || 'Caches de pageview removidos com sucesso.')
        await Promise.all([fetchPageviews(), fetchSummary()])
    } catch {
        notifyError('Falha ao remover todas as pageviews.')
    } finally {
        pageviewsLoading.value = false
    }
}

async function flushAllTrackingKeys() {
    const confirmed = await confirmAction(
        'Limpar namespace de tracking',
        'Deseja remover todas as chaves do tracking no Redis? Esta ação afeta script, campanhas, pageviews e demais caches.'
    )

    if (!confirmed) {
        return
    }

    summaryLoading.value = true

    try {
        const { data } = await axios.delete(route('panel.tracking-maintenance.flush-all'))
        notifySuccess(data?.message || 'Todas as chaves de tracking foram removidas com sucesso.')
        await refreshAll()
    } catch {
        notifyError('Falha ao limpar todas as chaves de tracking.')
    } finally {
        summaryLoading.value = false
    }
}

async function refreshAll() {
    await Promise.all([
        fetchSummary(),
        fetchScript(),
        fetchCampaigns(),
        fetchPageviews(),
    ])
}

async function confirmAction(title, message) {
    if (typeof window !== 'undefined' && window.$confirm) {
        return window.$confirm({
            title,
            message,
            okLabel: 'Remover',
            okColor: 'negative',
        })
    }

    if (typeof window !== 'undefined') {
        return window.confirm(message)
    }

    return false
}

function notifySuccess(message) {
    $q.notify({ type: 'positive', message })
}

function notifyError(message) {
    $q.notify({ type: 'negative', message })
}

watch(campaignSearch, () => {
    campaignPagination.value.page = 1
    clearTimeout(campaignSearchTimer)
    campaignSearchTimer = window.setTimeout(() => {
        fetchCampaigns()
    }, 250)
})

watch(pageviewSearch, () => {
    pageviewPagination.value.page = 1
    clearTimeout(pageviewSearchTimer)
    pageviewSearchTimer = window.setTimeout(() => {
        fetchPageviews()
    }, 250)
})

onMounted(() => {
    refreshAll()
})
</script>

<template>
    <Head title="Tracking e Manutenção" />

    <div class="tw-space-y-6">
        <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-3">
            <div>
                <h1 class="tw-text-xl tw-font-semibold tw-text-slate-900">
                    Tracking e Manutenção
                </h1>
                <p class="tw-text-sm tw-text-slate-500">
                    Visualização e limpeza das chaves Redis usadas pelo tracking. Os dados da tela são carregados por JSON no backend.
                </p>
            </div>
        </div>

        <q-card flat bordered class="tabs-card">
            <q-tabs
                v-model="activeTab"
                align="left"
                inline-label
                mobile-arrows
                indicator-color="primary"
                active-color="primary"
                class="tracking-tabs"
            >
                <q-tab name="overview" icon="dashboard" label="Resumo" />
                <q-tab name="script" icon="code" label="script.js" />
                <q-tab name="campaigns" icon="campaign" label="Campanhas" />
                <q-tab name="pageviews" icon="visibility" label="Pageviews" />
                <q-tab name="memory" icon="memory" label="Memória" />
                <q-tab name="cleanup" icon="warning" label="Limpeza geral" />
            </q-tabs>

            <q-separator />

            <q-tab-panels v-model="activeTab" animated class="tracking-panels">
                <q-tab-panel name="overview" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6">
                        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-3 tw-gap-4">
                            <MetricCard
                                v-for="card in summaryCards"
                                :key="card.title"
                                :title="card.title"
                                :value="card.value"
                                :caption="card.caption"
                                :icon="card.icon"
                                :color="card.color"
                            />
                        </div>
                    </div>
                </q-tab-panel>

                <q-tab-panel name="script" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6">
                        <q-card flat bordered class="script-card">
                            <q-card-section class="tw-space-y-4">
                                <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-3">
                                    <div>
                                        <div class="tw-text-lg tw-font-semibold">Template `script.js`</div>
                                        <div class="tw-text-sm tw-text-slate-500">
                                            Chave fixa de cache do snippet minificado.
                                        </div>
                                    </div>

                                    <div class="tw-flex tw-gap-2">
                                        <q-btn
                                            outline
                                            color="primary"
                                            icon="visibility"
                                            label="Visualizar"
                                            :disable="!scriptItem.exists"
                                            @click="viewScriptContent"
                                        />
                                        <q-btn
                                            color="negative"
                                            icon="delete"
                                            label="Remover"
                                            :disable="!scriptItem.exists"
                                            :loading="scriptLoading"
                                            @click="removeScript"
                                        />
                                    </div>
                                </div>

                                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-3">
                                    <MetricCard title="Status" :value="scriptItem.exists ? 'Em cache' : 'Ausente'" icon="check_circle" color="positive" />
                                    <MetricCard title="TTL" :value="scriptItem.ttl_label || '-'" icon="schedule" color="primary" />
                                    <MetricCard title="Memória" :value="scriptItem.memory_label || '-'" icon="memory" color="indigo" />
                                    <MetricCard title="Payload" :value="scriptItem.payload_size_label || '-'" icon="data_object" color="secondary" />
                                </div>

                                <q-banner rounded class="tw-bg-slate-50 tw-text-slate-700">
                                    <div class="tw-text-xs tw-uppercase tw-tracking-wide tw-text-slate-500">Chave Redis</div>
                                    <div class="tw-mt-1 tw-font-mono tw-text-sm tw-break-all">
                                        {{ scriptItem.key || '-' }}
                                    </div>
                                </q-banner>

                                <q-input
                                    :model-value="scriptItem.content_preview || ''"
                                    type="textarea"
                                    autogrow
                                    readonly
                                    outlined
                                    label="Prévia"
                                    class="script-preview"
                                />
                            </q-card-section>
                        </q-card>
                    </div>
                </q-tab-panel>

                <q-tab-panel name="campaigns" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6">
                        <RedisKeysTable
                            title="Campanhas"
                            description="Lista de chaves `campaign` armazenadas no Redis do tracking."
                            :rows="campaignRows"
                            :columns="campaignColumns"
                            :loading="campaignsLoading"
                            :pagination="campaignPagination"
                            :selected="campaignSelected"
                            :search="campaignSearch"
                            empty-label="Nenhum cache de campanha encontrado."
                            @update:pagination="updateCampaignPagination"
                            @update:selected="updateCampaignSelected"
                            @update:search="updateCampaignSearch"
                            @request="onCampaignRequest"
                            @view="viewCampaignPayload"
                            @delete="removeCampaign"
                            @delete-selected="removeSelectedCampaigns"
                            @delete-all="removeAllCampaigns"
                        >
                            <template #body-cell-campaign_name="slotProps">
                                <q-td :props="slotProps">
                                    <div class="tw-font-medium">{{ slotProps.row.campaign_name || '-' }}</div>
                                    <div class="tw-text-xs tw-text-slate-500 tw-font-mono">{{ slotProps.row.key }}</div>
                                </q-td>
                            </template>
                        </RedisKeysTable>
                    </div>
                </q-tab-panel>

                <q-tab-panel name="pageviews" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6">
                        <RedisKeysTable
                            title="Pageviews"
                            description="Tabela paginada com as chaves `pv` e remoção das chaves relacionadas (`last` e `hit_gate`)."
                            :rows="pageviewRows"
                            :columns="pageviewColumns"
                            :loading="pageviewsLoading"
                            :pagination="pageviewPagination"
                            :selected="pageviewSelected"
                            :search="pageviewSearch"
                            empty-label="Nenhum cache de pageview encontrado."
                            @update:pagination="updatePageviewPagination"
                            @update:selected="updatePageviewSelected"
                            @update:search="updatePageviewSearch"
                            @request="onPageviewRequest"
                            @view="viewPageviewPayload"
                            @delete="removePageview"
                            @delete-selected="removeSelectedPageviews"
                            @delete-all="removeAllPageviews"
                        >
                            <template #body-cell-page_url="slotProps">
                                <q-td :props="slotProps">
                                    <div class="page-url-cell">{{ slotProps.row.page_url || '-' }}</div>
                                </q-td>
                            </template>

                            <template #body-cell-campaign_name="slotProps">
                                <q-td :props="slotProps">
                                    <div class="tw-font-medium">{{ slotProps.row.campaign_name || '-' }}</div>
                                    <div class="tw-text-xs tw-text-slate-500">
                                        {{ slotProps.row.occurred_at || '-' }}
                                    </div>
                                </q-td>
                            </template>
                        </RedisKeysTable>
                    </div>
                </q-tab-panel>

                <q-tab-panel name="memory" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6 tw-space-y-3">
                        <div>
                            <div class="tw-text-lg tw-font-semibold">Uso de memória do namespace</div>
                            <div class="tw-text-sm tw-text-slate-500">
                                O valor de memória real depende do comando `MEMORY USAGE` do Redis. Quando indisponível, a tela mostra apenas o tamanho dos payloads salvos.
                            </div>
                        </div>

                        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                            <MetricCard
                                title="Memória total"
                                :value="summary.memory.total_memory_label"
                                :caption="summary.memory.memory_command_available ? 'Soma estimada via MEMORY USAGE.' : 'Comando MEMORY USAGE indisponível nesta instância.'"
                                icon="memory"
                                color="deep-purple"
                            />
                            <MetricCard
                                title="Payload total"
                                :value="summary.memory.total_payload_label"
                                caption="Soma do tamanho bruto dos valores lidos no Redis."
                                icon="storage"
                                color="teal"
                            />
                        </div>
                    </div>
                </q-tab-panel>

                <q-tab-panel name="cleanup" class="tw-p-0">
                    <div class="tw-p-4 md:tw-p-6">
                        <q-card flat bordered class="cleanup-card">
                            <q-card-section class="tw-space-y-4">
                                <div>
                                    <div class="tw-text-lg tw-font-semibold">Limpeza geral do tracking</div>
                                    <div class="tw-text-sm tw-text-slate-500">
                                        Remove todas as chaves do namespace de tracking no Redis, incluindo script, campanhas, pageviews e caches auxiliares.
                                    </div>
                                </div>

                                <q-banner rounded class="tw-bg-red-50 tw-text-red-900">
                                    Esta ação é global para o namespace configurado e deve ser usada apenas quando você quiser zerar o cache de tracking.
                                </q-banner>

                                <div class="tw-flex tw-justify-start">
                                    <q-btn
                                        color="negative"
                                        icon="delete_forever"
                                        label="Limpar todo o tracking"
                                        :loading="summaryLoading"
                                        class="cleanup-button"
                                        @click="flushAllTrackingKeys"
                                    />
                                </div>
                            </q-card-section>
                        </q-card>
                    </div>
                </q-tab-panel>
            </q-tab-panels>
        </q-card>
    </div>

    <RedisPayloadDialog
        v-model="payloadDialog"
        :title="payloadTitle"
        :subtitle="payloadSubtitle"
        :payload="payloadData"
        :raw-text="payloadRawText"
    />
</template>

<style scoped>
.script-card {
    border-radius: 20px;
}

.tabs-card {
    border-radius: 22px;
    overflow: hidden;
}

.tracking-tabs {
    padding: 0 12px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}

.tracking-panels {
    background: #ffffff;
}

.cleanup-card {
    max-width: 720px;
    border-radius: 18px;
}

.cleanup-button {
    min-width: 240px;
}

.script-preview :deep(textarea) {
    min-height: 140px;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
}

.page-url-cell {
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
