<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import {Head} from "@inertiajs/vue3";

const rows = ref([])
const loading = ref(false)
const tableRef = ref(null)

const pagination = ref({
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: 'created_at',
    descending: true
})

const campaignCode = ref(null)
const campaigns = ref([])

const columns = [
    { name: 'created_at', label: 'Data', field: 'created_at', sortable: true, align: 'left' },
    { name: 'campaign_name', label: 'Campanha', field: 'campaign_name', sortable: true, align: 'left' },
    { name: 'campaign_code', label: 'Cód/Campanha', field: 'campaign_code', sortable: true, align: 'left' },
    { name: 'url', label: 'URL', field: 'url', sortable: true, align: 'left' },
    { name: 'ip', label: 'IP', field: 'ip', sortable: true, align: 'left' },
    { name: 'conversion', label: 'Conversão', field: 'conversion', sortable: true, align: 'left' }
]

function formatDateBR(date) {
    if (!date) return '-'
    return new Date(date).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'medium'
    })
}

function fetchCampaigns() {
    axios.get(route('panel.atividade.campaigns')).then(res => {
        campaigns.value = res.data
    })
}

function fetchPageviews(props) {
    loading.value = true

    const {
        page,
        rowsPerPage,
        sortBy,
        descending
    } = props.pagination

    axios.get(route('panel.atividade.pageviews.data'), {
        params: {
            page,
            per_page: rowsPerPage,
            sortBy,
            descending,
            campaign_code: campaignCode.value
        }
    })
        .then(res => {
            rows.value = res.data.data

            // 🔥 ESSENCIAL: devolver exatamente o estado esperado
            pagination.value = {
                ...pagination.value,
                page: res.data.current_page,
                rowsPerPage: res.data.per_page,
                rowsNumber: res.data.total,
                sortBy,
                descending
            }
        })
        .finally(() => {
            loading.value = false
        })
}


watch(campaignCode, () => {
    // volta para primeira página ao trocar campanha
    pagination.value.page = 1
    // chama manualmente usando a paginação atual
    fetchPageviews({ pagination: pagination.value })
})

onMounted(() => {
    fetchCampaigns()
    // chama manualmente usando a paginação atual
    fetchPageviews({ pagination: pagination.value })
})
</script>

<template>
    <Head title="Relatório de atividade" />
    <q-card flat bordered class="tw-rounded-2xl tw-p-4">
        <div class="row q-mb-md items-center">
            <div class="col-12 col-md-4">
                <q-select
                    ref="tableRef"
                    v-model="campaignCode"
                    :options="campaigns"
                    option-label="name"
                    option-value="code"
                    emit-value
                    map-options
                    clearable
                    label="Filtrar por campanha"
                />
            </div>
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            :loading="loading"
            v-model:pagination="pagination"
            :binary-state-sort="true"
            @request="fetchPageviews"
        >

        <template #body-cell-created_at="props">
                <q-td :props="props">
                    {{ formatDateBR(props.value) }}
                </q-td>
            </template>

            <template #body-cell-conversion="props">
                <q-td :props="props">
                    <q-badge
                        v-if="props.value"
                        color="green"
                        label="Convertido"
                    />
                    <q-badge
                        v-else
                        color="grey-4"
                        text-color="dark"
                        label="Não convertido"
                    />
                </q-td>
            </template>
        </q-table>
    </q-card>
</template>
