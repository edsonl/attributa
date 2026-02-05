<template>
    <q-page padding>
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
            <div class="tw-text-xl tw-font-semibold">Clientes</div>
            <div class="tw-flex tw-gap-2">
                <q-input v-model="search" outlined dense debounce="400" placeholder="Buscar por nome/website" @update:model-value="applyFilters">
                    <template #append>
                        <q-btn flat dense icon="search" @click="applyFilters" />
                    </template>
                </q-input>
                <q-select v-model="perPage" :options="perPageOptions" outlined dense style="width: 120px" @update:model-value="applyFilters" label="Registros" />
                <q-btn color="primary" @click="()=>router.visit(route('panel.clients.create'))" label="Novo" icon="add" dense unelevated />
            </div>
        </div>

        <q-table
            flat
            separator="cell"
            row-key="id"
            :rows="clients.data"
            :columns="columns"
            :loading="loading"
            selection="multiple"
            v-model:selected="selected"
            :pagination="pagination"
            @request="onRequest"
        >
            <!-- Thumb 100x100 -->
            <template #body-cell-image_url="props">
                <q-td :props="props">
                    <div class="tw-w-[100px] tw-h-[100px] tw-rounded tw-overflow-hidden tw-bg-gray-100 tw-flex tw-items-center tw-justify-center">
                        <img v-if="props.row.image_url" :src="props.row.image_url" alt="thumb" class="tw-w-full tw-h-full tw-object-cover" />
                        <q-icon v-else name="image" />
                    </div>
                </q-td>
            </template>

            <!-- Visível: já vem do backend como visible_label (Sim/Não) -->
            <template #body-cell-visible_label="props">
                <q-td :props="props">
                    <q-badge :color="props.row.visible ? 'green' : 'grey'" outline>
                        {{ props.row.visible_label }}
                    </q-badge>
                </q-td>
            </template>

            <template #top-right>
                <div class="tw-flex tw-gap-2">
                    <q-btn v-if="selected.length" color="negative" flat dense icon="delete" label="Excluir selecionados" @click="destroyMany" />
                </div>
            </template>

            <template #body-cell-actions="props">
                <q-td :props="props" class="tw-text-right">
                    <Link :href="route('panel.clients.edit', props.row.id)">
                        <q-btn dense flat icon="edit" />
                    </Link>
                    <q-btn dense flat icon="delete" color="negative" @click="destroyOne(props.row.id)" />
                </q-td>
            </template>

            <template #bottom>
                <div class="tw-w-full tw-flex tw-justify-end">
                    <q-pagination
                        v-model="page"
                        :max="clients.last_page"
                        max-pages="10"
                        direction-links
                        @update:model-value="goPage"
                    />
                </div>
            </template>
        </q-table>
    </q-page>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    clients: Object,
    filters: Object,
    meta: Object,
})

// ===== State inicial (do backend) =====
const page      = ref(props.clients.current_page)
const perPage   = ref(props.filters?.per_page ?? 10)
const search    = ref(props.filters?.search ?? '')
const sortBy    = ref(props.filters?.sort ?? 'id')
const direction = ref(props.filters?.direction ?? 'desc')
const loading   = ref(false)

const perPageOptions = [10, 15, 25, 50, 100]

// QTable pagination objeto controlado
const pagination = computed(() => ({
    page: page.value,
    rowsPerPage: Number(perPage.value),
    sortBy: sortBy.value,
    descending: direction.value !== 'asc',
}))

// ===== Columns =====
const columns = [
    { name: 'image',          label: 'Imagem',         field: 'image_url',     align: 'left',   sortable: false },
    { name: 'name',           label: 'Nome',           field: 'name',          align: 'left',   sortable: true  },
    { name: 'website',        label: 'Website',        field: 'website',       align: 'left',   sortable: true  },
    { name: 'order',          label: 'Ordem',          field: 'order',         align: 'left',   sortable: true  },
    { name: 'visible_label',  label: 'Visível no site',field: 'visible_label', align: 'center', sortable: true  },
    { name: 'actions',        label: 'Ações',          field: 'id',            align: 'right',  sortable: false },
]

// Seleção
const selected = ref([])

// ===== Navegação server-side =====
function fetch(params = {}) {
    loading.value = true
    router.get(route('panel.clients.index'), {
        page: params.page ?? page.value,
        per_page: params.per_page ?? perPage.value,
        search: params.search ?? search.value,
        sort: params.sort ?? sortBy.value,
        direction: params.direction ?? direction.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { loading.value = false }
    })
}

function applyFilters() {
    page.value = 1
    fetch({})
}

function goPage(val) {
    page.value = val
    fetch({})
}

function onRequest(ctx) {
    // ctx.pagination: { page, rowsPerPage, sortBy, descending }
    const p = ctx.pagination
    page.value = p.page
    perPage.value = p.rowsPerPage
    if (p.sortBy) {
        sortBy.value = p.sortBy
        direction.value = p.descending ? 'desc' : 'asc'
    }
    fetch({})
}

// ===== Ações =====
function destroyOne(id) {
    if (window.confirm('Remover este cliente?')) {
        router.delete(route('panel.clients.destroy', id), {
            preserveScroll: true,
            onSuccess: () => fetch({}),
        })
    }
}

function destroyMany() {
    if (!selected.value.length) return
    if (window.confirm(`Remover ${selected.value.length} registro(s)?`)) {
        // Se você tiver uma rota bulk, use-a. Caso contrário, faz loop.
        const ids = selected.value.map(r => r.id)
        // Exemplo simples: deleta em série
        const next = () => {
            const id = ids.shift()
            if (!id) { selected.value = []; fetch({}); return }
            router.delete(route('panel.clients.destroy', id), {
                preserveScroll: true,
                onSuccess: next,
                onError: next,
            })
        }
        next()
    }
}


</script>
