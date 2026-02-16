<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { Notify } from 'quasar'

import StatusChip from '@/Components/StatusChip.vue'
import { qTableLangPt } from '@/lang/qtable-pt'

const props = defineProps({
    campaigns: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const rows = computed(() => props.campaigns?.data ?? [])
const search = ref(props.filters?.search ?? '')

const pagination = ref({
    page: props.campaigns?.current_page ?? 1,
    rowsPerPage: props.campaigns?.per_page ?? 15,
    rowsNumber: props.campaigns?.total ?? 0,
    sortBy: props.filters?.sort ?? 'created_at',
    descending: (props.filters?.direction ?? 'desc') === 'desc',
})

watch(
    () => props.campaigns,
    (val) => {
        if (!val) {
            return
        }
        pagination.value.page = val.current_page
        pagination.value.rowsPerPage = val.per_page
        pagination.value.rowsNumber = val.total
    }
)

watch(
    () => props.filters,
    (val) => {
        pagination.value.sortBy = val?.sort ?? 'created_at'
        pagination.value.descending = (val?.direction ?? 'desc') === 'desc'
        if ((val?.search ?? '') !== search.value) {
            search.value = val?.search ?? ''
        }
    }
)

const columns = [
    {
        name: 'code',
        label: 'Código',
        field: 'code',
        align: 'left',
        sortable: true,
    },
    {
        name: 'name',
        label: 'Nome',
        field: 'name',
        align: 'left',
        sortable: true,
    },
    {
        name: 'countries',
        label: 'Países',
        field: 'countries',
        align: 'left',
    },
    {
        name: 'channel',
        label: 'Canal',
        field: row => row.channel?.name ?? '-',
        align: 'left',
    },
    {
        name: 'conversion_goal',
        label: 'Meta de conversao',
        field: row => row.conversion_goal?.goal_code ?? '-',
        align: 'left',
    },
    {
        name: 'status',
        label: 'Status',
        field: 'status',
        align: 'center',
        sortable: true,
    },
    {
        name: 'tracking_snippet',
        label: 'Acompanhamento',
        field: 'code',
        align: 'left',
    },
    {
        name: 'actions',
        label: 'Ações',
        field: 'actions',
        align: 'right',
    },
]

const showTrackingDialog = ref(false)
const trackingLoading = ref(false)
const trackingScript = ref('')
const trackingTextarea = ref(null)
const showCountriesDialog = ref(false)
const countriesLoading = ref(false)
const countriesDialogTitle = ref('')
const countriesDialogCount = ref(0)
const countriesDialogItems = ref([])

function fetchTable() {
    const params = {
        page: pagination.value.page,
        per_page: pagination.value.rowsPerPage,
        sort: pagination.value.sortBy,
        direction: pagination.value.descending ? 'desc' : 'asc',
        search: search.value || undefined,
    }

    router.get(route('panel.campaigns.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function onRequest({ pagination: newPagination }) {
    Object.assign(pagination.value, newPagination)
    fetchTable()
}

function onSearch() {
    pagination.value.page = 1
    fetchTable()
}

function onClearSearch() {
    if (search.value === '') {
        return
    }
    search.value = ''
    pagination.value.page = 1
    fetchTable()
}

function campaignRouteKey(campaign) {
    return campaign?.hashid ?? campaign?.id
}

function editCampaign(campaign) {
    router.visit(route('panel.campaigns.edit', campaignRouteKey(campaign)))
}

function destroyCampaign(campaign) {
    if (!confirm(`Deseja realmente remover a campanha "${campaign.name}"?`)) {
        return
    }

    router.delete(route('panel.campaigns.destroy', campaignRouteKey(campaign)))
}

async function openTrackingDialog(campaign) {
    const routeKey = campaignRouteKey(campaign)
    if (!routeKey) return

    showTrackingDialog.value = true
    trackingLoading.value = true
    trackingScript.value = ''

    try {
        const response = await axios.get(route('panel.campaigns.tracking_code', routeKey))
        trackingScript.value = String(response.data.script || '').trim()
    } catch {
        Notify.create({
            type: 'negative',
            message: 'Não foi possível carregar o código de acompanhamento',
            position: 'top-right',
        })
    } finally {
        trackingLoading.value = false
    }
}

async function openCountriesDialog(campaign) {
    const routeKey = campaignRouteKey(campaign)
    if (!routeKey) return

    showCountriesDialog.value = true
    countriesLoading.value = true
    countriesDialogTitle.value = campaign?.name ?? 'Campanha'
    countriesDialogCount.value = Number(campaign?.countries_count ?? 0)
    countriesDialogItems.value = []

    try {
        const response = await axios.get(route('panel.campaigns.countries', routeKey))
        countriesDialogTitle.value = String(response.data?.campaign?.name ?? countriesDialogTitle.value)
        countriesDialogCount.value = Number(response.data?.campaign?.countries_count ?? 0)
        countriesDialogItems.value = Array.isArray(response.data?.countries) ? response.data.countries : []
    } catch {
        Notify.create({
            type: 'negative',
            message: 'Não foi possível carregar a lista de países',
            position: 'top-right',
        })
    } finally {
        countriesLoading.value = false
    }
}

function copyTrackingScript() {
    if (!trackingTextarea.value) return

    trackingTextarea.value.focus()
    trackingTextarea.value.select()

    try {
        const ok = document.execCommand('copy')
        if (!ok) throw new Error()

        Notify.create({
            type: 'positive',
            message: 'Código copiado',
            timeout: 2000,
            position: 'top-right',
        })
    } catch {
        Notify.create({
            type: 'negative',
            message: 'Não foi possível copiar o código',
            timeout: 3000,
            position: 'top-right',
        })
    }
}

</script>

<template>
    <Head title="Campanhas" />

    <div class="tw-space-y-3">
        <!-- Header / ações -->
        <div class="tw-flex tw-items-center tw-justify-between tw-flex-col sm:tw-flex-row tw-gap-3">
            <h1 class="tw-text-lg tw-font-semibold">
                Campanhas
            </h1>

            <Link :href="route('panel.campaigns.create')">
                <q-btn
                    color="positive"
                    icon="add"
                    label="Nova Campanha"
                    unelevated
                />
            </Link>
        </div>

        <!-- Tabela -->
        <q-card flat bordered>
            <q-card-section class="tw-space-y-3">
                <div class="tw-flex tw-gap-3 tw-flex-col md:tw-flex-row">
                    <q-input
                        v-model="search"
                        dense
                        outlined
                        clearable
                        placeholder="Pesquisar por código ou nome"
                        class="md:tw-flex-1"
                        @keyup.enter="onSearch"
                        @clear="onClearSearch"
                    >
                        <template #append>
                            <q-icon name="search" class="cursor-pointer" @click="onSearch" />
                        </template>
                    </q-input>

                    <q-btn
                        outline
                        color="primary"
                        icon="search"
                        label="Filtrar"
                        class="md:tw-w-auto"
                        @click="onSearch"
                    />
                </div>

                <q-table
                    flat
                    :rows="rows"
                    :columns="columns"
                    row-key="id"
                    binary-state-sort
                    v-model:pagination="pagination"
                    :pagination="pagination"
                    :rows-per-page-options="[10, 15, 25, 50]"
                    :no-data-label="qTableLangPt.noData"
                    :no-results-label="qTableLangPt.noResults"
                    :loading-label="qTableLangPt.loading"
                    :rows-per-page-label="qTableLangPt.recordsPerPage"
                    :all-rows-label="qTableLangPt.allRows"
                    :pagination-label="qTableLangPt.pagination"
                    @request="onRequest"
                >
                    <!-- Países -->
                    <template #body-cell-countries="props">
                        <q-td :props="props">
                            <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                <span v-if="Number(props.row.countries_count ?? 0) === 0" class="tw-text-sm tw-text-gray-600">
                                    0 país(es)
                                </span>
                                <span v-else>{{ (props.row.countries_preview ?? []).join(', ') }}</span>
                                <q-btn
                                    v-if="Number(props.row.countries_hidden_count ?? 0) > 0"
                                    flat
                                    dense
                                    no-caps
                                    color="primary"
                                    :label="`(+${props.row.countries_hidden_count ?? 0})`"
                                    @click="openCountriesDialog(props.row)"
                                />
                            </div>
                        </q-td>
                    </template>

                    <!-- Status -->
                    <template #body-cell-status="props">
                        <q-td :props="props" class="tw-text-center">
                            <StatusChip :value="props.row.status" />
                        </q-td>
                    </template>

                    <template #body-cell-tracking_snippet="props">
                        <q-td :props="props">
                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="code"
                                color="primary"
                                label="Snippet"
                                @click="openTrackingDialog(props.row)"
                            />
                        </q-td>
                    </template>

                    <!-- Ações -->
                    <template #body-cell-actions="props">
                        <q-td :props="props" class="tw-text-right">
                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="edit"
                                color="primary"
                                @click="editCampaign(props.row)"
                            />

                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="delete"
                                color="negative"
                                @click="destroyCampaign(props.row)"
                            />
                        </q-td>
                    </template>
                </q-table>
            </q-card-section>
        </q-card>
    </div>

    <q-dialog v-model="showTrackingDialog">
        <q-card style="min-width: 720px; max-width: 95vw; min-height: 450px;">
            <q-card-section class="tw-flex tw-justify-between tw-items-center">
                <div class="tw-text-lg tw-font-semibold">
                    Código de acompanhamento
                </div>
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div v-if="trackingLoading" class="tw-text-center tw-py-6">
                    Carregando...
                </div>

                <textarea
                    ref="trackingTextarea"
                    class="tw-w-full tw-h-72 tw-font-mono tw-text-sm tw-p-3 tw-bg-gray-100 tw-rounded"
                    readonly
                >{{ trackingScript }}</textarea>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <q-btn
                    flat
                    icon="content_copy"
                    label="Copiar"
                    :disable="!trackingScript"
                    @click="copyTrackingScript"
                />
                <q-btn color="primary" label="Fechar" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-dialog v-model="showCountriesDialog">
        <q-card style="min-width: 620px; max-width: 95vw; max-height: 85vh;">
            <q-card-section class="tw-flex tw-items-center tw-justify-between">
                <div>
                    <div class="tw-text-lg tw-font-semibold">
                        Países da campanha
                    </div>
                    <div class="tw-text-sm tw-text-gray-600">
                        {{ countriesDialogTitle }} · {{ countriesDialogCount }} país(es)
                    </div>
                </div>
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div v-if="countriesLoading" class="tw-text-center tw-py-6">
                    Carregando...
                </div>

                <div v-else-if="!countriesDialogItems.length" class="tw-text-sm tw-text-gray-600">
                    Nenhum país encontrado para esta campanha.
                </div>

                <div v-else class="tw-flex tw-flex-wrap tw-gap-2 tw-max-h-96 tw-overflow-y-auto">
                    <q-chip
                        v-for="country in countriesDialogItems"
                        :key="country.id"
                        dense
                        square
                    >
                        {{ country.name }} ({{ country.iso2 }})
                    </q-chip>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <q-btn color="primary" label="Fechar" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
