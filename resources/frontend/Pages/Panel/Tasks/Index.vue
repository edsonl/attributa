<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import StatusChip from './StatusChip.vue'
import PriorityChip from './PriorityChip.vue'
import { qTableLangPt } from '@/lang/qtable-pt'
import { useQuasar } from 'quasar'
const $q = useQuasar()

const props = defineProps({
  tasks: Object,            // paginator do backend
  filters: Object,          // { q, status, priority, sort, direction }
  statusOptions: Array,
  priorityOptions: Array,
  defaultSort: String,      // ex.: 'created_at'
  defaultDirection: String, // 'asc' | 'desc'
})

const q        = ref(props.filters?.q || '')
const status   = ref(props.filters?.status || '')
const priority = ref(props.filters?.priority || '')

const pagination = ref({
  page: props.tasks?.current_page || 1,
  rowsPerPage: props.tasks?.per_page || 10,          // << inicializa com per_page vindo do backend
  sortBy: props.defaultSort || 'created_at',
  descending: (props.defaultDirection || 'desc') === 'desc',
})
const loading = ref(false)

const columns = [
  { name: 'title',        label: 'Título',       field: 'title',               align: 'left',  sortable: true },
  { name: 'description',  label: 'Descrição',    field: 'description_preview', align: 'left'  },
  { name: 'status',       label: 'Status',       field: 'status',              align: 'left',  sortable: true },
  { name: 'total_time_formatted', label: 'Tempo', field: 'total_time_formatted', align: 'center' },
  { name: 'total_value', label: 'Valor', field: 'total_value', align: 'right' },
  //{ name: 'total_value_formatted', label: 'Valor', field: 'total_value_formatted', align: 'right' },
  { name: 'priority',     label: 'Prioridade',   field: 'priority',            align: 'left',  sortable: true },
  { name: 'due_date',     label: 'Entrega',      field: 'due_date',            align: 'left',  sortable: true },
  { name: 'created_at',   label: 'Criado em',    field: 'created_at',          align: 'left',  sortable: true },
  { name: 'company',      label: 'Empresa',      field: row => row.company?.name, align: 'left' , sortable: true },
  { name: 'actions',      label: '',             field: 'id',                  align: 'right' },
]

//  (pending | in_progress | done)
const statusOptions = [
    { value: 'pending',     label: 'Pendente',     color: 'grey-5',   css: 'tw-text-gray-900' },
    { value: 'in_progress', label: 'Em andamento', color: 'warning',  css: 'tw-text-yellow-900' },
    { value: 'done',        label: 'Concluída',    color: 'positive', css: 'tw-text-green-900' },
]

// Prioridade
const priorityOptions = [
    { value: 'low',    label: 'Baixa', color: 'grey-5',   css: 'tw-text-gray-900' },
    { value: 'medium', label: 'Média', color: 'warning',  css: 'tw-text-yellow-900' },
    { value: 'high',   label: 'Alta',  color: 'secondary', css: 'tw-text-white-900' },
]


const rows = computed(() => props.tasks?.data ?? [])
const meta = computed(() => ({
  current_page: props.tasks?.current_page ?? 1,
  last_page:    props.tasks?.last_page ?? 1,
  per_page:     props.tasks?.per_page ?? 10,
  total:        props.tasks?.total ?? 0,
}))

function performGet(params) {
  loading.value = true
  router.get(route('panel.tasks.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onFinish: () => (loading.value = false),
  })
}

function applyFilters(page = 1) {
  performGet({
    q: q.value || undefined,
    status: status.value || undefined,
    priority: priority.value || undefined,
    sort: pagination.value.sortBy,
    direction: pagination.value.descending ? 'desc' : 'asc',
    page,
    per_page: pagination.value.rowsPerPage, // << envia tamanho da página
  })
}

// QTable nativo: ao trocar página/tamanho/ordem, cai aqui
function onRequest({ pagination: p }) {
  pagination.value = { ...pagination.value, ...p }
  performGet({
    q: q.value || undefined,
    status: status.value || undefined,
    priority: priority.value || undefined,
    sort: p.sortBy,
    direction: p.descending ? 'desc' : 'asc',
    page: p.page,
    per_page: p.rowsPerPage, // << respeita seletor “itens por página”
  })
}

async function confirmDelete(id) {
  if (window.$confirm) {
    const ok = await window.$confirm({
      title: 'Excluir tarefa',
      message: 'Esta ação é permanente. Deseja realmente remover?',
      okLabel: 'Remover',
      okColor: 'negative',
    })
    if (!ok) return
  } else if (!confirm('Excluir tarefa?')) return
  router.delete(route('panel.tasks.destroy', id), { preserveScroll: true })
}

// Modal: descrição completa via AJAX
const showDialog = ref(false)
const dialogLoading = ref(false)
const dialogContent = ref('')
const dialogTitle = ref('')

async function openDescription(id, title) {
  dialogTitle.value = title ?? 'Descrição'
  dialogContent.value = ''
  showDialog.value = true
  dialogLoading.value = true
  try {
      const { data } = await axios.get(route('panel.tasks.description', id))
      dialogContent.value = data?.description ?? ''
  } catch (e) {
    dialogContent.value = 'Falha ao carregar descrição.'
  } finally {
    dialogLoading.value = false
  }
}

// Atualiza status
async function updateStatus(id, newStatus, row) {
    const old = row.status
    row.status   = newStatus
    if(row.status==='done'){
        row.priority = 'low';
    }
    try {
        await axios.patch(route('panel.tasks.updateStatus', id), { status: newStatus })
        $q.notify({ type: 'positive', message: 'Status atualizado!' })
    } catch (err) {
        row.status = old // reverte
        $q.notify({ type: 'negative', message: 'Não foi possível atualizar o status.' })
    }
}

// Atualiza prioridade
async function updatePriority(id, newPriority, row) {
    const old  = row.priority
    row.priority = newPriority  // UI otimista
    try {
        await axios.patch(route('panel.tasks.updatePriority', id), { priority: newPriority })
        $q.notify({ type: 'positive', message: 'Prioridade atualizado!' })
    } catch (err) {
        row.priority = old // reverte
        $q.notify({ type: 'negative', message: 'Não foi possível atualizar a prioridade.' })
    }
}

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    })
}
/*
function formatTime(mins) {
    if (!mins) return '-'
    const h = Math.floor(mins / 60)
    const m = mins % 60
    return `${h}h ${m}m`
}

function formatValue(value) {
    if (!value) return '-'
    const num = Number(value)
    return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}*/


</script>

<template>
  <div class="tw-space-y-3">
    <q-card flat bordered class="tw-rounded-2xl">
      <q-toolbar>
        <Link :href="route('panel.tasks.create')">
          <q-btn icon="add" label="Adicionar Tarefa" color="positive" unelevated class="tw-rounded-2xl" />
        </Link>
      </q-toolbar>

      <q-separator />

      <q-card-section class="tw-grid tw-gap-3 md:tw-grid-cols-3">
        <q-input v-model="q" dense filled clearable placeholder="Buscar título ou descrição" @keyup.enter="applyFilters()">
          <template #append><q-icon name="search" class="tw-cursor-pointer" @click="applyFilters()" /></template>
        </q-input>

        <q-select v-model="status" :options="statusOptions" dense filled clearable emit-value map-options
                  label="Status" @update:model-value="applyFilters()" />

        <q-select v-model="priority" :options="priorityOptions" dense filled clearable emit-value map-options
                  label="Prioridade" @update:model-value="applyFilters()" />
      </q-card-section>

      <q-separator />

      <q-table
        flat
        :rows="rows"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        :rows-per-page-options="[10, 20, 50, 100]"
        :rows-number="meta.total"
        binary-state-sort
        @request="onRequest"
        :no-data-label="qTableLangPt.noData"
        :no-results-label="qTableLangPt.noResults"
        :loading-label="qTableLangPt.loading"
        :rows-per-page-label="qTableLangPt.recordsPerPage"
        :all-rows-label="qTableLangPt.allRows"
        :pagination-label="qTableLangPt.pagination"
      >
        <template #body-cell-title="{ row, col, value }">
          <q-td :props="{ row, col }">
            <Link :href="route('panel.tasks.edit', row.id)" class="hover:tw-underline">{{ value }}</Link>
          </q-td>
        </template>

        <template #body-cell-description="{ row, col }">
          <q-td :props="{ row, col }">
            <span class="tw-text-gray-800">{{ row.description_preview || '—' }}</span>
            <q-btn
              v-if="row.is_description_truncated"
              dense flat round icon="add" size="sm" class="tw-ml-1"
              :title="`Ver mais`"
              @click="openDescription(row.id, row.title)"
            />
          </q-td>
        </template>

          <template #body-cell-total_value="props">
              <q-td :props="props">
                  <div class="tw-flex tw-flex-col tw-gap-0.5" v-if="props.row.total_value > 0">
                      <div class="tw-text-green-600 tw-text-xs">
                          Pago: {{ formatCurrency(props.row.notes_summary.paid) }}
                      </div>
                      <div class="tw-text-yellow-600 tw-text-xs">
                          Pendente: {{ formatCurrency(props.row.notes_summary.pending) }}
                      </div>
                      <div class="tw-text-gray-800 tw-text-xs tw-font-semibold">
                          Total: {{ formatCurrency(props.row.notes_summary.total) }}
                      </div>
                  </div>
              </q-td>
          </template>

          <template #body-cell-status="{ row, col }">
              <q-td :props="{ row, col }">
                  <div class="tw-inline-flex tw-cursor-pointer">
                      <StatusChip :value="row.status" :options="statusOptions" />
                      <q-menu anchor="bottom middle" self="top middle">
                          <q-list dense>
                              <q-item
                                  v-for="opt in statusOptions"
                                  :key="opt.value"
                                  clickable v-close-popup
                                  @click="updateStatus(row.id, opt.value, row)"
                              >
                                  <q-item-section avatar>
                                      <q-badge :color="opt.color" rounded />
                                  </q-item-section>
                                  <q-item-section>{{ opt.label }}</q-item-section>
                                  <q-item-section side>
                                      <q-icon name="check" v-if="row.status === opt.value" />
                                  </q-item-section>
                              </q-item>
                          </q-list>
                      </q-menu>
                  </div>
              </q-td>
          </template>

        <!--
        <template #body-cell-priority="{ row, col }">
          <q-td :props="{ row, col }"><PriorityChip :value="row.priority" /></q-td>
        </template>-->
          <template #body-cell-priority="{ row, col }">
              <q-td :props="{ row, col }">
                  <div class="tw-inline-flex tw-cursor-pointer" v-if="row.status!=='done'">
                      <PriorityChip :value="row.priority" :options="priorityOptions" />
                      <q-menu anchor="bottom middle" self="top middle">
                          <q-list dense>
                              <q-item
                                  v-for="opt in priorityOptions"
                                  :key="opt.value"
                                  clickable v-close-popup
                                  @click="updatePriority(row.id, opt.value, row)"
                              >
                                  <q-item-section avatar>
                                      <q-badge :color="opt.color" rounded />
                                  </q-item-section>
                                  <q-item-section>{{ opt.label }}</q-item-section>
                                  <q-item-section side>
                                      <q-icon name="check" v-if="row.priority === opt.value" />
                                  </q-item-section>
                              </q-item>
                          </q-list>
                      </q-menu>
                  </div>
                  <div class="tw-flex tw-items-center" v-else>
                      <q-badge
                          label="Baixa"
                          :class="['tw-font-medium', 'tw-rounded-md','tw-p-2','tw-pe-3','tw-ps-3','tw-rounded-sm','tw-bg-gray-200']"
                          align="middle"
                      />
                  </div>
              </q-td>
          </template>


          <template #body-cell-actions="{ row, col }">
          <q-td :props="{ row, col }" class="tw-text-right tw-space-x-1">
            <Link :href="route('panel.tasks.edit', row.id)"><q-btn dense flat icon="edit" /></Link>
            <q-btn dense flat icon="delete" color="negative" @click="confirmDelete(row.id)" />
          </q-td>
        </template>

        <template #no-data>
          <div class="tw-text-gray-500 tw-py-6">Nenhuma tarefa encontrada.</div>
        </template>
      </q-table>
    </q-card>

    <!-- Dialog com descrição completa -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="tw-w-full md:tw-w-[700px] tw-max-w-full tw-rounded-2xl">
        <q-card-section class="tw-font-semibold tw-text-lg">
          {{ dialogTitle }}
        </q-card-section>
        <q-card-section>
          <div v-if="dialogLoading" class="tw-text-gray-500">Carregando...</div>
          <div v-else class="tw-whitespace-pre-line tw-text-gray-800">
            {{ dialogContent || 'Sem descrição.' }}
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Fechar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>
