<script setup>
/**
 * IndexTable.vue — Template genérico de listagem para módulos (Quasar + Inertia)
 *
 * Props obrigatórias:
 * - title: string (ex.: 'Gerenciar usuários')
 * - collectionKey: string (ex.: 'users')  => nome da prop com paginator vinda do backend
 * - columns: Quasar columns[]
 * - routes: {
 *     index: string           // nome da rota index (ex.: 'panel.users.index')
 *     create: string          // nome da rota create (ex.: 'panel.users.create')
 *     edit: string            // nome da rota edit   (ex.: 'panel.users.edit')
 *     destroy: string         // nome da rota destroy (ex.: 'panel.users.destroy')
 *     bulkDestroy?: string    // nome da rota bulk   (ex.: 'panel.users.bulk-destroy')
 *   }
 *
 * Backend esperado:
 * - Filtros: page, per_page, sort, direction, search
 * - Retorno: { [collectionKey]: LengthAwarePaginator, filters: {...} }
 */

import { ref, watch, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { qTableLangPt } from '@/lang/qtable-pt'
import { useQuasar } from 'quasar'
const { screen } = useQuasar()

const props = defineProps({
    title: { type: String, required: true },
    collectionKey: { type: String, required: true },
    enableSelection: { type: Boolean, default: true },
    columns: { type: Array, required: true },
    rowsPerPageOptions:{
        type: Array, default: [5,10,15,25,50]
    },
    routes: {
        type: Object,
        required: true,
        // { index, create, edit, destroy, bulkDestroy? }
    },
    // largura do campo de busca (classe tailwind)
    searchWidthClass: { type: String, default: 'md:tw-w-1/3' },
    // rótulos
    labels: {
        type: Object,
        default: () => ({
            create: 'Novo',
            deleteOne:'Excluir este item',
            bulkDeleteSingular: 'Excluir selecionado',
            bulkDeletePlural: 'Excluir registros selecionados',
            searchPlaceholder: 'Pesquisar...',
        })
    }
})

const page = usePage()
const paginator = computed(() => page.props[props.collectionKey] ?? {})
const rows = computed(() => paginator.value?.data ?? [])
const filters = computed(() => page.props.filters ?? {})

const pagination = ref({
    page: paginator.value?.current_page ?? 1,
    rowsPerPage: paginator.value?.per_page ?? 10,
    rowsNumber: paginator.value?.total ?? 0,
    sortBy: filters.value?.sort ?? 'id',
    descending: (filters.value?.direction ?? 'desc') === 'desc',
})

const search = ref(filters.value?.search ?? '')
const selected = ref([])
const selectedIds = computed(() => selected.value.map(r => r.id))

watch(() => paginator.value, (val) => {
    if (!val) return
    Object.assign(pagination.value, {
        page: val.current_page,
        rowsPerPage: val.per_page,
        rowsNumber: val.total,
    })
})

function fetchTable () {
    const params = {
        page: pagination.value.page,
        per_page: pagination.value.rowsPerPage,
        sort: pagination.value.sortBy,
        direction: pagination.value.descending ? 'desc' : 'asc',
        search: search.value || undefined,
    }
    router.get(route(props.routes.index), params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function onRequest ({ pagination: p }) {
    Object.assign(pagination.value, p) // mutar, não substituir
    fetchTable()
}
function onSearch () {
    pagination.value.page = 1
    fetchTable()
}

async function destroyOne (id) {
    if (!id) return
    const ok = await $confirm({
        title: props.labels.deleteOne,
        message: 'Esta ação é permanente. Deseja realmente remover?',
        okLabel: 'Remover',
        okColor: 'negative',
    })
    if (!ok) return
    router.delete(route(props.routes.destroy, id), { preserveScroll: true })
}

async function bulkDelete () {
    if (!props.routes.bulkDestroy || selectedIds.value.length === 0) return
    const ok = await $confirm({
        title: `${(selectedIds.value.length > 1)? props.labels.bulkDeletePlural:props.labels.bulkDeleteSingular}!`,
        message: `Remover ${selectedIds.value.length} registro(s)?`,
        okLabel: 'Remover',
        okColor: 'negative',
    })
    if (!ok) return
    router.delete(route(props.routes.bulkDestroy), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => { selected.value = [] }
    })
}

//Tradução

/*
const qTableLangPt = {
    noData: 'Nenhum registro encontrado',
    noResults: 'Nenhum resultado corresponde à busca',
    loading: 'Carregando...',
    selectedRecords: (rows) => (rows === 1 ? '1 item selecionado' : `${rows} itens selecionados`),
    recordsPerPage: 'Registros por página:',
    allRows: 'Todos',
    pagination: (start, end, total) => `${start}-${end} de ${total}`,
    columns: 'Colunas',
    sort: 'Ordenar',
    filter: 'Filtrar',
    firstPage: 'Primeira página',
    lastPage: 'Última página',
    nextPage: 'Próxima página',
    previousPage: 'Página anterior',
    removeFilter: 'Remover filtro',
}
*/
</script>

<template>
    <Head :title="title" />
 <div class="tw-space-y-3">
    <q-card flat bordered class="tw-rounded-2xl tw-p-4">

        <!-- Toolbar -->
        <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-3 tw-mb-3">
            <!-- Esquerda: botões -->
            <!-- Linha 1 (sempre fica antes no mobile) -->
            <div class="tw-flex tw-items-center tw-gap-2">
                <Link :href="route(routes.create)">
                    <q-btn color="positive" unelevated icon="add" :label="labels.create"
                           class="tw-w-auto" />
                </Link>
                <template v-if="props.enableSelection">
                    <q-btn
                        v-if="routes.bulkDestroy && selected.length > 0"
                        color="negative"
                        unelevated
                        icon="delete"
                        :disable="selected.length===0"
                        label="Excluir"
                        @click="bulkDelete"
                    />
                </template>
            </div>

            <!-- Direita: busca -->
            <q-input
                v-model="search"
                dense
                outlined
                clearable
                :placeholder="labels.searchPlaceholder"
                class="tw-w-full md:tw-w-1/3 md:tw-ml-auto"
                :class="searchWidthClass"
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
            v-model:pagination="pagination"
            :rows="rows"
            :columns="columns"
            :rows-per-page-options="props.rowsPerPageOptions"
            :selection="props.enableSelection ? 'multiple' : 'none'"
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
            <!-- FORWARD de TODOS os slots recebidos por IndexTable -->
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps" />
            </template>
        </q-table>
    </q-card>
    </div>
</template>
