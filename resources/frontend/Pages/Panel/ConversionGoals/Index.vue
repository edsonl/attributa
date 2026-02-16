<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { copyToClipboard, useQuasar } from 'quasar'
import { qTableLangPt } from '@/lang/qtable-pt'

const props = defineProps({
    conversionGoals: {
        type: Object,
        required: true,
    },
    logsRetentionDays: {
        type: Number,
        default: 10,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const rows = computed(() => props.conversionGoals?.data ?? [])
const search = ref(props.filters?.search ?? '')
const $q = useQuasar()
const integrationDialog = ref(false)
const integrationPayload = ref(null)
const logsDialog = ref(false)
const logsLoading = ref(false)
const logsGoalCode = ref('')
const logsRows = ref([])
const logsPagination = ref({
    page: 1,
    rowsPerPage: 15,
    rowsNumber: 0,
    sortBy: 'created_at',
    descending: true,
})
const logsGoalKey = ref(null)
const logsColumns = [
    { name: 'created_at', label: 'Data', field: 'created_at_formatted', align: 'left', sortable: true },
    { name: 'message', label: 'Mensagem', field: 'message', align: 'left', sortable: false },
]

const pagination = ref({
    page: props.conversionGoals?.current_page ?? 1,
    rowsPerPage: props.conversionGoals?.per_page ?? 15,
    rowsNumber: props.conversionGoals?.total ?? 0,
    sortBy: props.filters?.sort ?? 'created_at',
    descending: (props.filters?.direction ?? 'desc') === 'desc',
})

watch(
    () => props.conversionGoals,
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
        name: 'id',
        label: 'ID',
        field: 'id',
        align: 'left',
        sortable: true,
    },
    {
        name: 'goal_code',
        label: 'Codigo',
        field: 'goal_code',
        align: 'left',
        sortable: true,
    },
    {
        name: 'active',
        label: 'Status',
        field: 'active',
        align: 'center',
        sortable: true,
    },
    {
        name: 'timezone',
        label: 'Timezone',
        field: row => row.timezone?.label ?? row.timezone?.identifier ?? '-',
        align: 'left',
    },
    {
        name: 'campaigns',
        label: 'Campanhas',
        field: 'campaigns',
        align: 'left',
    },
    {
        name: 'created_at',
        label: 'Criado em',
        field: 'created_at',
        align: 'left',
        sortable: true,
    },
    {
        name: 'integration',
        label: 'Integracao',
        field: 'integration',
        align: 'center',
    },
    {
        name: 'logs',
        label: 'Logs',
        field: 'logs',
        align: 'center',
    },
    {
        name: 'actions',
        label: 'Acoes',
        field: 'actions',
        align: 'right',
    },
]

function fetchTable() {
    const params = {
        page: pagination.value.page,
        per_page: pagination.value.rowsPerPage,
        sort: pagination.value.sortBy,
        direction: pagination.value.descending ? 'desc' : 'asc',
        search: search.value || undefined,
    }

    router.get(route('panel.conversion-goals.index'), params, {
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

function goalRouteKey(goal) {
    return goal?.hashid ?? goal?.id
}

function editItem(goal) {
    router.visit(route('panel.conversion-goals.edit', goalRouteKey(goal)))
}

function campaignsLabel(row) {
    const names = (row.campaigns ?? [])
        .map((campaign) => campaign?.name)
        .filter(Boolean)

    if (!names.length) {
        return '-'
    }

    const joined = names.join(', ')
    if (joined.length <= 60) {
        return joined
    }

    return `${joined.slice(0, 60).trimEnd()}...`
}

function destroyItem(item) {
    $q.dialog({
        title: 'Confirmar exclusao',
        message: `Deseja realmente remover a meta "${item.goal_code}"?`,
        cancel: {
            label: 'Cancelar',
            flat: true,
        },
        ok: {
            label: 'Excluir',
            color: 'negative',
            unelevated: true,
        },
        persistent: true,
    }).onOk(() => {
        router.delete(route('panel.conversion-goals.destroy', goalRouteKey(item)))
    })
}

function openIntegration(item) {
    integrationPayload.value = {
        url: item.integration_url,
        username: `googleads-${item.user_slug_id}`,
        password: item.googleads_password,
    }

    integrationDialog.value = true
}

async function copyValue(value, label) {
    const text = String(value ?? '')

    if (text === '') {
        $q.notify({
            type: 'warning',
            message: `${label} vazio.`,
        })
        return
    }
    try {
        await copyToClipboard(text)

        $q.notify({
            type: 'positive',
            message: `${label} copiado.`,
        })
    } catch (error) {
        $q.notify({
            type: 'negative',
            message: `Falha ao copiar ${label.toLowerCase()}.`,
        })
    }
}

function logsCardStyle() {
    if ($q.screen.lt.sm) {
        return 'width: 96vw; max-width: 96vw; height: 92vh; max-height: 92vh;'
    }

    if ($q.screen.lt.md) {
        return 'width: 92vw; max-width: 92vw; height: 90vh; max-height: 90vh;'
    }

    return 'width: 70vw; max-width: 70vw; height: 88vh; max-height: 88vh;'
}

async function fetchLogs(goalId, forcedPagination = null) {
    if (!goalId) {
        return
    }

    logsLoading.value = true
    const p = forcedPagination ?? logsPagination.value

    try {
        const response = await axios.get(route('panel.conversion-goals.logs', goalId), {
            params: {
                page: p.page,
                per_page: p.rowsPerPage,
                sort: p.sortBy || 'created_at',
                direction: p.descending ? 'desc' : 'asc',
            },
        })

        logsRows.value = response.data.data ?? []
        logsPagination.value = {
            ...logsPagination.value,
            page: response.data.current_page,
            rowsPerPage: response.data.per_page,
            rowsNumber: response.data.total,
            sortBy: p.sortBy || 'created_at',
            descending: Boolean(p.descending),
        }
    } finally {
        logsLoading.value = false
    }
}

async function openLogs(goal) {
    logsGoalKey.value = goalRouteKey(goal)
    logsGoalCode.value = goal.goal_code
    logsRows.value = []
    logsPagination.value = {
        ...logsPagination.value,
        page: 1,
        sortBy: 'created_at',
        descending: true,
    }

    logsDialog.value = true
    await fetchLogs(logsGoalKey.value, logsPagination.value)
}

async function onLogsRequest({ pagination: newPagination }) {
    logsPagination.value = {
        ...logsPagination.value,
        ...newPagination,
    }

    await fetchLogs(logsGoalKey.value, logsPagination.value)
}

async function deleteCurrentGoalLogs() {
    if (!logsGoalKey.value) {
        return
    }

    $q.dialog({
        title: 'Excluir logs',
        message: `Deseja excluir todos os logs da meta ${logsGoalCode.value || ''}?`,
        cancel: {
            label: 'Cancelar',
            flat: true,
        },
        ok: {
            label: 'Excluir logs',
            color: 'negative',
            unelevated: true,
        },
        persistent: true,
    }).onOk(async () => {
        logsLoading.value = true
        try {
            await axios.delete(route('panel.conversion-goals.logs.destroy', logsGoalKey.value))
            logsRows.value = []
            logsPagination.value = {
                ...logsPagination.value,
                page: 1,
                rowsNumber: 0,
            }
            $q.notify({
                type: 'positive',
                message: 'Logs removidos com sucesso.',
            })
        } catch (error) {
            $q.notify({
                type: 'negative',
                message: 'Falha ao remover logs.',
            })
        } finally {
            logsLoading.value = false
        }
    })
}
</script>

<template>
    <Head title="Metas de Conversao" />

    <div class="tw-space-y-3">
        <div class="tw-flex tw-items-center tw-justify-between tw-flex-col sm:tw-flex-row tw-gap-3">
            <h1 class="tw-text-lg tw-font-semibold">
                Metas de Conversao
            </h1>

            <Link :href="route('panel.conversion-goals.create')">
                <q-btn
                    color="positive"
                    icon="add"
                    label="Nova Meta"
                    unelevated
                />
            </Link>
        </div>

        <q-card flat bordered>
            <q-card-section class="tw-space-y-3">
                <div class="tw-flex tw-gap-3 tw-flex-col md:tw-flex-row">
                    <q-input
                        v-model="search"
                        dense
                        outlined
                        clearable
                        placeholder="Pesquisar por codigo"
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
                    <template #body-cell-active="props">
                        <q-td :props="props" class="tw-text-center">
                            <q-chip
                                dense
                                size="sm"
                                :color="props.row.active ? 'positive' : 'negative'"
                                text-color="white"
                            >
                                {{ props.row.active ? 'Ativo' : 'Inativo' }}
                            </q-chip>
                        </q-td>
                    </template>

                    <template #body-cell-created_at="props">
                        <q-td :props="props">
                            {{ props.row.created_at ? new Date(props.row.created_at).toLocaleString('pt-BR') : '-' }}
                        </q-td>
                    </template>

                    <template #body-cell-campaigns="props">
                        <q-td :props="props">
                            <span class="tw-text-xs tw-text-slate-700">
                                {{ campaignsLabel(props.row) }}
                            </span>
                        </q-td>
                    </template>

                    <template #body-cell-timezone="props">
                        <q-td :props="props">
                            <span class="tw-text-xs tw-text-slate-700">
                                {{ props.row.timezone?.label ?? props.row.timezone?.identifier ?? '-' }}
                            </span>
                        </q-td>
                    </template>

                    <template #body-cell-actions="props">
                        <q-td :props="props" class="tw-text-right">
                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="edit"
                                color="primary"
                                @click="editItem(props.row)"
                            />

                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="delete"
                                color="negative"
                                @click="destroyItem(props.row)"
                            />
                        </q-td>
                    </template>

                    <template #body-cell-integration="props">
                        <q-td :props="props" class="tw-text-center">
                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="link"
                                color="primary"
                                @click="openIntegration(props.row)"
                            />
                        </q-td>
                    </template>

                    <template #body-cell-logs="props">
                        <q-td :props="props" class="tw-text-center">
                            <q-btn
                                flat
                                dense
                                size="sm"
                                icon="history"
                                label="Logs"
                                color="secondary"
                                @click="openLogs(props.row)"
                            />
                        </q-td>
                    </template>
                </q-table>
            </q-card-section>
        </q-card>

        <q-dialog v-model="integrationDialog">
            <q-card style="min-width: 620px; max-width: 90vw;">
                <q-card-section class="tw-flex tw-items-center tw-justify-between">
                    <div class="tw-text-base tw-font-semibold">
                        Integração Google Ads
                    </div>
                    <q-btn flat round dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section class="tw-space-y-4">
                    <q-input
                        :model-value="integrationPayload?.url ?? ''"
                        label="URL CSV"
                        readonly
                        outlined
                        dense
                    >
                        <template #append>
                            <q-btn flat dense icon="content_copy" @click.stop.prevent="copyValue(integrationPayload?.url, 'URL')" />
                        </template>
                    </q-input>

                    <q-input
                        :model-value="integrationPayload?.username ?? ''"
                        label="Usuário"
                        readonly
                        outlined
                        dense
                    >
                        <template #append>
                            <q-btn flat dense icon="content_copy" @click.stop.prevent="copyValue(integrationPayload?.username, 'Usuário')" />
                        </template>
                    </q-input>

                    <q-input
                        :model-value="integrationPayload?.password ?? ''"
                        label="Senha"
                        readonly
                        outlined
                        dense
                    >
                        <template #append>
                            <q-btn flat dense icon="content_copy" @click.stop.prevent="copyValue(integrationPayload?.password, 'Senha')" />
                        </template>
                    </q-input>
                </q-card-section>

                <q-separator />

                <q-card-actions align="right">
                    <q-btn flat label="Fechar" v-close-popup />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <q-dialog v-model="logsDialog" :maximized="$q.screen.lt.sm">
            <q-card :style="logsCardStyle()">
                <q-card-section class="tw-flex tw-items-center tw-justify-between">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div class="tw-text-base tw-font-semibold">
                            Logs da Meta {{ logsGoalCode || '-' }}
                        </div>
                        <span
                            class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-3 tw-py-1 tw-text-xs tw-font-medium tw-text-slate-600"
                        >
                            Os logs são armazenados temporariamente e serão excluídos após {{ props.logsRetentionDays }} dias.
                        </span>
                    </div>
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <q-btn
                            flat
                            dense
                            color="negative"
                            icon="delete_sweep"
                            label="Excluir logs"
                            :disable="logsLoading || !logsGoalKey"
                            @click="deleteCurrentGoalLogs"
                        />
                        <q-btn flat round dense icon="close" v-close-popup />
                    </div>
                </q-card-section>

                <q-separator />

                <q-card-section class="tw-p-0" style="height: calc(100% - 72px);">
                    <q-table
                        flat
                        :rows="logsRows"
                        :columns="logsColumns"
                        row-key="id"
                        :loading="logsLoading"
                        v-model:pagination="logsPagination"
                        :pagination="logsPagination"
                        :rows-per-page-options="[10, 15, 25, 50]"
                        :no-data-label="qTableLangPt.noData"
                        :no-results-label="qTableLangPt.noResults"
                        :loading-label="qTableLangPt.loading"
                        :rows-per-page-label="qTableLangPt.recordsPerPage"
                        :all-rows-label="qTableLangPt.allRows"
                        :pagination-label="qTableLangPt.pagination"
                        binary-state-sort
                        @request="onLogsRequest"
                    />
                </q-card-section>
            </q-card>
        </q-dialog>
    </div>
</template>
