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
    name: '',
    slug: '',
    description: '',
    is_system: true,
})

const form = ref(defaultForm())
const isEditing = computed(() => Boolean(form.value.id))

const columns = [
    { name: 'name', label: 'Nome', field: 'name', sortable: true, align: 'left' },
    { name: 'slug', label: 'Slug', field: 'slug', sortable: true, align: 'left' },
    { name: 'description', label: 'Descrição', field: 'description', sortable: false, align: 'left' },
    { name: 'is_system', label: 'Tipo', field: 'is_system', sortable: true, align: 'left' },
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

function fetchRows(props) {
    loading.value = true
    const { page, rowsPerPage, sortBy, descending } = props.pagination

    return axios.get(route('panel.device-categories.data'), {
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
    errors.value = {}
    dialogVisible.value = true
}

function openEdit(row) {
    form.value = {
        id: row.id,
        name: row.name,
        slug: row.slug,
        description: row.description ?? '',
        is_system: row.is_system,
    }
    errors.value = {}
    dialogVisible.value = true
}

function closeDialog() {
    dialogVisible.value = false
    form.value = defaultForm()
    errors.value = {}
}

async function saveRow() {
    if (saving.value) return
    saving.value = true
    errors.value = {}

    const payload = { ...form.value }
    const request = form.value.id
        ? axios.put(route('panel.device-categories.update', form.value.id), payload)
        : axios.post(route('panel.device-categories.store'), payload)

    try {
        await request
        dialogVisible.value = false
        form.value = defaultForm()
        $q.notify({ type: 'positive', message: 'Dados salvos com sucesso!' })
        await fetchRows({ pagination: pagination.value })
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
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
            title: 'Excluir categoria de dispositivo',
            message: 'Esta ação é permanente. Deseja realmente remover?',
            okLabel: 'Remover',
            okColor: 'negative',
        })
    } else if (typeof window !== 'undefined') {
        confirmed = window.confirm('Excluir esta categoria de dispositivo?')
    }

    if (!confirmed) return

    loading.value = true
    try {
        await axios.delete(route('panel.device-categories.destroy', id))
        $q.notify({ type: 'positive', message: 'Categoria removida.' })
        await fetchRows({ pagination: pagination.value })
    } catch (error) {
        $q.notify({ type: 'negative', message: 'Falha ao remover a categoria.' })
    } finally {
        loading.value = false
    }
}

watch(search, () => {
    triggerSearch()
})

onMounted(() => {
    fetchRows({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Categorias de Dispositivo" />
    <div class="tw-space-y-3">
        <q-card flat bordered class="tw-rounded-2xl tw-p-4">
            <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-mb-4">
                <div class="tw-flex tw-gap-2">
                    <q-btn color="positive" unelevated icon="add" label="Nova Categoria" @click="openCreate" />
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
                <template #body-cell-description="props">
                    <q-td :props="props">{{ props.value || '-' }}</q-td>
                </template>
                <template #body-cell-is_system="props">
                    <q-td :props="props">
                        <q-badge :color="props.row.is_system ? 'primary' : 'grey-6'" :label="props.row.is_system ? 'Sistema' : 'Customizado'" />
                    </q-td>
                </template>
                <template #body-cell-created_at="props">
                    <q-td :props="props">{{ formatDate(props.value) }}</q-td>
                </template>
                <template #body-cell-actions="props">
                    <q-td :props="props" class="tw-text-right">
                        <q-btn dense flat icon="edit" @click="openEdit(props.row)" />
                        <q-btn dense flat icon="delete" color="negative" @click="deleteRow(props.row.id)" />
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </div>

    <q-dialog v-model="dialogVisible" persistent>
        <q-card class="tw-w-full tw-max-w-2xl">
            <q-card-section>
                <div class="text-h6">{{ isEditing ? 'Editar categoria' : 'Nova categoria' }}</div>
            </q-card-section>
            <q-separator />
            <q-card-section class="tw-space-y-3">
                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                    <q-input v-model="form.name" label="Nome" dense outlined :error="Boolean(errors.name)" :error-message="errors.name?.[0]" />
                    <q-input v-model="form.slug" label="Slug" dense outlined :error="Boolean(errors.slug)" :error-message="errors.slug?.[0]" />
                </div>
                <q-input v-model="form.description" type="textarea" autogrow label="Descrição" dense outlined :error="Boolean(errors.description)" :error-message="errors.description?.[0]" />
                <q-toggle v-model="form.is_system" label="Categoria de sistema" />
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="closeDialog" />
                <q-btn color="primary" unelevated :loading="saving" :label="isEditing ? 'Atualizar' : 'Cadastrar'" @click="saveRow" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

