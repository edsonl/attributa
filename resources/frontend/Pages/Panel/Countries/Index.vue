<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'

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
const errors = ref({})
const $q = useQuasar()
const SEARCH_DEBOUNCE = 400
let searchTimeout = null

const defaultForm = () => ({
    id: null,
    iso2: '',
    iso3: '',
    name: '',
    currency: '',
    currency_symbol: '',
    timezone_default: '',
    active: true,
})

const form = ref(defaultForm())
const isEditing = computed(() => Boolean(form.value.id))
const assetBaseUrl = (
    import.meta.env.VITE_ASSET_URL
        ?? (typeof window !== 'undefined' ? window.location.origin : 'http://attributa.site')
).replace(/\/$/, '')

const columns = [
    { name: 'name', label: 'Nome', field: 'name', sortable: true, align: 'left' },
    { name: 'iso2', label: 'ISO2', field: 'iso2', sortable: true, align: 'left' },
    { name: 'iso3', label: 'ISO3', field: 'iso3', sortable: true, align: 'left' },
    { name: 'currency', label: 'Moeda', field: 'currency', sortable: true, align: 'left' },
    { name: 'currency_symbol', label: 'Símbolo', field: 'currency_symbol', sortable: true, align: 'left' },
    { name: 'timezone_default', label: 'Timezone', field: 'timezone_default', sortable: true, align: 'left' },
    { name: 'active', label: 'Status', field: 'active', sortable: true, align: 'left' },
    { name: 'created_at', label: 'Criado em', field: 'created_at', sortable: true, align: 'left' },
    { name: 'actions', label: 'Ações', field: 'id', align: 'right' },
]

function formatDate(date) {
    if (!date) return '-'
    return new Date(date).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    })
}

function resolveCountryFlag(row) {
    const code = row?.iso2
    if (!code) return null

    const lowerCode = String(code).toLowerCase()
    const base = assetBaseUrl || ''
    return `${base}/assets/country-flags/${lowerCode}.svg`
}

function fetchCountries(props) {
    loading.value = true

    const { page, rowsPerPage, sortBy, descending } = props.pagination

    return axios.get(route('panel.countries.data'), {
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
        fetchCountries({ pagination: pagination.value })
    }

    if (immediate) {
        run()
    } else {
        searchTimeout = setTimeout(run, SEARCH_DEBOUNCE)
    }
}

function openCreate() {
    form.value = defaultForm()
    errors.value = {}
    dialogVisible.value = true
}

function openEdit(row) {
    form.value = {
        id: row.id,
        iso2: row.iso2,
        iso3: row.iso3,
        name: row.name,
        currency: row.currency,
        currency_symbol: row.currency_symbol,
        timezone_default: row.timezone_default,
        active: row.active,
    }
    errors.value = {}
    dialogVisible.value = true
}

function closeDialog() {
    dialogVisible.value = false
    form.value = defaultForm()
    errors.value = {}
}

async function saveCountry() {
    if (saving.value) return
    saving.value = true
    errors.value = {}

    const payload = { ...form.value }
    const request = form.value.id
        ? axios.put(route('panel.countries.update', form.value.id), payload)
        : axios.post(route('panel.countries.store'), payload)

    try {
        await request
        dialogVisible.value = false
        form.value = defaultForm()
        $q.notify({ type: 'positive', message: 'Dados salvos com sucesso!' })
        await fetchCountries({ pagination: pagination.value })
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
        } else {
            $q.notify({ type: 'negative', message: 'Não foi possível salvar o país.' })
        }
    } finally {
        saving.value = false
    }
}

async function deleteCountry(id) {
    if (!id) return
    const confirmDialog = typeof window !== 'undefined' ? window.$confirm : null
    let confirmed = true

    if (confirmDialog) {
        confirmed = await confirmDialog({
            title: 'Excluir país',
            message: 'Esta ação é permanente. Deseja realmente remover?',
            okLabel: 'Remover',
            okColor: 'negative',
        })
    } else if (typeof window !== 'undefined') {
        confirmed = window.confirm('Excluir este país?')
    }

    if (!confirmed) return

    loading.value = true
    try {
        await axios.delete(route('panel.countries.destroy', id))
        $q.notify({ type: 'positive', message: 'País removido.' })
        await fetchCountries({ pagination: pagination.value })
    } catch (error) {
        $q.notify({ type: 'negative', message: 'Falha ao remover o país.' })
    } finally {
        loading.value = false
    }
}

watch(search, () => {
    triggerSearch()
})

onMounted(() => {
    fetchCountries({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Países" />
    <div class="tw-space-y-3">
        <q-card flat bordered class="tw-rounded-2xl tw-p-4">
            <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-mb-4">
                <div class="tw-flex tw-gap-2">
                    <q-btn
                        color="positive"
                        unelevated
                        icon="add"
                        label="Novo País"
                        @click="openCreate"
                    />
                </div>
                <q-space />
                <div class="tw-flex tw-gap-2 tw-w-full md:tw-w-[360px] lg:tw-w-[420px] md:tw-ml-auto md:tw-justify-end">
                    <q-input
                        v-model="search"
                        dense
                        outlined
                        clearable
                        placeholder="Buscar por nome ou código"
                    >
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
                @request="fetchCountries"
            >
                <template #body-cell-name="props">
                    <q-td :props="props">
                        <div class="tw-flex tw-items-center tw-gap-2">
                            <img
                                v-if="resolveCountryFlag(props.row)"
                                :src="resolveCountryFlag(props.row)"
                                :alt="props.row.iso2"
                                class="tw-w-6 tw-h-4 tw-rounded-sm tw-object-cover tw-border tw-border-gray-200"
                            />
                            <span>{{ props.value || '-' }}</span>
                        </div>
                    </q-td>
                </template>
                <template #body-cell-active="props">
                    <q-td :props="props">
                        <q-badge
                            :color="props.row.active ? 'positive' : 'grey-5'"
                            :label="props.row.active ? 'Ativo' : 'Inativo'"
                        />
                    </q-td>
                </template>
                <template #body-cell-created_at="props">
                    <q-td :props="props">
                        {{ formatDate(props.value) }}
                    </q-td>
                </template>
                <template #body-cell-actions="props">
                    <q-td :props="props" class="tw-text-right">
                        <q-btn dense flat size="sm" icon="edit" class="qtable-edit-btn" @click="openEdit(props.row)" />
                        <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="deleteCountry(props.row.id)" />
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </div>

    <q-dialog v-model="dialogVisible" persistent>
        <q-card class="tw-w-full tw-max-w-2xl">
            <q-card-section>
                <div class="text-h6">
                    {{ isEditing ? 'Editar país' : 'Novo país' }}
                </div>
            </q-card-section>
            <q-separator />
            <q-card-section class="tw-space-y-3">
                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                    <q-input
                        v-model="form.name"
                        label="Nome"
                        dense outlined
                        :error="Boolean(errors.name)"
                        :error-message="errors.name?.[0]"
                    />
                    <q-input
                        v-model="form.iso2"
                        label="ISO2"
                        dense outlined
                        maxlength="2"
                        :error="Boolean(errors.iso2)"
                        :error-message="errors.iso2?.[0]"
                    />
                    <q-input
                        v-model="form.iso3"
                        label="ISO3"
                        dense outlined
                        maxlength="3"
                        :error="Boolean(errors.iso3)"
                        :error-message="errors.iso3?.[0]"
                    />
                    <q-input
                        v-model="form.currency"
                        label="Moeda (ISO 4217)"
                        dense outlined
                        maxlength="3"
                        :error="Boolean(errors.currency)"
                        :error-message="errors.currency?.[0]"
                    />
                    <q-input
                        v-model="form.currency_symbol"
                        label="Símbolo da moeda"
                        dense outlined
                        maxlength="5"
                        :error="Boolean(errors.currency_symbol)"
                        :error-message="errors.currency_symbol?.[0]"
                    />
                    <q-input
                        v-model="form.timezone_default"
                        label="Timezone padrão"
                        dense outlined
                        :error="Boolean(errors.timezone_default)"
                        :error-message="errors.timezone_default?.[0]"
                    />
                </div>
                <q-toggle
                    v-model="form.active"
                    label="Ativo"
                />
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="closeDialog" />
                <q-btn
                    color="primary"
                    unelevated
                    :loading="saving"
                    :label="isEditing ? 'Atualizar' : 'Cadastrar'"
                    @click="saveCountry"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
