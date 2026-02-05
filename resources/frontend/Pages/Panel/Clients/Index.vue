<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { qTableLangPt } from '@/lang/qtable-pt'

const page = usePage()
const items    = computed(() => page.props.items)
const filters  = computed(() => page.props.filters)
const rows     = computed(() => items.value?.data ?? [])

const pagination = ref({
    page: items.value?.current_page ?? 1,
    rowsPerPage: items.value?.per_page ?? 10,
    rowsNumber: items.value?.total ?? 0,
    sortBy: filters.value?.sort ?? 'id',
    descending: (filters.value?.direction ?? 'desc') === 'desc',
})

const search = ref(filters.value?.search ?? '')
const selected = ref([]) // ← seleção múltipla
const selectedIds = computed(() => selected.value.map(r => r.id))


watch(() => items.value, (val) => {
    pagination.value.page = val?.current_page ?? 1
    pagination.value.rowsPerPage = val?.per_page ?? 10
    pagination.value.rowsNumber = val?.total ?? 0
})

watch(() => items.value, (val) => {
    if (!val) return
    Object.assign(pagination.value, {
        page: val.current_page,
        rowsPerPage: val.per_page,
        rowsNumber: val.total,
    })
    // NÃO toque em sortBy/descending aqui
})


// navegação da tabela
function fetchTable () {
    const params = {
        page: pagination.value.page,
        per_page: pagination.value.rowsPerPage,
        sort: pagination.value.sortBy,
        direction: pagination.value.descending ? 'desc' : 'asc', // <- chave
        search: search.value || undefined,
    }

    router.get(route('panel.clients.index'), params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function onRequest ({ pagination: p }) {
    Object.assign(pagination.value, p)   // mantém a mesma ref
    fetchTable()
}

function onSearch() {
    pagination.value.page = 1
    fetchTable()
}

// delete individual (mantém)
async function deleteItem(id) {
    if (!id) return
    const ok = await $confirm({
        title: 'Remover cliente',
        message: 'Esta ação é permanente. Deseja realmente remover?',
        okLabel: 'Remover',
        okColor: 'negative',
    })
    if (!ok) return
    router.delete(route('panel.clients.destroy', id), { preserveScroll: true })
}

// bulk delete
async function bulkDelete() {
    if (selectedIds.value.length === 0) return
    const ok = await $confirm({
        title: 'Excluir selecionados',
        message: `Remover ${selectedIds.value.length} registro(s)?`,
        okLabel: 'Remover',
        okColor: 'negative',
    })
    if (!ok) return
    router.delete(route('panel.clients.bulk-delete'), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => { selected.value = [] },
    })
}

const columns = [
    { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
    { name: 'image_url', label: 'Imagem', field: 'image_url', align: 'center', sortable: false },
    { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
    { name: 'order', label: 'Ordem', field: 'order', align: 'left', sortable: true },
    { name: 'website', label: 'Site', field: 'website', align: 'left', sortable: true },
    { name: 'visible_label', label: 'Visível', field: 'visible_label', align: 'center', sortable: true },
    { name: 'created_at', label: 'Criado em', field: 'created_at', align: 'left', sortable: true },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

</script>

<template>
    <Head title="Clientes" />
    <div class="tw-space-y-3">
        <q-card flat bordered class="tw-rounded-2xl tw-p-4">
            <!-- Toolbar -->
            <div class="tw-flex tw-items-center tw-gap-3 tw-mb-3">
                <!-- Esquerda: botões -->
                <div class="tw-flex tw-items-center tw-gap-2">
                    <!-- Novo usuário (verde) -->
                    <Link :href="route('panel.clients.create')">
                        <q-btn color="positive" unelevated icon="add" label="Novo Cliente" />
                    </Link>

                    <!-- Excluir selecionados (aparece só quando há seleção) -->
                    <q-btn
                        v-if="selected.length > 0"
                        color="negative"
                        unelevated
                        icon="delete"
                        :label="`Excluir`"
                        @click="bulkDelete"
                    />
                </div>
                <!-- Direita: busca -->
                <q-input
                    v-model="search"
                    dense
                    outlined
                    clearable
                    placeholder="Pesquisar por nome ou e-mail"
                    class="tw-ml-auto tw-w-full md:tw-w-1/3"
                    @keyup.enter="onSearch"
                >
                    <template #append>
                        <q-icon name="search" class="cursor-pointer" @click="onSearch" />
                    </template>
                </q-input>
            </div>

            <!-- Tabela -->
            <q-table
                flat
                bordered
                row-key="id"
                :rows="rows"
                :columns="columns"
                v-model:pagination="pagination"
                :pagination="pagination"
                :rows-per-page-options="[5,10,15,25,50]"
                selection="multiple"
                v-model:selected="selected"
                binary-state-sort
                :no-data-label="qTableLangPt.noData"
                :no-results-label="qTableLangPt.noResults"
                :loading-label="qTableLangPt.loading"
                :rows-per-page-label="qTableLangPt.recordsPerPage"
                :all-rows-label="qTableLangPt.allRows"
                :pagination-label="qTableLangPt.pagination"
                :selected-rows-label="qTableLangPt.selectedRecords"
                @request="onRequest"
            >
                <!-- Visível: já vem do backend como visible_label (Sim/Não) -->
                <template #body-cell-visible_label="props">
                    <q-td :props="props">
                        <q-badge :color="props.row.visible ? 'green' : 'grey'" outline>
                            {{ props.row.visible_label }}
                        </q-badge>
                    </q-td>
                </template>
                <!-- Thumb 100x100 -->
                <template #body-cell-image_url="props">
                    <q-td :props="props">
                        <div class="tw-w-[100px] tw-h-[100px] tw-rounded tw-overflow-hidden tw-bg-gray-100 tw-flex tw-items-center tw-justify-center">
                            <img v-if="props.row.image_url" :src="props.row.image_url" alt="thumb" class="tw-w-full tw-h-full tw-object-cover" />
                            <q-icon v-else name="image" />
                        </div>
                    </q-td>
                </template>
                <template #body-cell-actions="props">
                    <q-td :props="props" class="tw-text-right">
                        <Link :href="route('panel.clients.edit', props.row.id)">
                            <q-btn dense flat icon="edit" />
                        </Link>
                        <q-btn dense flat icon="delete" color="negative" @click="deleteItem(props.row.id)" />
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </div>
</template>
