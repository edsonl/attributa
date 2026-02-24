<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar, copyToClipboard } from 'quasar'

const props = defineProps({
    integration: {
        type: Object,
        default: () => ({
            callback_base_url: '',
            user_code: '',
        }),
    },
})

const rows = ref([])
const loading = ref(false)
const pagination = ref({
    page: 1,
    rowsPerPage: 10,
    rowsNumber: 0,
    sortBy: 'name',
    descending: false,
})
const search = ref('')
const saving = ref(false)
const dialogVisible = ref(false)
const integrationDialog = ref(false)
const integrationPayload = ref(null)
const errors = ref({})
const $q = useQuasar()
const SEARCH_DEBOUNCE = 400
let searchTimeout = null

const integrationTypeOptions = [
    { label: 'Postback GET', value: 'postback_get' },
]
const leadStatusOptions = [
    { label: 'processing', value: 'processing' },
    { label: 'rejected', value: 'rejected' },
    { label: 'trash', value: 'trash' },
    { label: 'approved', value: 'approved' },
    { label: 'cancelled', value: 'cancelled' },
    { label: 'refunded', value: 'refunded' },
    { label: 'chargeback', value: 'chargeback' },
]

const defaultForm = () => ({
    id: null,
    name: '',
    slug: '',
    active: true,
    integration_type: 'postback_get',
    lead_param_mapping: {
        payout_amount: '',
        currency_code: '',
        lead_status: '',
        platform_lead_id: '',
        occurred_at: '',
        offer_id: '',
    },
    lead_status_mapping: {},
})

const form = ref(defaultForm())
const mappingRows = ref([{ source: '', target: '' }])
const leadStatusMappingRows = ref([{ raw_status: '', internal_status: 'processing' }])
const additionalParams = ref([])
const additionalParamInput = ref('')
const isEditing = computed(() => Boolean(form.value.id))

const columns = [
    { name: 'name', label: 'Nome', field: 'name', sortable: true, align: 'left' },
    { name: 'slug', label: 'Slug', field: 'slug', sortable: true, align: 'left' },
    { name: 'integration_type', label: 'Tipo de integração', field: 'integration_type_label', sortable: true, align: 'left' },
    { name: 'mapping', label: 'Mapeamento', field: 'mapping_preview', sortable: false, align: 'left' },
    { name: 'active', label: 'Status', field: 'active', sortable: true, align: 'left' },
    { name: 'created_at', label: 'Criado em', field: 'created_at', sortable: true, align: 'left' },
    { name: 'integration', label: 'Integração', field: 'integration', sortable: false, align: 'center' },
    { name: 'actions', label: 'Ações', field: 'id', align: 'right' },
]

const callbackPreviewUrl = computed(() => {
    const baseUrl = String(props.integration?.callback_base_url || '').replace(/\/$/, '')
    const userCode = String(props.integration?.user_code || '').trim()
    const slug = String(form.value.slug || '').trim()
    if (!baseUrl || !userCode || !slug) return ''

    const endpoint = `${baseUrl}/${encodeURIComponent(slug)}/${encodeURIComponent(userCode)}`
    const mappingTargets = mappingRows.value
        .map(item => String(item.target || '').trim())
        .filter(Boolean)
    const leadMappedParams = [
        String(form.value?.lead_param_mapping?.payout_amount || '').trim(),
        String(form.value?.lead_param_mapping?.currency_code || '').trim(),
        String(form.value?.lead_param_mapping?.lead_status || '').trim(),
        String(form.value?.lead_param_mapping?.platform_lead_id || '').trim(),
        String(form.value?.lead_param_mapping?.occurred_at || '').trim(),
        String(form.value?.lead_param_mapping?.offer_id || '').trim(),
    ].filter(Boolean)

    const params = [...new Set([...mappingTargets, ...leadMappedParams, ...additionalParams.value])]
    if (params.length === 0) return endpoint

    const query = params.map(param => `${encodeURIComponent(param)}={${param}}`).join('&')
    return `${endpoint}?${query}`
})

function formatDate(date) {
    if (!date) return '-'
    return new Date(date).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    })
}

function fetchRows(propsArg) {
    loading.value = true
    const { page, rowsPerPage, sortBy, descending } = propsArg.pagination

    return axios.get(route('panel.affiliate-platforms.data'), {
        params: {
            page,
            per_page: rowsPerPage,
            sortBy,
            descending,
            search: search.value || undefined,
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

function triggerSearch(immediate = false) {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
        searchTimeout = null
    }

    const run = () => {
        pagination.value.page = 1
        fetchRows({ pagination: pagination.value })
    }

    if (immediate) {
        run()
    } else {
        searchTimeout = setTimeout(run, SEARCH_DEBOUNCE)
    }
}

function openCreate() {
    form.value = defaultForm()
    mappingRows.value = [{ source: '', target: '' }]
    leadStatusMappingRows.value = [{ raw_status: '', internal_status: 'processing' }]
    additionalParams.value = []
    additionalParamInput.value = ''
    errors.value = {}
    dialogVisible.value = true
}

function openEdit(row) {
    form.value = {
        id: row.id,
        name: row.name,
        slug: row.slug,
        active: row.active,
        integration_type: row.integration_type || 'postback_get',
        lead_param_mapping: {
            payout_amount: String(row.lead_param_mapping?.payout_amount || ''),
            currency_code: String(row.lead_param_mapping?.currency_code || ''),
            lead_status: String(row.lead_param_mapping?.lead_status || ''),
            platform_lead_id: String(row.lead_param_mapping?.platform_lead_id || ''),
            occurred_at: String(row.lead_param_mapping?.occurred_at || ''),
            offer_id: String(row.lead_param_mapping?.offer_id || ''),
        },
        lead_status_mapping: row.lead_status_mapping || {},
    }

    const pairs = Object.entries(row.tracking_param_mapping || {})
        .map(([source, target]) => ({
            source: String(source || ''),
            target: String(target || ''),
        }))

    const additionalParamsFromRow = (row.postback_additional_params || [])
        .map((name) => String(name || '').trim())
        .filter(Boolean)

    mappingRows.value = pairs.length > 0 ? pairs : [{ source: '', target: '' }]
    const statusPairs = Object.entries(row.lead_status_mapping || {})
        .map(([rawStatus, internalStatus]) => ({
            raw_status: String(rawStatus || ''),
            internal_status: String(internalStatus || 'processing'),
        }))
    leadStatusMappingRows.value = statusPairs.length > 0
        ? statusPairs
        : [{ raw_status: '', internal_status: 'processing' }]
    additionalParams.value = [...new Set(additionalParamsFromRow)]
    additionalParamInput.value = ''
    errors.value = {}
    dialogVisible.value = true
}

function closeDialog() {
    dialogVisible.value = false
    form.value = defaultForm()
    mappingRows.value = [{ source: '', target: '' }]
    leadStatusMappingRows.value = [{ raw_status: '', internal_status: 'processing' }]
    additionalParams.value = []
    additionalParamInput.value = ''
    errors.value = {}
}

function addMappingRow() {
    mappingRows.value.push({ source: '', target: '' })
}

function removeMappingRow(index) {
    if (mappingRows.value.length === 1) {
        mappingRows.value[0] = { source: '', target: '' }
        return
    }
    mappingRows.value.splice(index, 1)
}

function addLeadStatusMappingRow() {
    leadStatusMappingRows.value.push({ raw_status: '', internal_status: 'processing' })
}

function removeLeadStatusMappingRow(index) {
    if (leadStatusMappingRows.value.length === 1) {
        leadStatusMappingRows.value[0] = { raw_status: '', internal_status: 'processing' }
        return
    }
    leadStatusMappingRows.value.splice(index, 1)
}

function addAdditionalParamChip() {
    const value = String(additionalParamInput.value || '').trim()
    if (!value) return
    if (!additionalParams.value.includes(value)) {
        additionalParams.value.push(value)
    }
    additionalParamInput.value = ''
}

function removeAdditionalParamChip(param) {
    additionalParams.value = additionalParams.value.filter(item => item !== param)
}

function buildMappingPayload() {
    const mapping = {}
    for (const row of mappingRows.value) {
        const source = String(row.source || '').trim()
        const target = String(row.target || '').trim()
        if (!source || !target) continue
        mapping[source] = target
    }
    return mapping
}

function buildAdditionalParamsPayload() {
    const names = additionalParams.value
        .map(name => String(name || '').trim())
        .filter(Boolean)
    return [...new Set(names)]
}

function buildLeadParamMappingPayload() {
    const payoutAmount = String(form.value?.lead_param_mapping?.payout_amount || '').trim()
    const currencyCode = String(form.value?.lead_param_mapping?.currency_code || '').trim()
    const leadStatus = String(form.value?.lead_param_mapping?.lead_status || '').trim()
    const platformLeadId = String(form.value?.lead_param_mapping?.platform_lead_id || '').trim()
    const occurredAt = String(form.value?.lead_param_mapping?.occurred_at || '').trim()
    const offerId = String(form.value?.lead_param_mapping?.offer_id || '').trim()

    const mapping = {}
    if (payoutAmount) {
        mapping.payout_amount = payoutAmount
    }
    if (currencyCode) {
        mapping.currency_code = currencyCode
    }
    if (leadStatus) {
        mapping.lead_status = leadStatus
    }
    if (platformLeadId) {
        mapping.platform_lead_id = platformLeadId
    }
    if (occurredAt) {
        mapping.occurred_at = occurredAt
    }
    if (offerId) {
        mapping.offer_id = offerId
    }

    return mapping
}

function buildLeadStatusMappingPayload() {
    const mapping = {}
    for (const row of leadStatusMappingRows.value) {
        const rawStatus = String(row.raw_status || '').trim().toLowerCase()
        const internalStatus = String(row.internal_status || '').trim().toLowerCase()
        if (!rawStatus || !internalStatus) continue
        mapping[rawStatus] = internalStatus
    }
    return mapping
}

async function copyValue(value, label) {
    const text = String(value || '').trim()
    if (!text) {
        $q.notify({ type: 'warning', message: `${label} vazio.` })
        return
    }

    try {
        await copyToClipboard(text)
        $q.notify({ type: 'positive', message: `${label} copiado.` })
    } catch {
        $q.notify({ type: 'negative', message: `Falha ao copiar ${label.toLowerCase()}.` })
    }
}

async function saveRow() {
    if (saving.value) return
    saving.value = true
    errors.value = {}

    const payload = {
        ...form.value,
        tracking_param_mapping: buildMappingPayload(),
        lead_param_mapping: buildLeadParamMappingPayload(),
        lead_status_mapping: buildLeadStatusMappingPayload(),
        postback_additional_params: buildAdditionalParamsPayload(),
    }

    const request = form.value.id
        ? axios.put(route('panel.affiliate-platforms.update', form.value.id), payload)
        : axios.post(route('panel.affiliate-platforms.store'), payload)

    try {
        await request
        dialogVisible.value = false
        form.value = defaultForm()
        mappingRows.value = [{ source: '', target: '' }]
        leadStatusMappingRows.value = [{ raw_status: '', internal_status: 'processing' }]
        additionalParams.value = []
        additionalParamInput.value = ''
        $q.notify({ type: 'positive', message: 'Dados salvos com sucesso!' })
        await fetchRows({ pagination: pagination.value })
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            if (error.response.data.message) {
                $q.notify({ type: 'warning', message: error.response.data.message })
            }
        } else {
            $q.notify({ type: 'negative', message: 'Não foi possível salvar o registro.' })
        }
    } finally {
        saving.value = false
    }
}

async function deleteRow(id) {
    if (!id) return
    const confirmDialog = typeof window !== 'undefined' ? window.$confirm : null
    let confirmed = true

    if (confirmDialog) {
        confirmed = await confirmDialog({
            title: 'Excluir plataforma',
            message: 'Esta ação é permanente. Deseja realmente remover?',
            okLabel: 'Remover',
            okColor: 'negative',
        })
    } else if (typeof window !== 'undefined') {
        confirmed = window.confirm('Excluir esta plataforma?')
    }

    if (!confirmed) return

    loading.value = true
    try {
        await axios.delete(route('panel.affiliate-platforms.destroy', id))
        $q.notify({ type: 'positive', message: 'Plataforma removida.' })
        await fetchRows({ pagination: pagination.value })
    } catch (error) {
        $q.notify({
            type: 'warning',
            message: error?.response?.data?.message || 'Falha ao remover a plataforma.',
        })
    } finally {
        loading.value = false
    }
}

function openIntegration(row) {
    integrationPayload.value = row
    integrationDialog.value = true
}

watch(search, () => {
    triggerSearch()
})

onMounted(() => {
    fetchRows({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Plataformas de Afiliado" />
    <div class="tw-space-y-3">
        <q-card flat bordered class="tw-rounded-2xl tw-p-4">
            <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-mb-4">
                <div class="tw-flex tw-gap-2">
                    <q-btn color="positive" unelevated icon="add" label="Nova Plataforma" @click="openCreate" />
                </div>
                <q-space />
                <div class="tw-flex tw-gap-2 tw-w-full md:tw-w-[360px] lg:tw-w-[420px] md:tw-ml-auto md:tw-justify-end">
                    <q-input v-model="search" dense outlined clearable placeholder="Buscar por nome ou slug">
                        <template #append>
                            <q-icon name="search" class="cursor-pointer" @click="triggerSearch(true)" />
                        </template>
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
                @request="fetchRows"
            >
                <template #body-cell-mapping="props">
                    <q-td :props="props">
                        <span class="tw-text-xs tw-text-slate-700">{{ props.value || '-' }}</span>
                    </q-td>
                </template>
                <template #body-cell-active="props">
                    <q-td :props="props">
                        <q-badge :color="props.row.active ? 'positive' : 'grey-6'" :label="props.row.active ? 'Ativo' : 'Inativo'" />
                    </q-td>
                </template>
                <template #body-cell-created_at="props">
                    <q-td :props="props">{{ formatDate(props.value) }}</q-td>
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
                <template #body-cell-actions="props">
                    <q-td :props="props" class="tw-text-right">
                        <q-btn dense flat size="sm" icon="edit" class="qtable-edit-btn" @click="openEdit(props.row)" />
                        <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="deleteRow(props.row.id)" />
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </div>

    <q-dialog v-model="dialogVisible" persistent>
        <q-card class="tw-w-full tw-max-w-6xl" style="width: 870px; max-width: 96vw;">
            <q-card-section>
                <div class="text-h6">{{ isEditing ? 'Editar plataforma' : 'Nova plataforma' }}</div>
            </q-card-section>
            <q-separator />
            <q-card-section class="tw-space-y-3">
                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                    <q-input v-model="form.name" label="Nome" dense outlined :error="Boolean(errors.name)" :error-message="errors.name?.[0]" />
                    <q-input v-model="form.slug" label="Slug" dense outlined :error="Boolean(errors.slug)" :error-message="errors.slug?.[0]" />
                    <q-select
                        v-model="form.integration_type"
                        :options="integrationTypeOptions"
                        option-label="label"
                        option-value="value"
                        emit-value
                        map-options
                        label="Tipo de integração"
                        dense
                        outlined
                        :error="Boolean(errors.integration_type)"
                        :error-message="errors.integration_type?.[0]"
                    />
                    <q-toggle v-model="form.active" label="Plataforma ativa" />
                </div>

                <q-input
                    :model-value="callbackPreviewUrl"
                    label="URL de Callback Lead (dinâmica)"
                    dense
                    outlined
                    readonly
                >
                    <template #append>
                        <q-btn flat dense icon="content_copy" @click.stop.prevent="copyValue(callbackPreviewUrl, 'URL de Callback Lead')" />
                    </template>
                </q-input>

                <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-4">
                    <div class="tw-space-y-2">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <div class="tw-text-sm tw-font-medium">Mapeamento de tracking (origem -> retorno)</div>
                            <q-btn
                                dense
                                unelevated
                                round
                                color="positive"
                                text-color="white"
                                icon="add"
                                @click="addMappingRow"
                            >
                                <q-tooltip>Adicionar novo par de mapeamento</q-tooltip>
                            </q-btn>
                        </div>
                        <div class="tw-space-y-2">
                            <div v-for="(item, index) in mappingRows" :key="`mapping-${index}`" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-[1fr_24px_1fr_auto] tw-gap-2 tw-items-center">
                                <q-input
                                    v-model="item.source"
                                    label="Parâmetro origem"
                                    dense
                                    outlined
                                    placeholder="ex: sub1"
                                />
                                <div class="tw-text-center tw-text-slate-500">-></div>
                                <q-input
                                    v-model="item.target"
                                    label="Parâmetro retorno"
                                    dense
                                    outlined
                                    placeholder="ex: subid1"
                                />
                                <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="removeMappingRow(index)" />
                            </div>
                        </div>
                    </div>

                    <div class="tw-space-y-4">
                        <div class="tw-space-y-2">
                            <div class="tw-text-sm tw-font-medium">Mapeamento do lead (postback -> leads)</div>
                            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-2">
                                <q-input
                                    v-model="form.lead_param_mapping.payout_amount"
                                    label="payout_amount"
                                    dense
                                    outlined
                                    placeholder="ex: payment"
                                    :error="Boolean(errors['lead_param_mapping.payout_amount'])"
                                    :error-message="errors['lead_param_mapping.payout_amount']?.[0]"
                                />
                                <q-input
                                    v-model="form.lead_param_mapping.currency_code"
                                    label="currency_code"
                                    dense
                                    outlined
                                    placeholder="ex: cy"
                                    :error="Boolean(errors['lead_param_mapping.currency_code'])"
                                    :error-message="errors['lead_param_mapping.currency_code']?.[0]"
                                />
                                <q-input
                                    v-model="form.lead_param_mapping.lead_status"
                                    label="lead_status"
                                    dense
                                    outlined
                                    placeholder="ex: status"
                                    :error="Boolean(errors['lead_param_mapping.lead_status'])"
                                    :error-message="errors['lead_param_mapping.lead_status']?.[0]"
                                />
                                <q-input
                                    v-model="form.lead_param_mapping.platform_lead_id"
                                    label="platform_lead_id"
                                    dense
                                    outlined
                                    placeholder="ex: uuid"
                                    :error="Boolean(errors['lead_param_mapping.platform_lead_id'])"
                                    :error-message="errors['lead_param_mapping.platform_lead_id']?.[0]"
                                />
                                <q-input
                                    v-model="form.lead_param_mapping.occurred_at"
                                    label="occurred_at"
                                    dense
                                    outlined
                                    placeholder="ex: date"
                                    :error="Boolean(errors['lead_param_mapping.occurred_at'])"
                                    :error-message="errors['lead_param_mapping.occurred_at']?.[0]"
                                />
                                <q-input
                                    v-model="form.lead_param_mapping.offer_id"
                                    label="offer_id"
                                    dense
                                    outlined
                                    placeholder="ex: offer"
                                    :error="Boolean(errors['lead_param_mapping.offer_id'])"
                                    :error-message="errors['lead_param_mapping.offer_id']?.[0]"
                                />
                            </div>
                            <div class="tw-text-xs tw-text-slate-500">
                                Dica: estes campos são da tabela leads. Exemplo Dr Cash: payout_amount = payment, currency_code = currency, lead_status = status, platform_lead_id = uuid, occurred_at = date, offer_id = offer.
                            </div>
                        </div>

                    </div>
                </div>

                <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-4">
                    <div class="tw-space-y-2">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <div class="tw-text-sm tw-font-medium">Mapeamento de status do lead (status recebido da plataforma -> status interno)</div>
                            <q-btn
                                dense
                                unelevated
                                round
                                color="positive"
                                text-color="white"
                                icon="add"
                                @click="addLeadStatusMappingRow"
                            >
                                <q-tooltip>Adicionar novo de/para de status</q-tooltip>
                            </q-btn>
                        </div>
                        <div class="tw-space-y-2">
                            <div v-for="(item, index) in leadStatusMappingRows" :key="`lead-status-${index}`" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-[1fr_24px_1fr_auto] tw-gap-2 tw-items-center">
                                <q-input
                                    v-model="item.raw_status"
                                    label="status da plataforma"
                                    dense
                                    outlined
                                    placeholder="ex: trash"
                                />
                                <div class="tw-text-center tw-text-slate-500">-></div>
                                <q-select
                                    v-model="item.internal_status"
                                    :options="leadStatusOptions"
                                    option-label="label"
                                    option-value="value"
                                    emit-value
                                    map-options
                                    label="status interno"
                                    dense
                                    outlined
                                />
                                <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="removeLeadStatusMappingRow(index)" />
                            </div>
                        </div>
                        <div class="tw-text-xs tw-text-slate-500">
                            Exemplo: approved -> approved, rejected -> rejected, trash -> trash, hold -> processing, canceled -> cancelled, refunded -> refunded.
                        </div>
                    </div>

                    <div class="tw-space-y-2">
                        <div class="tw-text-sm tw-font-medium">Parâmetros adicionais do postback</div>
                        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-[1fr_auto] tw-gap-2 tw-items-center">
                            <q-input
                                v-model="additionalParamInput"
                                label="Parâmetro"
                                dense
                                outlined
                                placeholder="ex: orderid"
                                @keyup.enter="addAdditionalParamChip"
                            />
                            <q-btn
                                dense
                                unelevated
                                round
                                color="positive"
                                text-color="white"
                                icon="add"
                                @click="addAdditionalParamChip"
                            >
                                <q-tooltip>Adicionar parâmetro adicional</q-tooltip>
                            </q-btn>
                        </div>
                        <div class="tw-flex tw-flex-wrap tw-gap-2 tw-min-h-[28px]">
                            <q-chip
                                v-for="param in additionalParams"
                                :key="`additional-${param}`"
                                removable
                                dense
                                color="blue-1"
                                text-color="primary"
                                @remove="removeAdditionalParamChip(param)"
                            >
                                {{ param }}
                            </q-chip>
                        </div>
                        <div class="tw-text-xs tw-text-slate-500">
                            Exemplo Dr Cash: orderid, product, amount, cy, status.
                        </div>
                    </div>
                </div>
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="closeDialog" />
                <q-btn color="primary" unelevated :loading="saving" :label="isEditing ? 'Atualizar' : 'Cadastrar'" @click="saveRow" />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-dialog v-model="integrationDialog">
        <q-card style="min-width: 680px; max-width: 92vw;">
            <q-card-section class="tw-flex tw-items-center tw-justify-between">
                <div class="tw-text-base tw-font-semibold">
                    Integração de plataforma
                </div>
                <q-btn flat round dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section class="tw-space-y-4">
                <q-input
                    :model-value="integrationPayload?.callback_url ?? ''"
                    label="URL de Callback Lead"
                    readonly
                    outlined
                    dense
                >
                    <template #append>
                        <q-btn flat dense icon="content_copy" @click.stop.prevent="copyValue(integrationPayload?.callback_url, 'URL de Callback Lead')" />
                    </template>
                </q-input>

                <q-input
                    :model-value="integrationPayload?.integration_type_label ?? ''"
                    label="Tipo de integração"
                    readonly
                    outlined
                    dense
                />

                <q-input
                    :model-value="integrationPayload?.mapping_preview ?? '-'"
                    label="Mapeamento de tracking"
                    readonly
                    outlined
                    dense
                />

                <q-input
                    :model-value="(integrationPayload?.postback_additional_params || []).join(', ') || '-'"
                    label="Parâmetros adicionais"
                    readonly
                    outlined
                    dense
                />

                <q-input
                    :model-value="[
                        integrationPayload?.lead_param_mapping?.payout_amount ? `payout: ${integrationPayload.lead_param_mapping.payout_amount}` : null,
                        integrationPayload?.lead_param_mapping?.currency_code ? `moeda: ${integrationPayload.lead_param_mapping.currency_code}` : null,
                        integrationPayload?.lead_param_mapping?.lead_status ? `status: ${integrationPayload.lead_param_mapping.lead_status}` : null,
                        integrationPayload?.lead_param_mapping?.platform_lead_id ? `id externo: ${integrationPayload.lead_param_mapping.platform_lead_id}` : null,
                        integrationPayload?.lead_param_mapping?.occurred_at ? `data: ${integrationPayload.lead_param_mapping.occurred_at}` : null,
                        integrationPayload?.lead_param_mapping?.offer_id ? `offer: ${integrationPayload.lead_param_mapping.offer_id}` : null,
                    ].filter(Boolean).join(' | ') || '-'"
                    label="Mapeamento de lead"
                    readonly
                    outlined
                    dense
                />

                <q-input
                    :model-value="Object.entries(integrationPayload?.lead_status_mapping || {})
                        .map(([from, to]) => `${from} -> ${to}`)
                        .join(' | ') || '-'"
                    label="Mapeamento de status"
                    readonly
                    outlined
                    dense
                />
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <q-btn flat label="Fechar" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
