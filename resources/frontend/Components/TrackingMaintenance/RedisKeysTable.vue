<script setup>
import { computed } from 'vue'
import { qTableLangPt } from '@/lang/qtable-pt'

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    pagination: { type: Object, required: true },
    selected: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    emptyLabel: { type: String, default: 'Nenhum registro encontrado.' },
})

const emit = defineEmits([
    'update:pagination',
    'update:selected',
    'update:search',
    'request',
    'view',
    'delete',
    'delete-selected',
    'delete-all',
])

const paginationModel = computed({
    get: () => props.pagination,
    set: (value) => emit('update:pagination', value),
})

const selectedModel = computed({
    get: () => props.selected,
    set: (value) => emit('update:selected', value),
})

const hasSelection = computed(() => props.selected.length > 0)
</script>

<template>
    <q-card flat bordered class="table-card">
        <q-card-section class="tw-space-y-4">
            <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-3">
                <div>
                    <div class="tw-text-lg tw-font-semibold">{{ props.title }}</div>
                    <div v-if="props.description" class="tw-text-sm tw-text-slate-500">
                        {{ props.description }}
                    </div>
                </div>

                <div class="tw-flex tw-flex-col sm:tw-flex-row tw-gap-2 sm:tw-items-center">
                    <q-input
                        :model-value="props.search"
                        dense
                        outlined
                        clearable
                        placeholder="Buscar"
                        class="table-search"
                        @update:model-value="value => emit('update:search', value || '')"
                    >
                        <template #append>
                            <q-icon name="search" />
                        </template>
                    </q-input>

                    <q-btn
                        dense
                        outline
                        color="negative"
                        icon="delete_sweep"
                        label="Selecionados"
                        :disable="!hasSelection"
                        @click="emit('delete-selected')"
                    />

                    <q-btn
                        dense
                        flat
                        color="negative"
                        icon="delete_forever"
                        label="Remover todos"
                        @click="emit('delete-all')"
                    />
                </div>
            </div>

            <q-table
                :rows="props.rows"
                :columns="props.columns"
                row-key="cache_id"
                flat
                binary-state-sort
                selection="multiple"
                :loading="props.loading"
                v-model:pagination="paginationModel"
                v-model:selected="selectedModel"
                :pagination="props.pagination"
                :rows-per-page-options="[10, 15, 25, 50]"
                :no-data-label="props.emptyLabel"
                :no-results-label="qTableLangPt.noResults"
                :loading-label="qTableLangPt.loading"
                :rows-per-page-label="qTableLangPt.recordsPerPage"
                :all-rows-label="qTableLangPt.allRows"
                :pagination-label="qTableLangPt.pagination"
                @request="payload => emit('request', payload)"
            >
                <template #body-cell-actions="slotProps">
                    <q-td :props="slotProps" class="tw-text-right">
                        <q-btn
                            flat
                            round
                            dense
                            icon="visibility"
                            color="primary"
                            @click="emit('view', slotProps.row)"
                        >
                            <q-tooltip>Visualizar payload</q-tooltip>
                        </q-btn>
                        <q-btn
                            flat
                            round
                            dense
                            icon="delete"
                            color="negative"
                            @click="emit('delete', slotProps.row)"
                        >
                            <q-tooltip>Remover chave</q-tooltip>
                        </q-btn>
                    </q-td>
                </template>

                <template
                    v-for="column in props.columns.filter(item => item.name !== 'actions')"
                    :key="column.name"
                    #[`body-cell-${column.name}`]="slotProps"
                >
                    <slot :name="`body-cell-${column.name}`" v-bind="slotProps">
                        <q-td :props="slotProps">
                            {{ slotProps.value ?? '-' }}
                        </q-td>
                    </slot>
                </template>
            </q-table>
        </q-card-section>
    </q-card>
</template>

<style scoped>
.table-card {
    border-radius: 20px;
}

.table-search {
    min-width: 220px;
}
</style>
