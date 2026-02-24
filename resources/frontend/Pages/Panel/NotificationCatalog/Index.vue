<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const loading = ref(false)
const categories = ref([])
const severityOptions = ref(['info', 'success', 'warning', 'error'])
const selectedCategoryId = ref(null)

const categoryDialog = ref(false)
const typeDialog = ref(false)
const savingCategory = ref(false)
const savingType = ref(false)
const categoryErrors = ref({})
const typeErrors = ref({})

const categoryForm = ref(defaultCategoryForm())
const typeForm = ref(defaultTypeForm())

const categoryColumns = [
    { name: 'name', label: 'Categoria', field: 'name', sortable: true, align: 'left' },
    { name: 'slug', label: 'Slug', field: 'slug', sortable: true, align: 'left' },
    { name: 'active', label: 'Status', field: 'active', sortable: true, align: 'left' },
    { name: 'sort_order', label: 'Ordem', field: 'sort_order', sortable: true, align: 'left' },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

const typeColumns = [
    { name: 'name', label: 'Tipo', field: 'name', sortable: true, align: 'left' },
    { name: 'category_name', label: 'Categoria', field: 'category_name', sortable: true, align: 'left' },
    { name: 'slug', label: 'Slug', field: 'slug', sortable: true, align: 'left' },
    { name: 'severity', label: 'Severidade', field: 'severity', sortable: true, align: 'left' },
    { name: 'active', label: 'Status', field: 'active', sortable: true, align: 'left' },
    { name: 'sort_order', label: 'Ordem', field: 'sort_order', sortable: true, align: 'left' },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

const selectedCategory = computed(() =>
    categories.value.find(item => Number(item.id) === Number(selectedCategoryId.value)) || null
)

const categoryFilterOptions = computed(() => ([
    { id: 0, name: 'Todas as categorias' },
    ...categories.value.map(item => ({ id: item.id, name: item.name })),
]))

const typeRows = computed(() => {
    if (Number(selectedCategoryId.value) === 0) {
        return categories.value.flatMap(category =>
            (category.types || []).map(type => ({
                ...type,
                category_name: category.name,
            }))
        )
    }

    const category = categories.value.find(item => Number(item.id) === Number(selectedCategoryId.value))
    if (!category) return []

    return (category.types || []).map(type => ({
        ...type,
        category_name: category.name,
    }))
})

function defaultCategoryForm() {
    return {
        id: null,
        name: '',
        slug: '',
        description: '',
        active: true,
        sort_order: 0,
    }
}

function defaultTypeForm() {
    return {
        id: null,
        notification_category_id: null,
        name: '',
        slug: '',
        description: '',
        default_title: '',
        default_message: '',
        severity: 'info',
        active: true,
        sort_order: 0,
    }
}

function normalizeCategory(item) {
    return {
        ...item,
        active: Boolean(item.active),
        types: Array.isArray(item.types)
            ? item.types.map(type => ({ ...type, active: Boolean(type.active) }))
            : [],
    }
}

async function fetchData() {
    loading.value = true
    try {
        const { data } = await axios.get(route('panel.notification-catalog.data'))
        categories.value = (data.categories || []).map(normalizeCategory)
        severityOptions.value = Array.isArray(data.severity_options) && data.severity_options.length > 0
            ? data.severity_options
            : ['info', 'success', 'warning', 'error']

        if (!selectedCategoryId.value && categories.value.length > 0) {
            selectedCategoryId.value = categories.value[0].id
        }

        if (
            selectedCategoryId.value &&
            Number(selectedCategoryId.value) !== 0 &&
            !categories.value.some(item => Number(item.id) === Number(selectedCategoryId.value))
        ) {
            selectedCategoryId.value = categories.value[0]?.id || null
        }
    } finally {
        loading.value = false
    }
}

function openCreateCategory() {
    categoryForm.value = defaultCategoryForm()
    categoryErrors.value = {}
    categoryDialog.value = true
}

function openEditCategory(row) {
    categoryForm.value = {
        id: row.id,
        name: row.name || '',
        slug: row.slug || '',
        description: row.description || '',
        active: Boolean(row.active),
        sort_order: Number(row.sort_order || 0),
    }
    categoryErrors.value = {}
    categoryDialog.value = true
}

async function saveCategory() {
    if (savingCategory.value) return
    savingCategory.value = true
    categoryErrors.value = {}

    const payload = {
        name: String(categoryForm.value.name || '').trim(),
        slug: String(categoryForm.value.slug || '').trim().toLowerCase(),
        description: String(categoryForm.value.description || '').trim() || null,
        active: Boolean(categoryForm.value.active),
        sort_order: Number(categoryForm.value.sort_order || 0),
    }

    try {
        if (categoryForm.value.id) {
            await axios.put(route('panel.notification-catalog.categories.update', categoryForm.value.id), payload)
            $q.notify({ type: 'positive', message: 'Categoria atualizada com sucesso.' })
        } else {
            await axios.post(route('panel.notification-catalog.categories.store'), payload)
            $q.notify({ type: 'positive', message: 'Categoria cadastrada com sucesso.' })
        }

        categoryDialog.value = false
        await fetchData()
    } catch (error) {
        if (error.response?.status === 422) {
            categoryErrors.value = error.response.data.errors || {}
            return
        }
        $q.notify({ type: 'negative', message: 'Não foi possível salvar a categoria.' })
    } finally {
        savingCategory.value = false
    }
}

async function removeCategory(row) {
    const confirmed = typeof window !== 'undefined'
        ? window.confirm(`Excluir categoria "${row.name}"?`)
        : true
    if (!confirmed) return

    try {
        await axios.delete(route('panel.notification-catalog.categories.destroy', row.id))
        $q.notify({ type: 'positive', message: 'Categoria removida com sucesso.' })
        await fetchData()
    } catch (error) {
        $q.notify({
            type: 'warning',
            message: error?.response?.data?.message || 'Não foi possível remover a categoria.',
        })
    }
}

function openCreateType() {
    const defaultCategoryId = Number(selectedCategoryId.value) === 0
        ? Number(categories.value[0]?.id || 0)
        : Number(selectedCategoryId.value || 0)

    typeForm.value = {
        ...defaultTypeForm(),
        notification_category_id: defaultCategoryId || null,
    }
    typeErrors.value = {}
    typeDialog.value = true
}

function openEditType(row) {
    typeForm.value = {
        id: row.id,
        notification_category_id: Number(row.notification_category_id),
        name: row.name || '',
        slug: row.slug || '',
        description: row.description || '',
        default_title: row.default_title || '',
        default_message: row.default_message || '',
        severity: row.severity || 'info',
        active: Boolean(row.active),
        sort_order: Number(row.sort_order || 0),
    }
    typeErrors.value = {}
    typeDialog.value = true
}

async function saveType() {
    if (savingType.value) return
    savingType.value = true
    typeErrors.value = {}

    const payload = {
        notification_category_id: Number(typeForm.value.notification_category_id || 0),
        name: String(typeForm.value.name || '').trim(),
        slug: String(typeForm.value.slug || '').trim().toLowerCase(),
        description: String(typeForm.value.description || '').trim() || null,
        default_title: String(typeForm.value.default_title || '').trim() || null,
        default_message: String(typeForm.value.default_message || '').trim() || null,
        severity: String(typeForm.value.severity || 'info').trim().toLowerCase(),
        active: Boolean(typeForm.value.active),
        sort_order: Number(typeForm.value.sort_order || 0),
    }

    try {
        if (typeForm.value.id) {
            await axios.put(route('panel.notification-catalog.types.update', typeForm.value.id), payload)
            $q.notify({ type: 'positive', message: 'Tipo de notificação atualizado com sucesso.' })
        } else {
            await axios.post(route('panel.notification-catalog.types.store'), payload)
            $q.notify({ type: 'positive', message: 'Tipo de notificação cadastrado com sucesso.' })
        }

        typeDialog.value = false
        await fetchData()
    } catch (error) {
        if (error.response?.status === 422) {
            typeErrors.value = error.response.data.errors || {}
            return
        }
        $q.notify({ type: 'negative', message: 'Não foi possível salvar o tipo de notificação.' })
    } finally {
        savingType.value = false
    }
}

async function removeType(row) {
    const confirmed = typeof window !== 'undefined'
        ? window.confirm(`Excluir tipo "${row.name}"?`)
        : true
    if (!confirmed) return

    try {
        await axios.delete(route('panel.notification-catalog.types.destroy', row.id))
        $q.notify({ type: 'positive', message: 'Tipo removido com sucesso.' })
        await fetchData()
    } catch {
        $q.notify({ type: 'negative', message: 'Não foi possível remover o tipo.' })
    }
}

onMounted(fetchData)
</script>

<template>
    <Head title="Catálogo de notificações" />
    <q-card flat bordered class="tw-rounded-2xl tw-p-4 tw-space-y-4">
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-12 tw-gap-4">
            <q-card flat bordered class="tw-rounded-xl tw-p-3 lg:tw-col-span-4">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                    <div class="tw-text-base tw-font-semibold">Categorias</div>
                    <q-btn color="positive" dense icon="add" label="Categoria" @click="openCreateCategory" />
                </div>

                <q-table
                    :rows="categories"
                    :columns="categoryColumns"
                    row-key="id"
                    :loading="loading"
                    flat
                    :pagination="{ rowsPerPage: 10 }"
                    @row-click="(_, row) => selectedCategoryId = row.id"
                >
                    <template #body-cell-active="props">
                        <q-td :props="props">
                            <q-badge :color="props.row.active ? 'positive' : 'grey-6'" :label="props.row.active ? 'Ativa' : 'Inativa'" />
                        </q-td>
                    </template>
                    <template #body-cell-actions="props">
                        <q-td :props="props" class="tw-text-right">
                            <q-btn dense flat size="sm" icon="edit" class="qtable-edit-btn" @click.stop="openEditCategory(props.row)" />
                            <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click.stop="removeCategory(props.row)" />
                        </q-td>
                    </template>
                </q-table>
            </q-card>

            <q-card flat bordered class="tw-rounded-xl tw-p-3 lg:tw-col-span-8">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <div class="tw-text-base tw-font-semibold">Tipos</div>
                        <q-select
                            v-model="selectedCategoryId"
                            :options="categoryFilterOptions"
                            option-label="name"
                            option-value="id"
                            emit-value
                            map-options
                            dense
                            outlined
                            style="min-width: 230px"
                        />
                    </div>
                    <div class="tw-text-sm tw-text-slate-500">
                        <span v-if="Number(selectedCategoryId) === 0">Mostrando todos</span>
                        <span v-else-if="selectedCategory">Mostrando: {{ selectedCategory.name }}</span>
                        <span v-else>Selecione uma categoria</span>
                    </div>
                    <q-btn
                        color="primary"
                        dense
                        icon="add"
                        label="Tipo"
                        :disable="!selectedCategoryId"
                        @click="openCreateType"
                    />
                </div>

                <q-table
                    :rows="typeRows"
                    :columns="typeColumns"
                    row-key="id"
                    :loading="loading"
                    flat
                    :pagination="{ rowsPerPage: 10 }"
                >
                    <template #body-cell-severity="props">
                        <q-td :props="props">
                            <q-badge :label="props.row.severity" :color="props.row.severity === 'error' ? 'negative' : props.row.severity === 'warning' ? 'warning' : props.row.severity === 'success' ? 'positive' : 'primary'" />
                        </q-td>
                    </template>
                    <template #body-cell-active="props">
                        <q-td :props="props">
                            <q-badge :color="props.row.active ? 'positive' : 'grey-6'" :label="props.row.active ? 'Ativo' : 'Inativo'" />
                        </q-td>
                    </template>
                    <template #body-cell-actions="props">
                        <q-td :props="props" class="tw-text-right">
                            <q-btn dense flat size="sm" icon="edit" class="qtable-edit-btn" @click="openEditType(props.row)" />
                            <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="removeType(props.row)" />
                        </q-td>
                    </template>
                </q-table>
            </q-card>
        </div>
    </q-card>

    <q-dialog v-model="categoryDialog" persistent>
        <q-card class="tw-w-full tw-max-w-xl">
            <q-card-section><div class="text-h6">{{ categoryForm.id ? 'Editar categoria' : 'Nova categoria' }}</div></q-card-section>
            <q-separator />
            <q-card-section class="tw-space-y-3">
                <q-input v-model="categoryForm.name" label="Nome" outlined dense :error="Boolean(categoryErrors.name)" :error-message="categoryErrors.name?.[0]" />
                <q-input v-model="categoryForm.slug" label="Slug" outlined dense :error="Boolean(categoryErrors.slug)" :error-message="categoryErrors.slug?.[0]" />
                <q-input v-model="categoryForm.description" label="Descrição" outlined dense :error="Boolean(categoryErrors.description)" :error-message="categoryErrors.description?.[0]" />
                <q-input v-model.number="categoryForm.sort_order" type="number" min="0" label="Ordem" outlined dense :error="Boolean(categoryErrors.sort_order)" :error-message="categoryErrors.sort_order?.[0]" />
                <q-toggle v-model="categoryForm.active" label="Categoria ativa" />
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="categoryDialog = false" />
                <q-btn color="primary" unelevated :loading="savingCategory" label="Salvar" @click="saveCategory" />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-dialog v-model="typeDialog" persistent>
        <q-card class="tw-w-full tw-max-w-2xl">
            <q-card-section><div class="text-h6">{{ typeForm.id ? 'Editar tipo' : 'Novo tipo' }}</div></q-card-section>
            <q-separator />
            <q-card-section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                <q-select
                    v-model="typeForm.notification_category_id"
                    :options="categories"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    label="Categoria"
                    outlined
                    dense
                    :error="Boolean(typeErrors.notification_category_id)"
                    :error-message="typeErrors.notification_category_id?.[0]"
                    class="md:tw-col-span-2"
                />
                <q-input v-model="typeForm.name" label="Nome" outlined dense :error="Boolean(typeErrors.name)" :error-message="typeErrors.name?.[0]" />
                <q-input v-model="typeForm.slug" label="Slug" outlined dense :error="Boolean(typeErrors.slug)" :error-message="typeErrors.slug?.[0]" />
                <q-select
                    v-model="typeForm.severity"
                    :options="severityOptions"
                    label="Severidade"
                    outlined
                    dense
                    :error="Boolean(typeErrors.severity)"
                    :error-message="typeErrors.severity?.[0]"
                />
                <q-input v-model.number="typeForm.sort_order" type="number" min="0" label="Ordem" outlined dense :error="Boolean(typeErrors.sort_order)" :error-message="typeErrors.sort_order?.[0]" />
                <q-input v-model="typeForm.default_title" label="Título padrão" outlined dense :error="Boolean(typeErrors.default_title)" :error-message="typeErrors.default_title?.[0]" class="md:tw-col-span-2" />
                <q-input v-model="typeForm.default_message" type="textarea" autogrow label="Mensagem padrão" outlined dense :error="Boolean(typeErrors.default_message)" :error-message="typeErrors.default_message?.[0]" class="md:tw-col-span-2" />
                <q-input v-model="typeForm.description" label="Descrição" outlined dense :error="Boolean(typeErrors.description)" :error-message="typeErrors.description?.[0]" class="md:tw-col-span-2" />
                <q-toggle v-model="typeForm.active" label="Tipo ativo" class="md:tw-col-span-2" />
            </q-card-section>
            <q-card-actions align="right" class="tw-gap-2 tw-p-4">
                <q-btn flat label="Cancelar" @click="typeDialog = false" />
                <q-btn color="primary" unelevated :loading="savingType" label="Salvar" @click="saveType" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
