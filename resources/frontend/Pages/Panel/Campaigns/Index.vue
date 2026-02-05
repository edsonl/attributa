<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import StatusChip from '@/Components/StatusChip.vue'

const props = defineProps({
    campaigns: {
        type: Object,
        required: true,
    },
})

const rows = computed(() => props.campaigns.data)

const columns = [
    {
        name: 'name',
        label: 'Nome',
        field: 'name',
        align: 'left',
        sortable: true,
    },
    {
        name: 'channel',
        label: 'Canal',
        field: row => row.channel?.name ?? '-',
        align: 'left',
    },
    {
        name: 'countries',
        label: 'Países',
        field: 'countries',
        align: 'left',
    },
    {
        name: 'status',
        label: 'Status',
        field: 'status',
        align: 'center',
    },
    {
        name: 'actions',
        label: 'Ações',
        field: 'actions',
        align: 'right',
    },
]

function editCampaign(id) {
    router.visit(route('panel.campaigns.edit', id))
}

function destroyCampaign(campaign) {
    if (!confirm(`Deseja realmente remover a campanha "${campaign.name}"?`)) {
        return
    }

    router.delete(route('panel.campaigns.destroy', campaign.id))
}
</script>

<template>
    <Head title="Campanhas" />

    <div class="tw-space-y-3">
        <!-- Header / ações -->
        <div class="tw-flex tw-items-center tw-justify-between">
            <h1 class="tw-text-lg tw-font-semibold">
                Campanhas
            </h1>

            <Link :href="route('panel.campaigns.create')">
                <q-btn
                    color="primary"
                    icon="add"
                    label="Nova Campanha"
                    unelevated
                />
            </Link>
        </div>

        <!-- Tabela -->
        <q-card flat bordered>
            <q-card-section>
                <q-table
                    flat
                    :rows="rows"
                    :columns="columns"
                    row-key="id"
                    no-data-label="Nenhuma campanha cadastrada"
                >
                    <!-- Países -->
                    <template #body-cell-countries="props">
                        <q-td :props="props">
                            <div class="tw-flex tw-flex-wrap tw-gap-1">
                                <q-chip
                                    v-for="country in props.row.countries"
                                    :key="country.id"
                                    dense
                                    size="sm"
                                >
                                    {{ country.iso2 }}
                                </q-chip>
                            </div>
                        </q-td>
                    </template>

                    <!-- Status -->
                    <template #body-cell-status="props">
                        <q-td :props="props" class="tw-text-center">
                            <StatusChip :value="props.row.status" />
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
                                @click="editCampaign(props.row.id)"
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
</template>
